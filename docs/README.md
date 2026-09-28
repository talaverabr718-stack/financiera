# Financiera

Sistema web para la gestión de clientes, solicitudes, créditos, cobranza, mora, caja, contabilidad y configuración operativa.

## Tecnologías

Laravel 12, PHP 8.2+, Vue 3, Inertia, Vite, Tailwind CSS, MySQL y SQLite para pruebas.

## Inicio rápido

1. Copie `.env.example` como `.env` y complete las variables requeridas.
2. Instale dependencias: `composer install` y `npm ci`.
3. Genere la clave solo en una instalación nueva: `php artisan key:generate`.
4. Ejecute migraciones de forma controlada: `php artisan migrate`.
5. Compile recursos: `npm run build`, o use `npm run dev` durante desarrollo.
6. Inicie la aplicación con `php artisan serve`.

Para desarrollo integrado existe `composer dev`. Consulte [Instalación](docs/01-INSTALACION.md) antes de usar Docker o producción.

## Calidad

- Formato: `./vendor/bin/pint --test`
- Pruebas: `php artisan test --compact`
- Frontend: `npm run build`

## Documentación

- [Instalación](docs/01-INSTALACION.md)
- [Entornos](docs/02-ENTORNOS.md)
- [Arquitectura](docs/03-ARQUITECTURA.md)
- [Configuración](docs/05-CONFIGURACION.md)

## Seguridad

No registre secretos, `.env`, respaldos ni credenciales en Git. Producción no crea usuarios demo al iniciar; cree el primer administrador con `app:create-admin`.

## Soporte

Reporte incidencias con pasos para reproducir, versión o commit, entorno, hora, errores y evidencia no sensible.
