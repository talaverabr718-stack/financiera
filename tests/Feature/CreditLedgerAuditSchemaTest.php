<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class CreditLedgerAuditSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_credit_balances_are_separated_by_financial_component(): void
    {
        $this->assertTrue(Schema::hasColumns('loans', [
            'principal_balance', 'interest_balance', 'fee_balance', 'delinquency_balance',
            'maturity_date', 'closed_at', 'restructured_from_id',
        ]));

        $this->assertTrue(Schema::hasColumns('loan_installments', [
            'principal_paid', 'interest_paid', 'fees_paid', 'delinquency_paid',
        ]));
    }

    public function test_delinquency_has_an_immutable_ledger_structure(): void
    {
        $this->assertTrue(Schema::hasColumns('delinquency_accruals', [
            'idempotency_key', 'loan_id', 'installment_id', 'accrual_date',
            'base_amount', 'rate', 'method', 'days_overdue', 'amount',
            'policy_snapshot', 'status', 'reversal_of_id', 'created_by',
        ]));
    }

    public function test_audit_events_capture_actor_context_changes_and_hash_chain(): void
    {
        $this->assertTrue(Schema::hasColumns('audit_events', [
            'event_uuid', 'auditable_type', 'auditable_id', 'action', 'actor_id',
            'occurred_at', 'before_values', 'after_values', 'reason', 'request_id',
            'ip_address', 'user_agent', 'metadata', 'previous_hash', 'event_hash',
        ]));
    }

    public function test_audit_service_builds_a_tamper_evident_chain(): void
    {
        $actor = User::factory()->create();
        $audit = app(AuditService::class);

        $first = $audit->record($actor, 'created', $actor->id, [], ['name' => $actor->name]);
        $second = $audit->record($actor, 'reviewed', $actor->id, [], [], metadata: ['source' => 'test']);

        $this->assertNull($first->previous_hash);
        $this->assertSame($first->event_hash, $second->previous_hash);
        $this->assertSame(64, strlen($first->event_hash));
        $this->assertCount(2, AuditEvent::all());
    }

    public function test_audit_events_cannot_be_modified_through_the_model(): void
    {
        $actor = User::factory()->create();
        $event = app(AuditService::class)->record($actor, 'created', $actor->id);

        $this->expectException(LogicException::class);
        $event->update(['reason' => 'altered']);
    }
}
