<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Support\ServiceSheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ServiceImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->make();
        $this->admin->role = User::ROLE_ADMIN;
        $this->admin->save();

        $design = ServiceCategory::create(['name' => 'Graphic Design', 'slug' => 'graphic-design']);
        Service::create(['service_category_id' => $design->id, 'name' => 'Logo Design', 'pricing_type' => 'from', 'price_cents' => 150000, 'is_from' => true, 'is_popular' => true]);
        Service::create(['service_category_id' => $design->id, 'name' => 'Flyer Design', 'pricing_type' => 'fixed', 'price_cents' => 65000]);
        Service::create(['service_category_id' => $design->id, 'name' => 'Old Service', 'pricing_type' => 'fixed', 'price_cents' => 10000]);
    }

    /** @param array<string, array<int, array<int, string|int>>> $sheets */
    private function xlsx(array $sheets): UploadedFile
    {
        $book = new Spreadsheet;
        $book->removeSheetByIndex(0);
        foreach ($sheets as $title => $rows) {
            $sheet = $book->createSheet()->setTitle($title);
            $sheet->fromArray(array_merge([['Category', 'Service', 'Price type', 'Price']], $rows), null, 'A1');
        }
        $path = tempnam(sys_get_temp_dir(), 'svc').'.xlsx';
        (new Xlsx($book))->save($path);

        return new UploadedFile($path, 'services.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_price_formats_are_understood(): void
    {
        $this->assertSame(['pricing_type' => 'fixed', 'price_cents' => 45000, 'is_from' => false, 'unit' => null], ServiceSheet::parsePrice('Fixed', '450'));
        $this->assertSame(['pricing_type' => 'from', 'price_cents' => 150000, 'is_from' => true, 'unit' => null], ServiceSheet::parsePrice('', 'From R1 500'));
        $this->assertSame(['pricing_type' => 'hourly', 'price_cents' => 45000, 'is_from' => false, 'unit' => 'hour'], ServiceSheet::parsePrice('', 'R450/hr'));
        $this->assertSame(['pricing_type' => 'monthly', 'price_cents' => 49900, 'is_from' => true, 'unit' => 'month'], ServiceSheet::parsePrice('Monthly', 'From R499 / month'));
        $this->assertSame(['pricing_type' => 'from', 'price_cents' => 45000, 'is_from' => true, 'unit' => 'post'], ServiceSheet::parsePrice('From', 'From R450 / post'));
        $this->assertSame('quote', ServiceSheet::parsePrice('Quote', '')['pricing_type']);
        $this->assertSame('quote', ServiceSheet::parsePrice('', 'Quote on request')['pricing_type']);
        $this->assertNull(ServiceSheet::parsePrice('Fixed', 'cheap'));
    }

    public function test_preview_shows_changes_without_saving_then_apply_updates(): void
    {
        $file = $this->xlsx([
            'Graphic Design' => [
                ['Graphic Design', 'Logo Design', 'From', 1800],
                ['Graphic Design', 'Flyer Design', 'Fixed', 650],
                ['Graphic Design', 'Business Card Design', 'Fixed', 'R450'],
                ['Graphic Design', '', 'Fixed', 100],
                ['Graphic Design', 'Banner', 'Fixed', 'cheap'],
            ],
            'Video' => [['Video Services', 'Promo Video', 'From', 3500]],
        ]);

        $page = $this->actingAs($this->admin)->post('/admin/services/import', ['sheet' => $file]);
        $page->assertOk()->assertSee('Check the changes')->assertSee('R1 800')->assertSee('Business Card Design')
            ->assertSee('Video Services')->assertSee('Old Service')->assertSee('2 rows will be skipped');

        // Nothing has changed yet.
        $this->assertSame(150000, Service::where('name', 'Logo Design')->value('price_cents'));
        $this->assertSame(0, Service::where('name', 'Promo Video')->count());

        $token = session('service_import.token');
        $this->actingAs($this->admin)->post('/admin/services/import/apply', ['token' => $token, 'hide_missing' => '1'])->assertRedirect('/admin/services');

        $this->assertSame(180000, Service::where('name', 'Logo Design')->value('price_cents'));
        $this->assertTrue(Service::where('name', 'Logo Design')->value('is_popular'), 'Popular is kept');
        $this->assertSame(45000, Service::where('name', 'Business Card Design')->value('price_cents'));
        $this->assertSame('Video Services', Service::where('name', 'Promo Video')->firstOrFail()->category->name);
        $this->assertFalse(Service::where('name', 'Old Service')->firstOrFail()->is_active, 'Missing services are hidden');
        $this->assertSame(1, Service::where('name', 'Old Service')->count(), 'and never deleted');
        $this->assertDatabaseHas('audit_logs', ['action' => 'catalogue.imported']);

        // The same upload cannot be applied twice.
        $this->actingAs($this->admin)->post('/admin/services/import/apply', ['token' => $token])->assertRedirect('/admin/services/import');
    }

    public function test_missing_services_can_be_left_alone_and_hidden_ones_come_back(): void
    {
        Service::where('name', 'Flyer Design')->update(['is_active' => false]);
        $file = $this->xlsx(['Graphic Design' => [['Graphic Design', 'Flyer Design', 'Fixed', 650]]]);

        $this->actingAs($this->admin)->post('/admin/services/import', ['sheet' => $file])->assertOk();
        $this->actingAs($this->admin)->post('/admin/services/import/apply', ['token' => session('service_import.token')]);

        $this->assertTrue(Service::where('name', 'Flyer Design')->value('is_active'));
        $this->assertTrue(Service::where('name', 'Old Service')->value('is_active'));
    }

    public function test_export_downloads_and_reimports_with_no_changes(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/services/export');
        $response->assertOk()->assertDownload();

        $path = ServiceSheet::export();
        $diff = ServiceSheet::diff(ServiceSheet::read($path)['rows']);
        $this->assertSame([0, 0, 0, 3], [count($diff['new']), count($diff['changed']), count($diff['missing']), $diff['unchanged']]);
    }

    public function test_bad_files_are_refused_and_clients_cannot_import(): void
    {
        $this->actingAs($this->admin)->post('/admin/services/import', ['sheet' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])->assertSessionHasErrors('sheet');

        $empty = $this->xlsx(['Notes' => []]);
        $this->actingAs($this->admin)->post('/admin/services/import', ['sheet' => $empty])->assertSessionHasErrors('sheet');

        $client = Client::create(['business_name' => 'X']);
        $user = User::factory()->make();
        $user->role = User::ROLE_CLIENT;
        $user->client_id = $client->id;
        $user->save();
        $this->actingAs($user)->get('/admin/services/export')->assertNotFound();
        $this->actingAs($user)->post('/admin/services/import', ['sheet' => $this->xlsx(['A' => [['A', 'B', 'Fixed', 1]]])])->assertNotFound();
    }
}
