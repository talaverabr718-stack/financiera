<?php

namespace Tests\Feature;

use App\Services\FinancialReportService;
use Database\Seeders\ClientModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_financial_report_tabs_render_real_data(): void
    {
        $this->seed(ClientModuleSeeder::class);
        foreach (array_keys(FinancialReportService::TYPES) as $type) {
            $this->get(route('reports.index', ['report_type'=>$type,'start_date'=>today()->subYear()->format('Y-m-d'),'end_date'=>today()->format('Y-m-d')]))
                ->assertOk()->assertSee(FinancialReportService::TYPES[$type]);
        }
    }

    public function test_portfolio_report_filters_by_client(): void
    {
        $this->seed(ClientModuleSeeder::class);
        $client=\App\Models\Client::firstOrFail();
        $other=\App\Models\Client::whereKeyNot($client->id)->firstOrFail();
        $otherLoan=$other->loans()->firstOrFail();
        $this->get(route('reports.index',['report_type'=>'portfolio','start_date'=>today()->subYear()->format('Y-m-d'),'end_date'=>today()->format('Y-m-d'),'client'=>$client->id]))
            ->assertOk()->assertSee($client->full_name)->assertDontSee($otherLoan->number);
    }

    public function test_report_can_be_exported_as_utf8_csv(): void
    {
        $this->seed(ClientModuleSeeder::class);
        \App\Models\SystemSetting::create(['group'=>'brand','key'=>'system_name','value'=>'Financiera Segovia','type'=>'string']);
        $response=$this->get(route('reports.export',['report_type'=>'portfolio','start_date'=>today()->subYear()->format('Y-m-d'),'end_date'=>today()->format('Y-m-d')]))
            ->assertOk()->assertHeader('content-type','text/csv; charset=UTF-8')->assertDownload();
        $this->assertStringContainsString('Financiera Segovia',$response->streamedContent());
    }

    public function test_invalid_report_type_is_rejected(): void
    {
        $this->get(route('reports.index',['report_type'=>'inventado']))->assertSessionHasErrors('report_type');
    }
}
