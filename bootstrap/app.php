<?php

use App\Http\Middleware\EnsureModuleAccess;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\AttachRequestContext;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Coolify terminates TLS at its reverse proxy. Trust its forwarded
        // headers so Laravel generates HTTPS asset, route, and redirect URLs.
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [AttachRequestContext::class, HandleInertiaRequests::class]);
        $middleware->append(SecurityHeaders::class);
        $middleware->alias(['module' => EnsureModuleAccess::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->respond(function (Response $response) {
            if (app()->environment(['local', 'testing'])) {
                return $response;
            }

            $status = $response->getStatusCode();
            $requestId = request()->attributes->get('request_id');

            if ($status === 419) {
                return back()->with('error', 'La sesión venció por seguridad. Revisa los datos e intenta nuevamente.');
            }

            if (in_array($status, [403, 404, 500, 503], true)) {
                if (request()->expectsJson()) {
                    return response()->json([
                        'message' => match ($status) {
                            403 => 'No tienes permiso para realizar esta acción.',
                            404 => 'No encontramos el recurso solicitado.',
                            503 => 'El servicio no está disponible temporalmente.',
                            default => 'No fue posible completar la operación.',
                        },
                        'error_id' => $requestId,
                    ], $status)->header('X-Request-ID', (string) $requestId);
                }

                return Inertia::render('Errors/HttpError', [
                    'status' => $status,
                    'requestId' => $requestId,
                    'homeUrl' => route('dashboard'),
                ])->toResponse(request())->setStatusCode($status);
            }

            return $response;
        });
    })->create();
