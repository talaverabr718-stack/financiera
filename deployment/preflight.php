<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$errors = [];
$warnings = [];

$require = static function (bool $condition, string $message) use (&$errors): void {
    if (! $condition) {
        $errors[] = $message;
    }
};

$require($app->environment('production'), 'APP_ENV debe ser production.');
$require(config('app.debug') === false, 'APP_DEBUG debe ser false.');
$require(str_starts_with((string) config('app.url'), 'https://'), 'APP_URL debe usar HTTPS.');
$require(filled(config('app.key')), 'APP_KEY no puede estar vacío.');
$require(config('database.default') === 'mysql', 'DB_CONNECTION debe ser mysql.');
$require(filled(config('database.connections.mysql.password')), 'DB_PASSWORD no puede estar vacío.');
$require(config('session.driver') === 'database', 'SESSION_DRIVER debe ser database.');
$require(config('session.secure') === true, 'SESSION_SECURE_COOKIE debe ser true.');
$require(config('queue.default') !== 'sync', 'QUEUE_CONNECTION no puede ser sync.');
$require(! in_array(config('cache.default'), ['array', 'null'], true), 'CACHE_STORE debe ser persistente.');
$require(! in_array(config('mail.default'), ['array', 'log'], true), 'MAIL_MAILER debe entregar correo real.');
$require(filled(config('financial.interest_method')), 'Debe aprobarse FINANCIAL_INTEREST_METHOD.');
$require(config('financial.payment_allocation_order') !== [], 'Debe aprobarse FINANCIAL_PAYMENT_ORDER.');
$require(filled(config('financial.early_payment_strategy')), 'Debe aprobarse FINANCIAL_EARLY_PAYMENT_STRATEGY.');
$require(filled(config('financial.delinquency_method')), 'Debe aprobarse FINANCIAL_DELINQUENCY_METHOD.');

if (blank(config('services.google_maps.key'))) {
    $warnings[] = 'Google Maps no está configurado; el mapa operativo no estará disponible.';
}

foreach ([storage_path(), base_path('bootstrap/cache')] as $path) {
    $require(is_writable($path), "El proceso PHP no puede escribir en {$path}.");
}

try {
    DB::connection()->select('SELECT 1');
} catch (Throwable $exception) {
    $errors[] = 'No fue posible conectar con MySQL: '.$exception->getMessage();
}

foreach ($warnings as $warning) {
    fwrite(STDOUT, "ADVERTENCIA: {$warning}\n");
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, "ERROR: {$error}\n");
    }

    exit(1);
}

fwrite(STDOUT, "Preflight de producción aprobado.\n");
