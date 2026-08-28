<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountingDashboardController;
use App\Http\Controllers\AccountingReportController;
use App\Http\Controllers\AmortizationCalculatorController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CollaboratorController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\CollectionRouteController;
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
    Route::resource('clientes', ClientController::class)->middleware('module:clients')->parameters(['clientes' => 'client'])->names('clients');
    Route::post('/clientes/{client}/transferir', [ClientController::class, 'transfer'])->name('clients.transfer');
    Route::resource('colaboradores', CollaboratorController::class)->middleware('module:collaborators')->parameters(['colaboradores' => 'collaborator'])->names('collaborators');
    Route::get('/cartera', [LoanPortfolioController::class, 'index'])->middleware('module:loans')->name('loans.index');
    Route::get('/cartera/{loan}', [LoanPortfolioController::class, 'show'])->middleware('module:loans')->name('loans.show');
    Route::get('/historial-crediticio', [CreditHistoryController::class, 'index'])->middleware('module:credit_history')->name('credit-history.index');
    Route::get('/historial-crediticio/{client}', [CreditHistoryController::class, 'show'])->middleware('module:credit_history')->name('credit-history.show');
    Route::patch('/cartera/{loan}/estado', [LoanPortfolioController::class, 'updateStatus'])->middleware('module:loans')->name('loans.status');
    Route::post('/cartera/{loan}/mora/recalcular', [DelinquencyCaseController::class, 'recalculate'])->middleware('module:delinquency')->name('loans.delinquency.recalculate');
    Route::get('/mora', [DelinquencyCaseController::class, 'index'])->middleware('module:delinquency')->name('delinquency.index');
    Route::post('/mora/recalcular', [DelinquencyCaseController::class, 'recalculateAll'])->middleware('module:delinquency')->name('delinquency.recalculate');
    Route::post('/mora/{delinquency_case}/cancelar', [DelinquencyCaseController::class, 'cancel'])->middleware('module:delinquency')->name('delinquency.cancel');
    Route::post('/mora/{delinquency_case}/reactivar', [DelinquencyCaseController::class, 'reopen'])->middleware('module:delinquency')->name('delinquency.reopen');
    Route::get('/calculadora-amortizacion', AmortizationCalculatorController::class)->middleware('module:amortization')->name('amortization.index');
    Route::post('/calculadora-amortizacion', [AmortizationCalculatorController::class, 'calculate'])->middleware('module:amortization')->name('amortization.calculate');
    Route::get('/rutas', [CollectionRouteController::class, 'index'])->middleware('module:routes')->name('routes.index');
    Route::get('/rutas/crear', [CollectionRouteController::class, 'create'])->name('routes.create');
    Route::post('/rutas', [CollectionRouteController::class, 'store'])->name('routes.store');
    Route::get('/rutas/{collectionRoute}/editar', [CollectionRouteController::class, 'edit'])->name('routes.edit');
    Route::put('/rutas/{collectionRoute}', [CollectionRouteController::class, 'update'])->name('routes.update');
    Route::patch('/rutas/{collectionRoute}/estado', [CollectionRouteController::class, 'updateStatus'])->name('routes.status');
    Route::patch('/rutas/paradas/{stop}/visitada', [CollectionRouteController::class, 'markVisited'])->name('routes.stops.visit');
    Route::get('/cobranza', [CollectionController::class, 'index'])->middleware('module:collections')->name('collections.index');
    Route::post('/cobranza/paradas/{stop}', [CollectionController::class, 'store'])->name('collections.store');
    Route::prefix('contabilidad')->name('accounting.')->middleware('module:accounting')->group(function () {
        Route::get('/', AccountingDashboardController::class)->name('dashboard');
        Route::resource('cuentas', AccountController::class)->parameters(['cuentas' => 'account'])->except(['show', 'destroy'])->names('accounts');
        Route::get('/asientos', [JournalEntryController::class, 'index'])->name('entries.index');
        Route::get('/asientos/nuevo', [JournalEntryController::class, 'create'])->name('entries.create');
        Route::post('/asientos', [JournalEntryController::class, 'store'])->name('entries.store');
        Route::get('/asientos/{entry}', [JournalEntryController::class, 'show'])->name('entries.show');
        Route::post('/asientos/{entry}/contabilizar', [JournalEntryController::class, 'post'])->name('entries.post');
        Route::post('/asientos/{entry}/reversar', [JournalEntryController::class, 'reverse'])->name('entries.reverse');
        Route::get('/diario', [AccountingReportController::class, 'journal'])->name('journal');
        Route::get('/mayor', [AccountingReportController::class, 'ledger'])->name('ledger');
        Route::get('/balance-comprobacion', [AccountingReportController::class, 'trial'])->name('trial');
    });
    Route::get('/reportes', [ReportController::class, 'index'])->middleware('module:reports')->name('reports.index');
    Route::get('/reportes/exportar', [ReportController::class, 'export'])->name('reports.export');
    Route::prefix('configuracion')->name('settings.')->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('index');
        Route::get('/general', [SettingsController::class, 'general'])->name('general');
        Route::put('/general', [SettingsController::class, 'updateGeneral'])->name('general.update');
        Route::get('/financiera', [SettingsController::class, 'financial'])->name('financial');
        Route::get('/contabilidad', [SettingsController::class, 'accounting'])->name('accounting');
        Route::put('/contabilidad', [SettingsController::class, 'updateAccounting'])->name('accounting.update');
        Route::get('/consecutivos', [SettingsController::class, 'sequences'])->name('sequences');
        Route::put('/consecutivos', [SettingsController::class, 'updateSequences'])->name('sequences.update');
        Route::get('/modulos', [SettingsController::class, 'modules'])->name('modules');
        Route::put('/modulos', [SettingsController::class, 'updateModules'])->name('modules.update');
        Route::get('/permisos', [SettingsController::class, 'permissions'])->name('permissions');
        Route::put('/permisos', [SettingsController::class, 'updatePermissions'])->name('permissions.update');
        Route::get('/apariencia', [SettingsController::class, 'appearance'])->name('appearance');
        Route::put('/apariencia', [SettingsController::class, 'updateAppearance'])->name('appearance.update');
        Route::get('/marca', [SettingsController::class, 'brand'])->name('brand');
        Route::put('/marca', [SettingsController::class, 'updateBrand'])->name('brand.update');
    });
    Route::resource('solicitudes', CreditApplicationController::class)->middleware('module:applications')->parameters(['solicitudes' => 'application'])->except('destroy')->names('applications');
    Route::patch('/solicitudes/{application}/estado', [CreditApplicationController::class, 'status'])->name('applications.status');
    Route::post('/solicitudes/{application}/desembolsar', [LoanDisbursementController::class, 'store'])->name('applications.disburse');
    Route::patch('/garantias/{guarantee}/decision', [CreditGuarantorController::class, 'decision'])->name('guarantees.decision');
    Route::patch('/garantias/{guarantee}/liberar', [CreditGuarantorController::class, 'release'])->name('guarantees.release');
    Route::resource('productos-crediticios', CreditProductController::class)->parameters(['productos-crediticios' => 'product'])->only(['index', 'create', 'store', 'edit', 'update'])->names('products');

    Route::get('/{section}', function (string $section) {
        abort_unless(in_array($section, ['clientes', 'caja', 'contabilidad', 'reportes'], true), 404);

        return Inertia::render('Sections/Placeholder', ['section' => $section]);
    })->name('section');
});
