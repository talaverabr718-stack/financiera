# Colas y scheduler

El ejemplo configura `QUEUE_CONNECTION=database`. En desarrollo, `composer dev` ejecuta `php artisan queue:listen --tries=1`. En producción existe un ejemplo Supervisor en `deployment/supervisor-financiera.conf.example`; tras desplegar se ejecuta `php artisan queue:restart`.

El scheduler registra `delinquency:recalculate` todos los días a las 00:05 en `America/Managua`. Producción requiere cron por minuto: `php artisan schedule:run`. Revise logs Laravel y trabajos fallidos antes de reiniciar workers.
