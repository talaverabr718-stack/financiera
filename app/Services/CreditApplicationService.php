<?php

namespace App\Services;

use App\Models\Client;
use App\Models\CreditApplication;
use App\Models\CreditProduct;
use App\Models\Guarantor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreditApplicationService
{
    public function __construct(private DocumentSequenceService $sequences, private AmortizationCalculator $calculator) {}

    public function create(array $data): CreditApplication
    {
        return DB::transaction(function () use ($data) {
            $application = CreditApplication::create(Arr::except($this->withProductTerms($data), ['guarantors']) + [
                'number' => $this->sequences->next('credit_application', 'SOL-'),
                'economic_snapshot' => $this->clientSnapshot((int) $data['client_id']),
            ]);
            $this->recordGuarantees($application, $data['guarantors'] ?? []);

            return $application;
        });
    }

    public function update(CreditApplication $application, array $data): CreditApplication
    {
        return DB::transaction(function () use ($application, $data) {
            $application = CreditApplication::lockForUpdate()->findOrFail($application->id);
            $application->update(Arr::except($this->withProductTerms($data), ['guarantors']));
            $this->recordGuarantees($application, $data['guarantors'] ?? []);

            return $application->fresh();
        });
    }

    private function withProductTerms(array $data): array
    {
        $product = CreditProduct::query()->find($data['credit_product_id'] ?? null);
        if (! $product) {
            return $data;
        }

        $data['interest_method'] = ! empty($data['interest_method']) ? $data['interest_method'] : $product->default_interest_method;
        $data['administrative_fee'] = array_key_exists('administrative_fee', $data) && $data['administrative_fee'] !== null && $data['administrative_fee'] !== ''
            ? $data['administrative_fee']
            : ($product->default_administrative_fee ?? '0.00');
        $data['interest_rate'] = ! empty($data['interest_rate']) ? $data['interest_rate'] : $product->default_interest_rate;

        return $this->withProjectedPayment($data);
    }

    private function withProjectedPayment(array $data): array
    {
        $term = (int) ($data['term'] ?? 0);
        $frequency = $data['payment_frequency'] ?? null;
        $principal = $data['requested_amount'] ?? null;
        if ($term < 1 || $principal === null || ! isset(AmortizationCalculator::FREQUENCIES[$frequency])) {
            return $data;
        }

        $schedule = $this->calculator->calculate([
            'principal' => $principal,
            'annual_rate' => $data['interest_rate'] ?? 0,
            'periods' => $term,
            'method' => $this->calculator->resolveCalculatorMethod($data['interest_method'] ?? null),
            'frequency' => $frequency,
            'first_payment_date' => $data['applied_on'] ?? now()->toDateString(),
        ]);
        $data['installment_amount'] = $schedule['rows'][0]['payment'];

        return $data;
    }

    private function recordGuarantees(CreditApplication $application, array $rows): void
    {
        foreach ($rows as $row) {
            if (empty($row['guarantor_id']) && empty($row['full_name'])) {
                continue;
            }
            $guarantor = ! empty($row['guarantor_id'])
                ? Guarantor::lockForUpdate()->findOrFail($row['guarantor_id'])
                : Guarantor::create(Arr::only($row, ['full_name', 'identity_number', 'phone', 'email', 'address']));

            $guarantor->update(array_filter(Arr::only($row, ['full_name', 'identity_number', 'phone', 'email', 'address']), fn ($value) => $value !== null && $value !== ''));
            $guarantee = $application->guarantees()->firstOrCreate(['guarantor_id' => $guarantor->id], [
                'guaranteed_amount' => $row['guaranteed_amount'], 'guarantee_type' => $row['guarantee_type'],
                'relationship' => $row['relationship'], 'status' => 'proposed',
            ]);
            $guarantee->update(Arr::only($row, ['guaranteed_amount', 'guarantee_type', 'relationship', 'accepted_at']));
            $guarantee->evaluations()->create([
                'occupation' => $row['occupation'] ?? null, 'workplace' => $row['workplace'] ?? null,
                'workplace_address' => $row['workplace_address'] ?? null, 'monthly_income' => $row['monthly_income'],
                'other_income' => $row['other_income'] ?? 0, 'monthly_expenses' => $row['monthly_expenses'],
                'assets_snapshot' => empty($row['assets']) ? [] : ['description' => $row['assets']],
                'has_overdue_obligations' => (bool) ($row['has_overdue_obligations'] ?? false),
                'notes' => $row['evaluation_notes'] ?? null, 'evaluated_by' => auth()->id(), 'evaluated_at' => now(),
            ]);
            $this->storeDocument($guarantee, $row['identity_document'] ?? null, 'identity');
            $this->storeDocument($guarantee, $row['signed_document'] ?? null, 'signed_guarantee');
        }
    }

    private function storeDocument($guarantee, mixed $file, string $type): void
    {
        if (! $file instanceof UploadedFile) {
            return;
        }
        $path = $file->store('guarantor-documents');
        $document = $guarantee->documents()->create([
            'guarantor_id' => $guarantee->guarantor_id, 'type' => $type, 'original_name' => $file->getClientOriginalName(),
            'path' => $path, 'mime_type' => $file->getMimeType(), 'size' => $file->getSize(), 'uploaded_by' => auth()->id(),
        ]);
        if ($type === 'signed_guarantee') {
            $guarantee->update(['signed_document_path' => $document->path]);
        }
    }

    private function clientSnapshot(int $clientId): array
    {
        $client = Client::findOrFail($clientId);

        return ['income' => $client->estimated_income, 'expenses' => $client->estimated_expenses, 'activity' => $client->economic_activity, 'captured_at' => now()->toIso8601String()];
    }
}
