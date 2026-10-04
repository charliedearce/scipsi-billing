<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\LegacyImport\LegacyImportSchema;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use ZipArchive;

class LegacyImportTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/admin/legacy-imports';

    private User $admin;

    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'cache.limiter' => 'array']);
        Storage::fake('local_private');
        $org = Organization::create(['name' => 'History test', 'code' => 'HISTORY', 'is_active' => true]);
        $this->admin = User::factory()->create(['organization_id' => $org->id, 'status' => 'active']);
        $role = Role::create(['organization_id' => $org->id, 'name' => 'Administrator', 'label' => 'Administrator', 'is_system' => true]);
        $this->admin->roles()->attach($role);
        $this->actingAs($this->admin, 'sanctum');
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        parent::tearDown();
    }

    public function test_stored_penny_difference_survives_preview_and_archive_without_live_financial_effects(): void
    {
        $rows = $this->billRows();
        $batch = $this->upload($rows)->assertCreated()->assertJsonMissingPath('data.private_path')->json('data');
        $this->getJson(self::URL.'/records')->assertJsonPath('total', 0);

        $this->postJson(self::URL."/{$batch['id']}/advance")->assertOk()->assertJsonPath('data.status', 'READY');
        $this->getJson(self::URL.'/records')->assertJsonPath('total', 0);
        $this->getJson(self::URL.'/records?table=tbl_bill_trans&search=0000063293')
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.reference', '0000063293')
            ->assertJsonPath('data.0.payload.bt_bill_num', '0000063293');
        $this->getJson(self::URL.'/records?table=tbl_item_trans&search=0000063293')
            ->assertJsonPath('total', 2)
            ->assertJsonPath('data.0.payload.it_qty', '19.000')
            ->assertJsonPath('data.0.payload.it_bill_num', '0000063293');

        $id = DB::table('legacy_import_records')->where('source_table', 'tbl_bill_trans')->value('id');
        $this->getJson(self::URL.'/records/'.$id)->assertOk()
            ->assertJsonPath('data.reference', '0000063293')
            ->assertJsonPath('data.due_amount', '19921.91')
            ->assertJsonPath('data.payload.bt_scipsi', '19921.91')
            ->assertJsonPath('data.reconciliation.amounts.due_amount.lines', '19921.92')
            ->assertJsonPath('data.reconciliation.amounts.due_amount.difference', '-0.01')
            ->assertJsonPath('data.issues', ['HEADER_DETAIL_DIFFERENCE']);
        $this->finalize($batch)->assertOk()->assertJsonPath('data.summary.dispositions.IMPORTED', 4);
        $this->getJson(self::URL.'/records?table=tbl_bill_trans&search=0000063293&review_only=1')
            ->assertJsonPath('total', 1)->assertJsonPath('data.0.due_amount', '19921.91')
            ->assertJsonPath('data.0.payload.bt_bill_num', '0000063293');
        $this->getJson(self::URL.'/records?table=tbl_item_trans&search=0000063293')
            ->assertJsonPath('total', 2)
            ->assertJsonPath('data.0.payload.it_unit', 'BOX')
            ->assertJsonPath('data.0.payload.it_service', 'LIFT ON/OFF')
            ->assertJsonPath('data.0.payload.it_gross', '9881.90');
        foreach (['invoices', 'invoice_items', 'receipts', 'receipt_allocations', 'document_numbers', 'document_series', 'invoice_outbox', 'in_app_notifications', 'notification_events', 'customers'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertDatabaseHas('audit_logs', ['action' => 'legacy_import.finalized', 'organization_id' => $this->admin->organization_id]);
    }

    public function test_upload_and_finalize_are_idempotent_and_changed_source_rows_are_conflicts(): void
    {
        $rows = $this->billRows();
        $batch = $this->upload($rows)->assertCreated()->json('data');
        $this->upload($rows)->assertCreated()->assertJsonPath('data.id', $batch['id']);
        $this->assertCount(1, Storage::disk('local_private')->allFiles());
        $this->postJson(self::URL."/{$batch['id']}/advance")->assertOk();
        $this->finalize($batch)->assertOk();
        $this->finalize($batch)->assertOk();

        $rows[1]['data']['bt_scipsi'] = '20000.00';
        $second = $this->upload($rows, ['exported_at' => '2026-09-22T00:00:00Z'])->assertCreated()->json('data');
        $this->postJson(self::URL."/{$second['id']}/advance")->assertOk();
        $this->finalize($second)->assertOk()
            ->assertJsonPath('data.summary.dispositions.CONFLICT', 1)
            ->assertJsonPath('data.summary.dispositions.DUPLICATE', 3);

        $this->getJson(self::URL.'/records?table=tbl_bill_trans')->assertJsonPath('total', 1)->assertJsonPath('data.0.due_amount', '19921.91');
        $this->getJson(self::URL."/records?batch_id={$second['id']}&disposition=CONFLICT")->assertJsonPath('total', 1)->assertJsonPath('data.0.due_amount', '20000.00');
        $this->assertDatabaseCount('legacy_import_batches', 2);
        $this->assertDatabaseCount('legacy_import_records', 8);
    }

    public function test_staging_resumes_after_bounded_chunks_and_rejects_early_finalization_or_wrong_hash(): void
    {
        $rows = [];
        for ($i = 1; $i <= 501; $i++) {
            $rows[] = $this->row('tbl_account', (string) $i, ['ac_code' => 'A'.$i, 'ac_name' => 'Archived buyer '.$i]);
        }
        $batch = $this->upload($rows)->assertCreated()->json('data');
        $this->finalize($batch)->assertUnprocessable();

        $this->postJson(self::URL."/{$batch['id']}/advance")->assertOk()->assertJsonPath('data.processed_rows', 500)->assertJsonPath('data.status', 'STAGING');
        $this->postJson(self::URL."/{$batch['id']}/advance")->assertOk()->assertJsonPath('data.processed_rows', 501)->assertJsonPath('data.status', 'READY');
        $this->postJson(self::URL."/{$batch['id']}/advance")->assertOk()->assertJsonPath('data.processed_rows', 501);
        $wrongHash = [...$batch, 'package_hash' => str_repeat('a', 64)];
        $this->finalize($wrongHash)->assertUnprocessable();

        $this->assertDatabaseCount('legacy_import_records', 501);
        $this->assertDatabaseMissing('legacy_import_records', ['disposition' => 'IMPORTED']);
        $this->finalize($batch)->assertOk();
    }

    public function test_403_for_non_admin_and_404_for_other_organization_batches_and_records(): void
    {
        $batch = $this->upload($this->billRows())->assertCreated()->json('data');
        $this->postJson(self::URL."/{$batch['id']}/advance")->assertOk();
        $record = DB::table('legacy_import_records')->value('id');
        $otherOrg = Organization::create(['name' => 'Other history', 'code' => 'OTHER', 'is_active' => true]);
        $other = User::factory()->create(['organization_id' => $otherOrg->id, 'status' => 'active']);
        $other->roles()->attach($this->admin->roles->first());
        $this->actingAs($other, 'sanctum');

        $this->getJson(self::URL)->assertJsonPath('total', 0);
        $this->getJson(self::URL.'/records')->assertJsonPath('total', 0);
        $this->getJson(self::URL."/{$batch['id']}")->assertNotFound();
        $this->getJson(self::URL."/records?batch_id={$batch['id']}")->assertNotFound();
        $this->getJson(self::URL.'/records/'.$record)->assertNotFound();
        $this->postJson(self::URL."/{$batch['id']}/advance")->assertNotFound();
        $this->finalize($batch)->assertNotFound();
        $this->upload($this->billRows())->assertCreated();
        $this->assertDatabaseCount('legacy_import_batches', 2);

        $user = User::factory()->create(['organization_id' => $this->admin->organization_id, 'status' => 'active']);
        $this->actingAs($user, 'sanctum');
        foreach (['', '/schema', '/toolkit', "/{$batch['id']}", '/records', '/records/'.$record] as $path) {
            $this->getJson(self::URL.$path)->assertForbidden();
        }
        $this->upload($this->billRows())->assertForbidden();
        $this->postJson(self::URL."/{$batch['id']}/advance")->assertForbidden();
        $this->finalize($batch)->assertForbidden();
    }

    public function test_rejects_unexpected_columns_numeric_json_values_and_malformed_row_shapes(): void
    {
        foreach ([
            $this->row('tbl_account', '1', ['password' => 'not-exportable']),
            $this->row('tbl_bill_trans', '1', ['bt_scipsi' => 994.01]),
            ['table' => [], 'key' => '1', 'data' => []],
            $this->row('tbl_settings', '2'),
        ] as $row) {
            $batch = $this->upload([$row])->assertCreated()->json('data');
            $this->postJson(self::URL."/{$batch['id']}/advance")->assertUnprocessable();
            $this->getJson(self::URL."/{$batch['id']}")->assertJsonPath('data.status', 'FAILED');
            $this->finalize($batch)->assertUnprocessable();
        }
        $this->assertDatabaseCount('legacy_import_records', 0);
    }

    public function test_rejects_manifest_count_mismatch_and_duplicate_row_identity_atomically(): void
    {
        $counts = array_fill_keys(array_keys(app(LegacyImportSchema::class)->tables()), 0);
        $counts['tbl_account'] = 2;
        $row = $this->row('tbl_account', '1', ['ac_code' => 'A']);
        $batch = $this->upload([$row], ['counts' => $counts])->assertCreated()->json('data');
        $this->postJson(self::URL."/{$batch['id']}/advance")->assertUnprocessable();
        $duplicate = $this->upload([$row, $row])->assertCreated()->json('data');

        $this->postJson(self::URL."/{$duplicate['id']}/advance")->assertUnprocessable();

        $this->assertDatabaseCount('legacy_import_records', 0);
        $this->assertDatabaseHas('legacy_import_batches', ['id' => $batch['id'], 'status' => 'FAILED']);
        $this->assertDatabaseHas('legacy_import_batches', ['id' => $duplicate['id'], 'status' => 'FAILED']);
    }

    public function test_rejects_unsafe_zip_paths_and_bad_manifests_without_leaving_private_files(): void
    {
        $file = $this->package([], [], '../history.jsonl');
        $this->postJson(self::URL, ['package' => $file])->assertUnprocessable();
        $this->upload([], ['source_key' => '../bad'])->assertUnprocessable();
        $this->upload([], ['counts' => ['tbl_users' => 1]])->assertUnprocessable();

        $this->assertDatabaseCount('legacy_import_batches', 0);
        $this->assertCount(0, Storage::disk('local_private')->allFiles());
    }

    public function test_flags_orphans_invalid_dates_amount_precision_and_used_numbers_without_rewriting_raw_values(): void
    {
        $rows = [
            $this->row('tbl_bill_trans', '1', ['bt_bill_num' => '0000000001', 'bt_date' => '2020-02-30T00:00:00', 'bt_status' => 'UNKNOWN', 'bt_total' => '1.001']),
            $this->row('tbl_bill_no', '1', ['b_num' => '0000000001']),
            $this->row('tbl_orbill_trans', '1', ['ot_or_no' => '0000000002', 'ot_bill_no' => '0000000003']),
        ];
        $batch = $this->upload($rows)->assertCreated()->json('data');

        $this->postJson(self::URL."/{$batch['id']}/advance")->assertOk()->assertJsonPath('data.status', 'READY');

        $bill = DB::table('legacy_import_records')->where('source_table', 'tbl_bill_trans')->value('id');
        $billIssues = $this->getJson(self::URL.'/records/'.$bill)->assertJsonPath('data.payload.bt_total', '1.001')->assertJsonPath('data.gross_amount', null)->json('data.issues');
        foreach (['INVALID_DATE', 'UNKNOWN_STATUS', 'MISSING_DETAILS'] as $issue) {
            $this->assertContains($issue, $billIssues);
        }
        $pool = DB::table('legacy_import_records')->where('source_table', 'tbl_bill_no')->value('id');
        $this->getJson(self::URL.'/records/'.$pool)->assertJsonPath('data.issues', ['NUMBER_ALREADY_ISSUED']);
        $allocation = DB::table('legacy_import_records')->where('source_table', 'tbl_orbill_trans')->value('id');
        $issues = $this->getJson(self::URL.'/records/'.$allocation)->assertOk()->json('data.issues');
        $this->assertContains('MISSING_BILL', $issues);
        $this->assertContains('MISSING_RECEIPT', $issues);
    }

    public function test_imported_history_cannot_be_mutated_in_the_database(): void
    {
        $batch = $this->upload($this->billRows())->assertCreated()->json('data');
        $this->postJson(self::URL."/{$batch['id']}/advance")->assertOk();
        $this->finalize($batch)->assertOk();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Legacy history is immutable');
        DB::table('legacy_import_records')->where('batch_id', $batch['id'])->update(['due_amount' => '0.00']);
    }

    public function test_imported_history_cannot_be_deleted(): void
    {
        $batch = $this->upload($this->billRows())->assertCreated()->json('data');
        $this->postJson(self::URL."/{$batch['id']}/advance")->assertOk();
        $this->finalize($batch)->assertOk();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Legacy history is immutable');
        DB::table('legacy_import_records')->where('batch_id', $batch['id'])->delete();
    }

    public function test_receipt_allocation_links_and_stored_partial_payment_flags_are_preserved(): void
    {
        $rows = [...$this->billRows(),
            $this->row('tbl_or_trans', '15', ['or_num' => '0000000015', 'or_acc_num' => '11001-0406', 'or_date' => '2020-06-01T12:00:00', 'or_status' => 'FALSE', 'or_bill_amount' => '1000.00', 'or_discount' => '0.00', 'or_net' => '1100.00', 'or_bill_vat' => '120.00']),
            $this->row('tbl_orbill_trans', '20', ['ot_or_no' => '0000000015', 'ot_bill_no' => '0000063293', 'ot_scipsi' => '1000.00', 'ot_disc' => '0.00', 'ot_net' => '1100.00', 'ot_bill_vat' => '120.00', 'ot_citw' => '20.00', 'ot_partial' => '1000.00', 'ot_balance' => '18921.91', 'ot_indipartial' => 'P']),
        ];
        $batch = $this->upload($rows)->assertCreated()->json('data');
        $this->postJson(self::URL."/{$batch['id']}/advance")->assertOk();
        $this->finalize($batch)->assertOk();

        $receipt = DB::table('legacy_import_records')->where('source_table', 'tbl_or_trans')->value('id');
        $this->getJson(self::URL.'/records/'.$receipt)->assertJsonPath('data.issues', [])
            ->assertJsonPath('data.reconciliation.amounts.net_amount.difference', '0.00');
        $allocation = $this->getJson(self::URL.'/records?table=tbl_orbill_trans&reference=0000000015')
            ->assertJsonPath('total', 1)->assertJsonPath('data.0.related_reference', '0000063293')->json('data.0.id');
        $this->getJson(self::URL.'/records/'.$allocation)->assertJsonPath('data.payload.ot_indipartial', 'P')
            ->assertJsonPath('data.payload.ot_balance', '18921.91')->assertJsonPath('data.issues', []);
        $this->assertDatabaseCount('receipt_allocations', 0);
    }

    public function test_export_toolkit_contains_only_the_script_and_allowlisted_schema(): void
    {
        $response = $this->get(self::URL.'/toolkit')->assertDownload('legacy-export-toolkit.zip');
        $path = $response->baseResponse->getFile()->getPathname();
        $this->temporaryFiles[] = $path;
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $this->assertSame(2, $zip->numFiles);
        $this->assertSame('Export-LegacyHistory.ps1', $zip->getNameIndex(0));
        $this->assertSame('legacy-import-schema.json', $zip->getNameIndex(1));
        $zip->close();
        $this->getJson(self::URL.'/schema')->assertJsonFragment(['value' => 'tbl_bill_trans', 'label' => 'Bills']);
    }

    public function test_returns_401_without_authentication_and_403_for_suspended_administrator(): void
    {
        $this->admin->update(['status' => 'suspended']);
        $this->getJson(self::URL)->assertForbidden();
        $this->upload([])->assertForbidden();
        $this->app['auth']->forgetGuards();

        $this->getJson(self::URL)->assertUnauthorized();
        $this->getJson(self::URL.'/records')->assertUnauthorized();
        $this->postJson(self::URL)->assertUnauthorized();
        $this->assertDatabaseCount('legacy_import_batches', 0);
    }

    public function test_search_filters_are_bounded_and_treat_percent_as_literal(): void
    {
        $batch = $this->upload([$this->row('tbl_account', '1', ['ac_code' => 'A', 'ac_name' => '100% Shipping'])])->assertCreated()->json('data');
        $this->postJson(self::URL."/{$batch['id']}/advance")->assertOk();
        $this->finalize($batch)->assertOk();

        $this->getJson(self::URL.'/records?search=%25')->assertJsonPath('total', 1);
        $this->getJson(self::URL.'/records?search=missing')->assertJsonPath('total', 0);
        $this->getJson(self::URL.'/records?per_page=10000')->assertUnprocessable();
        $this->getJson(self::URL.'/records?table=tbl_users')->assertUnprocessable();
        $this->getJson(self::URL.'/records?from=2026-01-01&to=2026-01-31')->assertJsonPath('total', 0);
    }

    private function finalize(array $batch): TestResponse
    {
        return $this->postJson(self::URL."/{$batch['id']}/finalize", ['package_hash' => $batch['package_hash'], 'reason' => 'Archive test; balances remain inactive.']);
    }

    private function upload(array $rows, array $manifestOverrides = []): TestResponse
    {
        return $this->postJson(self::URL, ['package' => $this->package($rows, $manifestOverrides)]);
    }

    private function package(array $rows, array $manifestOverrides = [], string $entry = 'history.jsonl'): UploadedFile
    {
        $counts = array_fill_keys(array_keys(app(LegacyImportSchema::class)->tables()), 0);
        foreach ($rows as $row) {
            if (is_string($row['table'] ?? null) && isset($counts[$row['table']])) {
                $counts[$row['table']]++;
            }
        }
        $manifest = [...[
            'format' => 'scipsi-legacy-history', 'version' => 1, 'source_key' => 'test-legacy',
            'exported_at' => '2026-09-21T00:00:00Z', 'consistency' => 'READ_ONLY_DATABASE', 'scope' => 'SAMPLE', 'counts' => $counts,
        ], ...$manifestOverrides];
        $path = tempnam(sys_get_temp_dir(), 'legacy-test-');
        $this->temporaryFiles[] = $path;
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $lines = array_map(fn (array $row): string => json_encode($row, JSON_THROW_ON_ERROR), [$manifest, ...$rows]);
        $zip->addFromString($entry, implode("\n", $lines)."\n");
        $zip->close();

        return new UploadedFile($path, 'history.zip', 'application/zip', null, true);
    }

    private function row(string $table, string $key, array $values = []): array
    {
        $schema = app(LegacyImportSchema::class)->tables()[$table];
        $data = array_fill_keys(explode(' ', $schema['columns']), null);
        if (isset($schema['key'])) {
            $data[$schema['key']] = $key;
        }

        return ['table' => $table, 'key' => $key, 'data' => [...$data, ...$values]];
    }

    private function billRows(): array
    {
        $items = [
            'it_bill_num' => '0000063293', 'it_qty' => '19.000', 'it_rate' => '520.10', 'it_unit' => 'BOX',
            'it_service' => 'LIFT ON/OFF', 'it_gross' => '9881.90', 'it_ppa' => '988.19', 'it_disc' => '0.00',
            'it_net' => '8893.71', 'it_tax' => '1067.25', 'it_scipsi' => '9960.96', 'it_charge' => '10949.15',
        ];

        return [
            $this->row('tbl_account', '1', ['ac_code' => '11001-0406', 'ac_name' => 'Historical shipping company']),
            $this->row('tbl_bill_trans', '63293', [
                'bt_bill_num' => '0000063293', 'bt_acc_num' => '11001-0406', 'bt_account' => 'Historical shipping company',
                'bt_date' => '2014-09-15T00:00:00.000', 'bt_status' => 'FALSE', 'bt_total' => '19763.8000', 'bt_ppa' => '1976.38',
                'bt_disc' => '0.00', 'bt_net' => '17787.42', 'bt_vat' => '2134.49', 'bt_scipsi' => '19921.91',
            ]),
            $this->row('tbl_item_trans', '1', [...$items, 'it_cargo' => 'LIFT - ON CHARGES']),
            $this->row('tbl_item_trans', '2', [...$items, 'it_cargo' => 'LIFT - OFF CHARGES']),
        ];
    }
}
