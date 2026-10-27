<?php

namespace App\Domain\Community\Push;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * FCM HTTP v1 tanpa SDK tambahan: hanya Laravel HTTP client + ext-openssl, jadi berjalan sama di
 * shared hosting maupun VPS. Kredensial service account dari env (path berkas atau base64), tidak
 * pernah dicetak. Token akses OAuth disimpan terenkripsi di cache default ±55 menit.
 * Satu permintaan per token (v1 tidak punya multicast), dikirim paralel per kelompok kecil.
 */
final class FcmGateway implements PushGateway
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private const CACHE_KEY = 'rt05:fcm:access-token';

    // Tetap, bukan token_uri dari berkas kredensial: host OAuth tidak bisa dialihkan lewat config.
    private const TOKEN_URI = 'https://oauth2.googleapis.com/token';

    /** @var array{client_email: string, private_key: string, project_id: string}|null */
    private ?array $credentials;

    private int $timeout;

    private int $concurrency;

    public function __construct(array $config)
    {
        $this->credentials = self::credentials($config);
        $this->timeout = max(2, (int) ($config['timeout'] ?? 10));
        $this->concurrency = max(1, (int) ($config['concurrency'] ?? 20));
    }

    public function configured(): bool
    {
        return $this->credentials !== null;
    }

    public function send(array $messages): array
    {
        if ($messages === []) {
            return [];
        }
        if (! $this->credentials) {
            return array_map(fn () => PushResult::Failed, $messages);
        }
        try {
            $access = $this->accessToken();
        } catch (Throwable $e) {
            // Jaringan/OAuth gagal: semua dicoba ulang nanti. Hanya nama kelas (tanpa respons Google).
            Log::warning('push.fcm_auth_failed', ['exception' => class_basename($e)]);

            return array_map(fn () => PushResult::Retry, $messages);
        }

        $url = 'https://fcm.googleapis.com/v1/projects/'.rawurlencode($this->credentials['project_id']).'/messages:send';
        $results = [];
        foreach (array_chunk($messages, $this->concurrency, true) as $chunk) {
            try {
                $responses = Http::pool(function (Pool $pool) use ($chunk, $url, $access) {
                    foreach ($chunk as $key => $m) {
                        $pool->as((string) $key)->withToken($access)->acceptJson()->asJson()
                            ->connectTimeout(5)->timeout($this->timeout)
                            ->post($url, ['message' => self::payload($m)]);
                    }
                });
            } catch (Throwable $e) {
                Log::warning('push.fcm_pool_failed', ['exception' => class_basename($e)]);
                foreach (array_keys($chunk) as $key) {
                    $results[$key] = PushResult::Retry;
                }

                continue;
            }
            foreach (array_keys($chunk) as $key) {
                $results[$key] = $this->classify($responses[(string) $key] ?? null);
            }
        }

        return $results;
    }

    /** @return array<string, mixed> */
    public static function payload(PushMessage $m): array
    {
        return [
            'token' => $m->token,
            'notification' => ['title' => $m->title, 'body' => $m->body],
            // Data FCM wajib string.
            'data' => array_map('strval', $m->data),
            'android' => [
                'priority' => 'HIGH',
                'ttl' => $m->alarm ? max(1, min(600, $m->alarmTtlSeconds)).'s' : '86400s',
                'collapse_key' => $m->tag,
                'notification' => ['tag' => $m->tag] + ($m->alarm ? ['channel_id' => 'rt05_darurat_v1', 'icon' => 'ic_emergency', 'visibility' => 'PRIVATE', 'notification_priority' => 'PRIORITY_MAX', 'default_vibrate_timings' => true] : []),
            ],
        ];
    }

    private function classify(mixed $response): PushResult
    {
        if (! $response instanceof Response) {
            return PushResult::Retry; // ConnectionException dsb.
        }
        if ($response->successful()) {
            return PushResult::Sent;
        }
        $status = $response->status();
        $codes = collect($response->json('error.details') ?? [])->pluck('errorCode')->filter()->all();
        if ($status === 401) {
            Cache::forget(self::CACHE_KEY);

            return PushResult::Retry;
        }
        if ($status === 404 || in_array('UNREGISTERED', $codes, true) || in_array('SENDER_ID_MISMATCH', $codes, true)) {
            return PushResult::InvalidToken;
        }
        if ($status === 400 && str_contains(strtolower((string) $response->json('error.message')), 'registration token')) {
            return PushResult::InvalidToken;
        }
        if ($status === 429 || $status >= 500) {
            return PushResult::Retry;
        }
        Log::warning('push.fcm_rejected', ['status' => $status, 'codes' => $codes]);

        return PushResult::Failed;
    }

    private function accessToken(): string
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_string($cached)) {
            try {
                return Crypt::decryptString($cached);
            } catch (Throwable) {
                Cache::forget(self::CACHE_KEY);
            }
        }

        $c = $this->credentials;
        $now = time();
        $jwt = self::jwt(['alg' => 'RS256', 'typ' => 'JWT'], [
            'iss' => $c['client_email'],
            'scope' => self::SCOPE,
            'aud' => self::TOKEN_URI,
            'iat' => $now,
            'exp' => $now + 3600,
        ], $c['private_key']);

        $response = Http::asForm()->acceptJson()->connectTimeout(5)->timeout($this->timeout)->post(self::TOKEN_URI, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ])->throw();
        $token = (string) $response->json('access_token');
        if ($token === '') {
            throw new \RuntimeException('fcm_oauth_empty');
        }
        $ttl = max(60, (int) $response->json('expires_in', 3600) - 300);
        Cache::put(self::CACHE_KEY, Crypt::encryptString($token), $ttl);

        return $token;
    }

    public static function jwt(array $header, array $claims, string $privateKey): string
    {
        $b64 = fn (string $v) => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
        $input = $b64(json_encode($header, JSON_UNESCAPED_SLASHES)).'.'.$b64(json_encode($claims, JSON_UNESCAPED_SLASHES));
        $key = openssl_pkey_get_private($privateKey);
        if ($key === false || ! openssl_sign($input, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('fcm_jwt_sign_failed');
        }

        return $input.'.'.$b64($signature);
    }

    /** @return array{client_email: string, private_key: string, project_id: string}|null */
    public static function credentials(array $config): ?array
    {
        $json = null;
        $path = (string) ($config['credentials_path'] ?? '');
        $base64 = (string) ($config['credentials_base64'] ?? '');
        if ($path !== '' && is_readable($path)) {
            $json = file_get_contents($path);
        } elseif ($base64 !== '') {
            $json = base64_decode($base64, true);
        }
        $data = is_string($json) ? json_decode($json, true) : null;
        if (! is_array($data) || ($data['type'] ?? null) !== 'service_account'
            || empty($data['client_email']) || empty($data['private_key'])) {
            return null;
        }
        $project = (string) (($config['project_id'] ?? '') ?: ($data['project_id'] ?? ''));
        if ($project === '') {
            return null;
        }

        return [
            'client_email' => (string) $data['client_email'],
            'private_key' => (string) $data['private_key'],
            'project_id' => $project,
        ];
    }
}
