# Entornos

## Desarrollo

Usa `.env.example`; habilita depuración y permite `composer dev`. Debe usar una base de datos propia del desarrollador.

## Testing

PHPUnit está configurado para SQLite. MySQL 8.4 existe en la definición Docker, pero la ejecución completa de pruebas contra una base MySQL aislada debe validarse por separado antes de considerarla un procedimiento operativo.

## Preview

`compose.preview.yaml` publica Nginx en el puerto 8081 y configura SQLite. Es útil para revisión local; no debe presentarse como producción endurecida.

## Producción

Use `.env.production.example` y `deployment/deploy.sh`. Requiere secretos únicos, HTTPS, MySQL exclusivo, workers, cron para `schedule:run`, respaldos y validación de `/up`. Los reinicios normales no deben ejecutar seeders ni crear usuarios.

Nunca reutilice base de datos, claves ni cookies entre entornos.
