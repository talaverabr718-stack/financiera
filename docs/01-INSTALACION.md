# Instalación

## Requisitos verificados

PHP 8.2 o superior, Composer 2, Node.js compatible con Vite, y MySQL para la configuración predeterminada. Las pruebas usan SQLite. Docker Compose es opcional.

## Desarrollo local

```bash
cp .env.example .env
composer install
npm ci
php artisan key:generate
php artisan migrate
npm run build
php artisan serve
```

`composer dev` inicia servidor Laravel, `queue:listen` y Vite de forma concurrente. Verifique `/up` y la pantalla de ingreso.

## Docker

Complete primero las variables `MYSQL_ROOT_PASSWORD`, `MYSQL_DATABASE`, `MYSQL_USER` y `MYSQL_PASSWORD`. El archivo `compose.yaml` usa MySQL y la aplicación local. Revise el archivo antes de iniciarlo, pues ejecuta `php artisan migrate --force` al arrancar el contenedor de aplicación.

## Primer administrador

En una instalación sin usuarios:

```bash
php artisan app:create-admin administrador@dominio.com --name="Nombre Administrador"
```

El comando solicita contraseña de forma interactiva. No pase contraseñas como argumentos ni use datos demo en producción.

## Problemas frecuentes

- Error de Vite: ejecute `npm ci` y `npm run build`.
- Variables faltantes: revise `.env` contra `.env.example`.
- Base de datos: compruebe host, puerto, base, usuario y contraseña antes de migrar.
