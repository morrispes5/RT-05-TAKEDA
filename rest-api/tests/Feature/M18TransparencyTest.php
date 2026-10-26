<?php

namespace Tests\Feature;

use App\Domain\Finance\Dues;
use App\Domain\Finance\FlexibleDues;
use App\Support\Privacy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\Fixtures;
use Tests\Support\OpenApi;
use Tests\TestCase;

class M18TransparencyTest extends TestCase
{
    use DatabaseTransactions, Fixtures;

    private function consent($account): void
    {
        $this->as($account)->postJson('/api/v1/auth/privacy-consent', ['versi' => Privacy::FINANCE_VERSION, 'versi_teks' => Privacy::FINANCE_VERSION])
            ->assertOk()->assertJsonPath('data.privasi_perlu_setuju', false);
    }

    public function test_new_consent_requires_version_of_displayed_local_text(): void
    {
        config(['finance.v2' => true]);
        $resident = $this->warga();
        $resident->forceFill(['privasi_versi' => Privacy::VERSION])->save();
        $this->as($resident)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.privasi_versi', Privacy::FINANCE_VERSION)
            ->assertJsonPath('data.privasi_perlu_setuju', true);
        $this->getJson('/api/v1/cash/summary')->assertForbidden()->assertJsonPath('error.code', 'PRIVACY_CONSENT_REQUIRED');
        foreach ([null, Privacy::VERSION] as $text) {
            $this->postJson('/api/v1/auth/privacy-consent', ['versi' => Privacy::FINANCE_VERSION, 'versi_teks' => $text])
                ->assertStatus(422)->assertJsonPath('error.code', 'PRIVACY_CLIENT_UPDATE_REQUIRED');
            $this->assertSame(Privacy::VERSION, $resident->refresh()->privasi_versi);
        }
        $this->consent($resident);
        $this->getJson('/api/v1/cash/summary')->assertOk();
    }

    public function test_resident_cash_is_exact_aggregate_allowlist_and_cross_month_reversal_stays_historical(): void
    {
        config(['finance.v2' => true]);
        $this->travelTo(now()->setDate(2026, 10, 26));
        $resident = $this->warga();
        $actor = $this->pengurus();
        $house = $resident->rumah()->id;
        Dues::recordCash($actor, 'saldo_awal', 500000, '2026-01-01', 'Modal', 'Catatan pribadi pengurus');
        Dues::recordCash($actor, 'pengeluaran', 50000, '2026-10-26', 'Operasional', 'Nama pribadi tidak boleh terbuka');
        $payment = FlexibleDues::record($actor, $house, ['2026-10'], 100000, '2026-10-26', null, 'titipan', null,
            FlexibleDues::preview($house, ['2026-10'])['fingerprint']);
        $this->consent($resident);
        $response = $this->getJson('/api/v1/cash/summary?periode=2026-10')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame(['periode', 'saldo_awal', 'modal_awal', 'pemasukan', 'pengeluaran', 'koreksi', 'saldo_akhir'], array_keys($response->json('data')));
        $this->assertSame(['periode' => '2026-10', 'saldo_awal' => 500000, 'modal_awal' => 0, 'pemasukan' => 100000,
            'pengeluaran' => 50000, 'koreksi' => 0, 'saldo_akhir' => 550000], $response->json('data'));
        $this->assertSame([], OpenApi::errors($response->json(), 'ResidentCashEnvelope'));
        foreach ([$house, $actor->id, $resident->id, 'Catatan pribadi', 'Nama pribadi', $payment->nomor_bukti] as $private) {
            $this->assertStringNotContainsString($private, $response->getContent());
        }
        $this->travelTo(now()->setDate(2026, 11, 1));
        FlexibleDues::reverse($actor, $payment->id, 'Koreksi bulan berikutnya');
        $this->getJson('/api/v1/cash/summary?periode=2026-10')->assertOk()->assertJsonPath('data.saldo_akhir', 550000)->assertJsonPath('data.pemasukan', 100000);
        $this->getJson('/api/v1/cash/summary?periode=2026-11')->assertOk()->assertJsonPath('data.saldo_awal', 550000)
            ->assertJsonPath('data.koreksi', -100000)->assertJsonPath('data.saldo_akhir', 450000);
    }

    public function test_admin_month_matches_report_and_resident_without_classifying_reversals_as_new_money(): void
    {
        config(['finance.v2' => true]);
        $this->travelTo(now()->setDate(2026, 10, 26));
        $resident = $this->warga();
        $actor = $this->pengurus();
        $house = $resident->rumah()->id;
        Dues::recordCash($actor, 'saldo_awal', 500000, '2026-10-01', 'Modal', 'Modal awal sintetis');
        $expense = Dues::recordCash($actor, 'pengeluaran', 20000, '2026-10-26', 'Uji', 'Pengeluaran salah input');
        Dues::reverseCash($actor, $expense->id, 'Pembalikan pengeluaran uji');
        $payment = FlexibleDues::record($actor, $house, ['2026-10'], 100000, '2026-10-26', null, 'titipan', null,
            FlexibleDues::preview($house, ['2026-10'])['fingerprint']);
        FlexibleDues::reverse($actor, $payment->id, 'Pembalikan penerimaan uji');
        $admin = $this->as($actor)->getJson('/api/v1/admin/cash/summary')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $admin->assertJsonPath('data.saldo', 500000)->assertJsonPath('data.bulan_ini.pemasukan', 100000)
            ->assertJsonPath('data.bulan_ini.pengeluaran', 20000)->assertJsonPath('data.bulan_ini.koreksi', -80000)
            ->assertJsonPath('data.bulan_ini.modal_awal', 500000);
        $report = $this->getJson('/api/v1/admin/finance-reports?periode=2026-10')->assertOk()->json('data.kas');
        $this->consent($resident);
        $public = $this->getJson('/api/v1/cash/summary?periode=2026-10')->assertOk()->json('data');
        $this->assertSame($admin->json('data.bulan_ini'), $report);
        $this->assertSame($report, $public);
    }

    public function test_access_and_validation_keep_other_house_dues_private(): void
    {
        config(['finance.v2' => true]);
        $main = $this->warga();
        $additional = $this->tambahan('1', 'tambahan@contoh.test');
        $pending = $this->pemohon('1', 'menunggu@contoh.test');
        $other = $this->warga('2')->rumah()->id;
        foreach ([$main, $additional] as $account) {
            $this->consent($account);
            $this->getJson('/api/v1/cash/summary')->assertOk();
            $this->getJson('/api/v1/dues/transparency')->assertForbidden();
            $this->getJson("/api/v1/admin/houses/$other/dues")->assertForbidden();
            $this->getJson('/api/v1/cash/summary?periode[]=2026-10')->assertStatus(422);
        }
        $this->as($pending)->getJson('/api/v1/cash/summary')->assertForbidden();
        config(['finance.v2' => false]);
        $this->as($main)->getJson('/api/v1/cash/summary')->assertNotFound();
    }
}
