<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\SellerProfile;
use App\Models\SystemSetting;
use App\Services\FinancialReportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private FinancialReportService $reports) {}

    public function index(Request $request)
    {
        $filters = $request->validate(['report_type' => ['nullable', Rule::in(array_keys(FinancialReportService::TYPES))], 'start_date' => ['nullable', 'date'], 'end_date' => ['nullable', 'date', 'after_or_equal:start_date'], 'client' => ['nullable', 'integer', 'exists:clients,id'], 'seller' => ['nullable', 'integer', 'exists:seller_profiles,id'], 'status' => ['nullable', 'string', 'max:30']]);
        $type = $filters['report_type'] ?? 'portfolio';
        $from = $filters['start_date'] ?? today()->startOfMonth()->format('Y-m-d');
        $to = $filters['end_date'] ?? today()->format('Y-m-d');
        $query = $this->reports->query($type, $from, $to, $request);
        $summary = $this->reports->summary($type, clone $query);
        $data = $query->latest($this->dateColumn($type))->paginate(30)->withQueryString();
        $clients = Client::orderBy('full_name')->get(['id', 'full_name']);
        $sellers = SellerProfile::with('user')->where('status', 'active')->get();

        return view('reports.index', compact('type', 'from', 'to', 'summary', 'data', 'clients', 'sellers'));
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $request->validate(['report_type' => ['required', Rule::in(array_keys(FinancialReportService::TYPES))], 'start_date' => ['nullable', 'date'], 'end_date' => ['nullable', 'date', 'after_or_equal:start_date'], 'client' => ['nullable', 'integer', 'exists:clients,id'], 'seller' => ['nullable', 'integer', 'exists:seller_profiles,id'], 'status' => ['nullable', 'string', 'max:30']]);
        $type = $filters['report_type'];
        $from = $filters['start_date'] ?? today()->startOfMonth()->format('Y-m-d');
        $to = $filters['end_date'] ?? today()->format('Y-m-d');
        $query = $this->reports->query($type, $from, $to, $request);
        $filename = "reporte_{$type}_".now()->format('Ymd_His').'.csv';
        $systemName = SystemSetting::where('key', 'system_name')->value('value') ?: 'Centro Financiero 360';

        return response()->streamDownload(function () use ($type, $from, $to, $query, $systemName) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, [$systemName]);
            fputcsv($out, ['Reporte', FinancialReportService::TYPES[$type]]);
            fputcsv($out, ['Período', "$from al $to"]);
            fputcsv($out, []);
            fputcsv($out, $this->reports->headings($type));
            foreach ($query->cursor() as $item) {
                fputcsv($out, $this->reports->row($type, $item));
            }fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function dateColumn(string $type): string
    {
        return ['portfolio' => 'disbursed_at', 'collections' => 'recorded_at', 'applications' => 'created_at', 'disbursements' => 'disbursed_at', 'routes' => 'scheduled_date', 'accounting' => 'date'][$type];
    }
}
