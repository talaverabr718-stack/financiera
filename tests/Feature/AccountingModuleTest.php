<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_catalog_can_be_created_without_seeded_account_codes(): void
    {
        $this->post(route('accounting.accounts.store'), ['code'=>'A-TEST','name'=>'Cuenta aprobada','type'=>'asset_current','is_postable'=>1,'is_active'=>1])->assertRedirect(route('accounting.accounts.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('accounts', ['code'=>'A-TEST','nature'=>'debit','is_active'=>1]);
    }

    public function test_balanced_entry_can_be_posted_and_reversed_without_deleting_original(): void
    {
        $user=User::factory()->create();
        $cash=Account::create(['code'=>'A-1','name'=>'Activo prueba','type'=>'asset_current','nature'=>'debit','is_postable'=>true,'is_active'=>true]);
        $equity=Account::create(['code'=>'P-1','name'=>'Patrimonio prueba','type'=>'equity','nature'=>'credit','is_postable'=>true,'is_active'=>true]);
        $payload=['date'=>today()->format('Y-m-d'),'concept'=>'Apertura de prueba','lines'=>[['account_id'=>$cash->id,'debit'=>'1000.00','credit'=>'0'],['account_id'=>$equity->id,'debit'=>'0','credit'=>'1000.00']]];

        $this->actingAs($user)->post(route('accounting.entries.store'),$payload)->assertSessionHasNoErrors();
        $entry=JournalEntry::firstOrFail();
        $this->post(route('accounting.entries.post',$entry))->assertSessionHasNoErrors();
        $this->assertSame('posted',$entry->fresh()->status);

        $this->post(route('accounting.entries.reverse',$entry),['reason'=>'Corrección autorizada'])->assertSessionHasNoErrors();
        $this->assertSame('reversed',$entry->fresh()->status);
        $this->assertDatabaseHas('journal_entries',['reversal_of_id'=>$entry->id,'status'=>'posted','total_debit'=>'1000.00']);
        $this->assertDatabaseCount('journal_entries',2);
        $this->assertDatabaseCount('journal_entry_lines',4);
    }

    public function test_unbalanced_entry_is_rejected(): void
    {
        $user=User::factory()->create();
        $account=Account::create(['code'=>'A-1','name'=>'Cuenta','type'=>'asset_current','nature'=>'debit','is_postable'=>true,'is_active'=>true]);
        $this->actingAs($user)->post(route('accounting.entries.store'),['date'=>today()->format('Y-m-d'),'concept'=>'Desbalance','lines'=>[['account_id'=>$account->id,'debit'=>'100','credit'=>'0'],['account_id'=>$account->id,'debit'=>'0','credit'=>'50']]])->assertSessionHasErrors('lines');
        $this->assertDatabaseCount('journal_entries',0);
    }

    public function test_accounting_reports_render(): void
    {
        $this->get(route('accounting.dashboard'))->assertOk();
        $this->get(route('accounting.journal'))->assertOk();
        $this->get(route('accounting.ledger'))->assertOk();
        $this->get(route('accounting.trial'))->assertOk();
    }
}
