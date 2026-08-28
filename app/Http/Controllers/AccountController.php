<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccountRequest;
use App\Models\Account;
use Inertia\Inertia;

class AccountController extends Controller
{
    public function index()
    {
        return Inertia::render('Accounting/Accounts/Index', ['accounts' => Account::with('parent')->orderBy('code')->paginate(30), 'createUrl'=>route('accounting.accounts.create')]);
    }

    public function create()
    {
        return $this->form(new Account);
    }

    public function store(AccountRequest $request)
    {
        $this->save(new Account, $request->validated());

        return redirect()->route('accounting.accounts.index')->with('success', 'Cuenta creada.');
    }

    public function edit(Account $account)
    {
        return $this->form($account);
    }

    public function update(AccountRequest $request, Account $account)
    {
        $this->save($account, $request->validated());

        return redirect()->route('accounting.accounts.index')->with('success', 'Cuenta actualizada sin alterar movimientos.');
    }

    private function form(Account $account)
    {
        return Inertia::render('Accounting/Accounts/Form', ['account' => $account, 'parents' => Account::active()->whereKeyNot($account->id)->orderBy('code')->get(), 'types'=>Account::TYPES, 'editing'=>$account->exists, 'endpoints'=>['index'=>route('accounting.accounts.index'),'save'=>$account->exists?route('accounting.accounts.update',$account):route('accounting.accounts.store')]]);
    }

    private function save(Account $account, array $data): void
    {
        $parentId = $data['parent_id'] ?? null;
        $data['parent_id'] = $parentId;
        $data['nature'] = Account::NATURE_BY_TYPE[$data['type']];
        $data['level'] = $parentId ? (Account::find($parentId)?->level ?? 0) + 1 : 1;
        $data['is_postable'] = (bool) ($data['is_postable'] ?? false);
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $account->fill($data)->save();
    }
}
