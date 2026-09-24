<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountingDashboardController;
use App\Http\Controllers\AccountingPeriodController;
use App\Http\Controllers\AccountingReportController;
use App\Http\Controllers\AmortizationCalculatorController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CollaboratorController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\CollectionRouteController;
use App\Http\Controllers\CostCenterController;
use App\Http\Controllers\CreditApplicationController;
use App\Http\Controllers\CreditGuarantorController;
use App\Http\Controllers\CreditHistoryController;
use App\Http\Controllers\CreditProductController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DelinquencyCaseController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\LoanDisbursementController;
use App\Http\Controllers\LoanPortfolioController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SettingsPermissionController;
use App\Http\Controllers\SettingsUserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->group(function () {
    Route::get('/ingresar', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/ingresar', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/', DashboardController::class)->middleware('module:dashboard')->name('dashboard');
    Route::get('/buscar', GlobalSearchController::class)->name('search');
    Route::get('/marca/logo', [SettingsController::class, 'logo'])->name('settings.logo');
    Route::post('/salir', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/clientes/opciones/vendedores', [ClientController::class, 'sellerOptions'])->middleware('module:clients')->name('clients.seller-options');
    Route::resource('clientes', ClientController::class)
        ->middleware('module:clients')
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'module:clients,manage')
        ->parameters(['clientes' => 'client'])
        ->names('clients');
    Route::post('/clientes/{client}/transferir', [ClientController::class, 'transfer'])->middleware('module:clients,manage')->name('clients.transfer');
    Route::resource('colaboradores', CollaboratorController::class)->middleware('module:collaborators')->parameters(['colaboradores' => 'collaborator'])->names('collaborators');
    Route::get('/cartera', [LoanPortfolioController::class, 'index'])->middleware('module:loans')->name('loans.index');
    Route::get('/cartera/{loan}', [LoanPortfolioController::class, 'show'])->middleware('module:loans')->name('loans.show');
    Route::get('/historial-crediticio', [CreditHistoryController::class, 'index'])->middleware('module:credit_history')->name('credit-history.index');
    Route::get('/historial-crediticio/{client}', [CreditHistoryController::class, 'show'])->middleware('module:credit_history')->name('credit-history.show');
    Route::patch('/cartera/{loan}/estado', [LoanPortfolioController::class, 'updateStatus'])->middleware('module:loans,manage')->name('loans.status');
    Route::post('/cartera/{loan}/mora/recalcular', [DelinquencyCaseController::class, 'recalculate'])->middleware('module:delinquency,manage')->name('loans.delinquency.recalculate');
    Route::get('/mora', [DelinquencyCaseController::class, 'index'])->middleware('module:delinquency')->name('delinquency.index');
    Route::post('/mora/recalcular', [DelinquencyCaseController::class, 'recalculateAll'])->middleware('module:delinquency,manage')->name('delinquency.recalculate');
    Route::post('/mora/{delinquency_case}/cancelar', [DelinquencyCaseController::class, 'cancel'])->middleware('module:delinquency,full')->name('delinquency.cancel');
    Route::post('/mora/{delinquency_case}/reactivar', [DelinquencyCaseController::class, 'reopen'])->middleware('module:delinquency,full')->name('delinquency.reopen');
    Route::get('/calculadora-amortizacion', AmortizationCalculatorController::class)->middleware('module:amortization')->name('amortization.index');
    Route::post('/calculadora-amortizacion', [AmortizationCalculatorController::class, 'calculate'])->middleware('module:amortization')->name('amortization.calculate');
    Route::get('/rutas', [CollectionRouteController::class, 'index'])->middleware('module:routes')->name('routes.index');
    Route::get('/rutas/crear', [CollectionRouteController::class, 'create'])->middleware('module:routes,manage')->name('routes.create');
    Route::post('/rutas', [CollectionRouteController::class, 'store'])->middleware('module:routes,manage')->name('routes.store');
    Route::get('/rutas/{collectionRoute}/editar', [CollectionRouteController::class, 'edit'])->middleware('module:routes,manage')->name('routes.edit');
    Route::put('/rutas/{collectionRoute}', [CollectionRouteController::class, 'update'])->middleware('module:routes,manage')->name('routes.update');
    Route::patch('/rutas/{collectionRoute}/estado', [CollectionRouteController::class, 'updateStatus'])->middleware('module:routes,manage')->name('routes.status');
    Route::patch('/rutas/paradas/{stop}/visitada', [CollectionRouteController::class, 'markVisited'])->middleware('module:routes,manage')->name('routes.stops.visit');
    Route::get('/cobranza', [CollectionController::class, 'index'])->middleware('module:collections')->name('collections.index');
    Route::post('/cobranza/paradas/{stop}', [CollectionController::class, 'store'])
        ->middleware('module:collections,manage')->name('collections.store');
    Route::post('/cobranza/paradas/{stop}/autorizar-segundo-pago', [CollectionController::class, 'authorizeSecondPayment'])
        ->middleware('module:collections,full')->name('collections.authorize-second-payment');
    Route::post('/cobranza/gestiones/{record}/autorizar-correccion', [CollectionController::class, 'authorizeCorrection'])
        ->middleware('module:collections,full')->name('collections.authorize-correction');
    Route::post('/cobranza/gestiones/{record}/corregir-monto', [CollectionController::class, 'correctAmount'])
        ->middleware('module:collections,manage')->name('collections.correct-amount');
    Route::prefix('contabilidad')->name('accounting.')->middleware('module:accounting')->group(function () {
        Route::get('/', AccountingDashboardController::class)->name('dashboard');
        Route::resource('cuentas', AccountController::class)->parameters(['cuentas' => 'account'])->except(['show', 'destroy'])->names('accounts')
            ->middlewareFor(['create', 'store', 'edit', 'update'], 'module:accounting,manage');
        Route::get('/asientos', [JournalEntryController::class, 'index'])->name('entries.index');
        Route::get('/asientos/nuevo', [JournalEntryController::class, 'create'])->name('entries.create');
        Route::post('/asientos', [JournalEntryController::class, 'store'])->middleware('module:accounting,manage')->name('entries.store');
        Route::get('/asientos/{entry}', [JournalEntryController::class, 'show'])->name('entries.show');
        Route::post('/asientos/{entry}/contabilizar', [JournalEntryController::class, 'post'])->middleware('module:accounting,full')->name('entries.post');
        Route::post('/asientos/{entry}/reversar', [JournalEntryController::class, 'reverse'])->middleware('module:accounting,full')->name('entries.reverse');
        Route::get('/diario', [AccountingReportController::class, 'journal'])->name('journal');
        Route::get('/mayor', [AccountingReportController::class, 'ledger'])->name('ledger');
        Route::get('/balance-comprobacion', [AccountingReportController::class, 'trial'])->name('trial');
        Route::get('/balance-general', [AccountingReportController::class, 'balanceSheet'])->name('balance-sheet');
        Route::get('/estado-resultados', [AccountingReportController::class, 'incomeStatement'])->name('income-statement');
        Route::get('/periodos', [AccountingPeriodController::class, 'index'])->name('periods.index');
        Route::post('/periodos/{period}/cerrar', [AccountingPeriodController::class, 'close'])->middleware('module:accounting,full')->name('periods.close');
        Route::post('/periodos/{period}/reabrir', [AccountingPeriodController::class, 'reopen'])->middleware('module:accounting,full')->name('periods.reopen');
        Route::get('/centros-de-costo', [CostCenterController::class, 'index'])->name('cost-centers.index');
        Route::post('/centros-de-costo', [CostCenterController::class, 'store'])->middleware('module:accounting,manage')->name('cost-centers.store');
        Route::put('/centros-de-costo/{costCenter}', [CostCenterController::class, 'update'])->middleware('module:accounting,manage')->name('cost-centers.update');
    });
    Route::get('/reportes', [ReportController::class, 'index'])->middleware('module:reports')->name('reports.index');
    Route::get('/reportes/exportar', [ReportController::class, 'export'])->middleware('module:reports')->name('reports.export');
    Route::prefix('configuracion')->name('settings.')->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->middleware('module:settings')->name('index');
        Route::get('/general', [SettingsController::class, 'general'])->middleware('module:settings')->name('general');
        Route::put('/general', [SettingsController::class, 'updateGeneral'])->middleware('module:settings,full')->name('general.update');
        Route::get('/financiera', [SettingsController::class, 'financial'])->middleware('module:settings')->name('financial');
        Route::get('/contabilidad', [SettingsController::class, 'accounting'])->middleware('module:settings')->name('accounting');
        Route::put('/contabilidad', [SettingsController::class, 'updateAccounting'])->middleware('module:settings,full')->name('accounting.update');
        Route::get('/consecutivos', [SettingsController::class, 'sequences'])->middleware('module:settings')->name('sequences');
        Route::put('/consecutivos', [SettingsController::class, 'updateSequences'])->middleware('module:settings,full')->name('sequences.update');
        Route::get('/modulos', [SettingsController::class, 'modules'])->middleware('module:settings')->name('modules');
        Route::put('/modulos', [SettingsController::class, 'updateModules'])->middleware('module:settings,full')->name('modules.update');
        Route::get('/permisos', [SettingsPermissionController::class, 'index'])->middleware('module:settings')->name('permissions');
        Route::post('/permisos/roles', [SettingsPermissionController::class, 'storeRole'])->middleware('module:settings,full')->name('permissions.roles.store');
        Route::put('/permisos/roles/{role}', [SettingsPermissionController::class, 'updateRole'])->middleware('module:settings,full')->name('permissions.roles.update');
        Route::put('/permisos/usuarios/{user}', [SettingsPermissionController::class, 'updateUser'])->middleware('module:settings,full')->name('permissions.users.update');
        Route::get('/usuarios', [SettingsUserController::class, 'index'])->middleware('module:settings,full')->name('users');
        Route::post('/usuarios', [SettingsUserController::class, 'store'])->middleware('module:settings,full')->name('users.store');
        Route::put('/usuarios/{user}', [SettingsUserController::class, 'update'])->middleware('module:settings,full')->name('users.update');
        Route::patch('/usuarios/{user}/estado', [SettingsUserController::class, 'status'])->middleware('module:settings,full')->name('users.status');
        Route::get('/apariencia', [SettingsController::class, 'appearance'])->name('appearance');
        Route::put('/apariencia', [SettingsController::class, 'updateAppearance'])->name('appearance.update');
        Route::get('/marca', [SettingsController::class, 'brand'])->middleware('module:settings')->name('brand');
        Route::put('/marca', [SettingsController::class, 'updateBrand'])->middleware('module:settings,full')->name('brand.update');
    });
    Route::resource('solicitudes', CreditApplicationController::class)->middleware('module:applications')->parameters(['solicitudes' => 'application'])->except('destroy')->names('applications')
        ->middlewareFor(['create', 'store', 'edit', 'update'], 'module:applications,manage');
    Route::patch('/solicitudes/{application}/estado', [CreditApplicationController::class, 'status'])->middleware('module:applications,full')->name('applications.status');
    Route::post('/solicitudes/{application}/desembolsar', [LoanDisbursementController::class, 'store'])->middleware('module:applications,full')->name('applications.disburse');
    Route::patch('/garantias/{guarantee}/decision', [CreditGuarantorController::class, 'decision'])->middleware('module:applications,full')->name('guarantees.decision');
    Route::patch('/garantias/{guarantee}/liberar', [CreditGuarantorController::class, 'release'])->middleware('module:applications,full')->name('guarantees.release');
    Route::resource('productos-crediticios', CreditProductController::class)->parameters(['productos-crediticios' => 'product'])->only(['index', 'create', 'store', 'edit', 'update'])->names('products')->middleware('module:settings,full');

    Route::get('/{section}', function (string $section) {
        abort_unless(in_array($section, ['clientes', 'caja', 'contabilidad', 'reportes'], true), 404);

        return Inertia::render('Sections/Placeholder', ['section' => $section]);
    })->name('section');
});
