<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FinancialFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_financial_operations_have_immutable_traceability_fields(): void
    {
        $this->assertTrue(Schema::hasColumns('payments', [
            'idempotency_key', 'receipt_number', 'previous_balance', 'new_balance',
            'status', 'collector_id', 'created_by',
        ]));
        $this->assertTrue(Schema::hasColumns('payment_reversals', [
            'payment_id', 'number', 'reason', 'authorized_by', 'reversed_at',
        ]));
    }

    public function test_critical_financial_rules_remain_unconfigured_until_business_approval(): void
    {
        $this->assertNull(config('financial.interest_method'));
        $this->assertSame([], config('financial.payment_allocation_order'));
        $this->assertNull(config('financial.early_payment_strategy'));
        $this->assertNull(config('financial.delinquency_method'));
    }
}
