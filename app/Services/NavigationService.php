<?php

namespace App\Services;

use App\Models\SystemModule;
use App\Models\User;

class NavigationService
{
    public function forUser(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $modules = SystemModule::with(['users' => fn ($query) => $query->whereKey($user->id)])
            ->get()->keyBy('key');
        $allowed = function (string $key) use ($modules): bool {
            $module = $modules->get($key);
            $grant = $module?->users->first()?->pivot;

            return ! $module || ($module->is_enabled && $module->is_visible && (! $grant || $grant->can_view));
        };

        return collect([
            ['group' => 'Resumen', 'items' => [
                ['key' => 'dashboard', 'label' => 'Panel general', 'url' => route('dashboard'), 'inertia' => true],
            ]],
            ['group' => 'Crédito', 'items' => [
                ['key' => 'clients', 'label' => 'Clientes', 'url' => route('clients.index'), 'inertia' => true],
                ['key' => 'applications', 'label' => 'Solicitudes', 'url' => route('applications.index'), 'inertia' => true],
                ['key' => 'loans', 'label' => 'Cartera', 'url' => route('loans.index'), 'inertia' => true],
                ['key' => 'amortization', 'label' => 'Calculadora', 'url' => route('amortization.index'), 'inertia' => true],
            ]],
            ['group' => 'Operación', 'items' => [
                ['key' => 'routes', 'label' => 'Rutas', 'url' => route('routes.index')],
                ['key' => 'collections', 'label' => 'Cobranza', 'url' => route('collections.index')],
                ['key' => 'cash', 'label' => 'Caja', 'url' => route('section', 'caja')],
            ]],
            ['group' => 'Administración', 'items' => [
                ['key' => 'collaborators', 'label' => 'Colaboradores', 'url' => route('collaborators.index')],
                ['key' => 'accounting', 'label' => 'Contabilidad', 'url' => route('accounting.dashboard')],
                ['key' => 'reports', 'label' => 'Reportes', 'url' => route('reports.index')],
                ['key' => 'settings', 'label' => 'Configuración', 'url' => route('settings.index')],
            ]],
        ])->map(fn (array $group) => [
            ...$group,
            'items' => collect($group['items'])->filter(fn (array $item) => $allowed($item['key']))->values()->all(),
        ])->filter(fn (array $group) => count($group['items']))->values()->all();
    }
}
