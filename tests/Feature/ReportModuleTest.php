<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\SystemSetting;
use App\Services\FinancialReportService;
use Database\Seeders\ClientModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_financial_report_tabs_render_real_data(): void
    {
        $this->seed(ClientModuleSeeder::class);
        foreach (array_keys(FinancialReportService::TYPES) as $type) {
            $this->get(route('reports.index', ['report_type' => $type, 'start_date' => today()->subYear()->format('Y-m-d'), 'end_date' => today()->format('Y-m-d')]))
                ->assertOk()->assertSee(FinancialReportService::TYPES[$type]);
        }
    }

    public function test_portfolio_report_filters_by_client(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $client = Client::firstOrFail();
        $other = Client::whereKeyNot($client->id)->firstOrFail();
        $otherLoan = $other->loans()->firstOrFail();
        $this->get(route('reports.index', ['report_type' => 'portfolio', 'start_date' => today()->subYear()->format('Y-m-d'), 'end_date' => today()->format('Y-m-d'), 'client' => $client->id]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('Reports/Index')->where('type', 'portfolio')
                ->where('data.data.0.client_id', $client->id));
    }

    public function test_report_can_be_exported_as_utf8_csv(): void
    {
        $this->seed(ClientModuleSeeder::class);
        SystemSetting::create(['group' => 'brand', 'key' => 'system_name', 'value' => 'Financiera Segovia', 'type' => 'string']);
        $response = $this->get(route('reports.export', ['report_type' => 'portfolio', 'start_date' => today()->subYear()->format('Y-m-d'), 'end_date' => today()->format('Y-m-d')]))
            ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8')->assertDownload();
        $this->assertStringContainsString('Financiera Segovia', $response->streamedContent());
    }

    public function test_invalid_report_type_is_rejected(): void
    {
        $this->get(route('reports.index', ['report_type' => 'inventado']))->assertSessionHasErrors('report_type');
    }

    public function test_management_analytics_and_auditable_rows_use_the_filtered_dataset(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $client = Client::firstOrFail();

        $this->get(route('reports.index', [
            'report_type' => 'portfolio',
            'start_date' => today()->subYear()->format('Y-m-d'),
            'end_date' => today()->format('Y-m-d'),
            'client' => $client->id,
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Index')
            ->where('analytics.records', $client->loans()->count())
            ->has('analytics.trend')
            ->has('analytics.distribution')
            ->has('analytics.leaders')
            ->has('headings', 8)
            ->where('rows.0.id', $client->loans()->firstOrFail()->id)
            ->has('rows.0.cells', 8));
    }
}
