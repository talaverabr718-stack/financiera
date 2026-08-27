<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAccountingSettingsRequest;
use App\Http\Requests\UpdateAppearanceSettingsRequest;
use App\Http\Requests\UpdateBrandSettingsRequest;
use App\Http\Requests\UpdateGeneralSettingsRequest;
use App\Http\Requests\UpdateModuleSettingsRequest;
use App\Http\Requests\UpdatePermissionSettingsRequest;
use App\Http\Requests\UpdateSequenceSettingsRequest;
use App\Models\Account;
use App\Models\CreditProduct;
use App\Models\SystemModule;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\SystemSettingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function __construct(private SystemSettingService $settings) {}

    public function index()
    {
        $general = $this->settings->group('general');
        $accounting = $this->settings->group('accounting');
        $checks = [
            'brand' => SystemSetting::where('key', 'system_name')->whereNotNull('value')->exists(),
            'modules' => SystemModule::where('is_enabled', false)->doesntExist(),
            'permissions' => DB::table('system_module_user')->exists(),
            'appearance' => SystemSetting::where('group', 'appearance')->exists(),
            'general' => filled($general['institution_name'] ?? null),
            'financial' => CreditProduct::where('is_active', true)->exists(),
            'accounting' => count(array_filter($accounting)) === 5,
            'sequences' => DB::table('document_sequences')->exists(),
        ];

        return view('settings.index', compact('checks'));
    }

    public function modules()
    {
        return view('settings.modules', ['modules' => SystemModule::orderBy('sort_order')->get()]);
    }

    public function updateModules(UpdateModuleSettingsRequest $request)
    {
        DB::transaction(function () use ($request): void {
            foreach (SystemModule::lockForUpdate()->get() as $module) {
                $row = $request->validated('modules')[$module->id] ?? [];
                $module->update(['is_enabled' => (bool) ($row['enabled'] ?? false), 'is_visible' => (bool) ($row['visible'] ?? false), 'sort_order' => $row['sort_order'] ?? $module->sort_order]);
            }
        });

        return back()->with('success', 'Configuración de módulos aplicada.');
    }

    public function permissions()
    {
        $users = User::orderBy('name')->get(['id', 'name', 'email']);
        $modules = SystemModule::orderBy('sort_order')->get(['id', 'key', 'name', 'is_enabled']);
        $stored = DB::table('system_module_user')->get()->keyBy(fn ($row) => $row->user_id.'-'.$row->system_module_id);
        $permissions = $users->mapWithKeys(fn (User $user) => [(string) $user->id => $modules->mapWithKeys(function (SystemModule $module) use ($stored, $user) {
            $grant = $stored->get($user->id.'-'.$module->id);

            return [(string) $module->id => ['view' => $grant ? (bool) $grant->can_view : true, 'manage' => $grant ? (bool) $grant->can_manage : false]];
        })->all()])->all();

        return Inertia::render('Settings/Permissions', [
            'users' => $users,
            'modules' => $modules,
            'permissions' => $permissions,
            'endpoints' => ['update' => route('settings.permissions.update')],
            'tabs' => collect(['index' => 'Resumen', 'brand' => 'Marca', 'modules' => 'Módulos', 'permissions' => 'Permisos', 'appearance' => 'Apariencia', 'general' => 'Institución', 'financial' => 'Financiera', 'accounting' => 'Contabilidad', 'sequences' => 'Consecutivos'])
                ->map(fn ($label, $name) => ['label' => $label, 'url' => route('settings.'.$name), 'active' => $name === 'permissions'])->values(),
        ]);
    }

    public function updatePermissions(UpdatePermissionSettingsRequest $request)
    {
        DB::transaction(function () use ($request): void {
            $values = $request->validated('permissions', []);
            foreach (User::get() as $user) {
                foreach (SystemModule::get() as $module) {
                    $row = $values[$user->id][$module->id] ?? [];
                    DB::table('system_module_user')->updateOrInsert(['user_id' => $user->id, 'system_module_id' => $module->id], ['can_view' => (bool) ($row['view'] ?? false), 'can_manage' => (bool) ($row['manage'] ?? false), 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        });

        return back()->with('success', 'Permisos por usuario actualizados.');
    }

    public function appearance()
    {
        return view('settings.appearance', ['settings' => $this->settings->group('appearance')]);
    }

    public function updateAppearance(UpdateAppearanceSettingsRequest $request)
    {
        $this->settings->save('appearance', $request->validated(), auth()->id());

        return back()->with('success', 'Apariencia aplicada a todo el sistema.');
    }

    public function brand()
    {
        return view('settings.brand', ['settings' => $this->settings->group('brand')]);
    }

    public function updateBrand(UpdateBrandSettingsRequest $request)
    {
        $current = $this->settings->group('brand');
        $values = $request->safe()->except(['logo', 'remove_logo']);
        if ($request->hasFile('logo')) {
            $values['logo_path'] = $request->file('logo')->store('system-branding');
            if (filled($current['logo_path'] ?? null)) {
                Storage::delete($current['logo_path']);
            }
        } elseif ($request->boolean('remove_logo')) {
            if (filled($current['logo_path'] ?? null)) {
                Storage::delete($current['logo_path']);
            } $values['logo_path'] = null;
        }
        $this->settings->save('brand', $values, auth()->id());

        return back()->with('success', 'Nombre y logotipo del sistema actualizados.');
    }

    public function logo()
    {
        $path = $this->settings->group('brand')['logo_path'] ?? null;
        abort_unless($path && Storage::exists($path), 404);

        return Storage::response($path, null, ['Cache-Control' => 'no-store, no-cache, must-revalidate', 'Pragma' => 'no-cache', 'Expires' => '0']);
    }

    public function general()
    {
        return view('settings.general', ['settings' => $this->settings->group('general')]);
    }

    public function updateGeneral(UpdateGeneralSettingsRequest $request)
    {
        $this->settings->save('general', $request->validated() + ['base_currency' => 'NIO', 'locale' => 'es'], auth()->id());

        return back()->with('success', 'Configuración institucional actualizada.');
    }

    public function financial()
    {
        return view('settings.financial', ['products' => CreditProduct::orderBy('name')->get(), 'criticalSettings' => DB::table('financial_settings')->orderBy('group')->orderBy('label')->get()]);
    }

    public function accounting()
    {
        return view('settings.accounting', ['settings' => $this->settings->group('accounting'), 'accounts' => Account::active()->postable()->orderBy('code')->get()]);
    }

    public function updateAccounting(UpdateAccountingSettingsRequest $request)
    {
        $this->settings->save('accounting', $request->validated(), auth()->id());

        return back()->with('success', 'Integración contable actualizada.');
    }

    public function sequences()
    {
        return view('settings.sequences', ['sequences' => DB::table('document_sequences')->orderBy('key')->get()]);
    }

    public function updateSequences(UpdateSequenceSettingsRequest $request)
    {
        DB::transaction(function () use ($request): void {
            foreach ($request->validated('sequences') as $key => $values) {
                DB::table('document_sequences')->where('key', $key)->lockForUpdate()->update(['prefix' => $values['prefix'], 'padding' => $values['padding'], 'updated_at' => now()]);
            }
        });

        return back()->with('success', 'Formatos de consecutivos actualizados sin alterar el siguiente número.');
    }
}
