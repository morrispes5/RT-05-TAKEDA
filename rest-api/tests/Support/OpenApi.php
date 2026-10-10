<?php

namespace Tests\Support;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use Symfony\Component\Yaml\Yaml;

/**
 * Memuat docs/openapi.yaml dan memvalidasi payload terhadap components.schemas
 * (OpenAPI 3.1 = JSON Schema 2020-12).
 */
final class OpenApi
{
    private const ID = 'https://rt05takeda.test/openapi.json';

    private static ?array $spec = null;

    private static ?Validator $validator = null;

    public static function spec(): array
    {
        return self::$spec ??= Yaml::parseFile(dirname(__DIR__, 3).'/docs/openapi.yaml');
    }

    /** @return list<string> pesan error; kosong bila valid. */
    public static function errors(mixed $payload, string $schema): array
    {
        if (self::$validator === null) {
            self::$validator = new Validator;
            self::$validator->resolver()->registerRaw(json_encode(self::spec(), JSON_THROW_ON_ERROR), self::ID);
        }

        $data = json_decode(json_encode($payload, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
        $result = self::$validator->validate($data, self::ID.'#/components/schemas/'.$schema);

        if ($result->isValid()) {
            return [];
        }

        $errors = [];
        foreach ((new ErrorFormatter)->format($result->error()) as $pointer => $messages) {
            $errors[] = $pointer.': '.implode('; ', (array) $messages);
        }

        return $errors;
    }

    /** @return array<string, array{status: string, milestone: string}> kunci "METHOD /path". */
    public static function operations(): array
    {
        $operations = [];
        foreach (self::spec()['paths'] as $path => $item) {
            foreach ($item as $method => $operation) {
                if (! in_array($method, ['get', 'put', 'post', 'patch', 'delete'], true)) {
                    continue;
                }
                $operations[strtoupper($method).' '.$path] = [
                    'status' => $operation['x-status'] ?? '',
                    'milestone' => $operation['x-milestone'] ?? '',
                ];
            }
        }

        return $operations;
    }
}
