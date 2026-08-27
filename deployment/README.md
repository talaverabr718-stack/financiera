# Despliegue en VPS

Requisitos: Linux actualizado, Nginx, PHP 8.2 o superior con FPM y extensiones de Laravel, Composer 2, Node.js LTS, MySQL 8, Supervisor y Certbot.

1. Publicar el repositorio en `/var/www/financiera/current` y apuntar Nginx exclusivamente a `public/`.
2. Copiar `.env.production.example` a `.env`, completar secretos y ejecutar `php artisan key:generate` una sola vez.
3. Crear una base y usuario MySQL exclusivos, sin permisos globales.
4. Dar a `www-data` escritura solamente sobre `storage/` y `bootstrap/cache/`.
5. Adaptar e instalar `nginx.conf.example` y emitir el certificado TLS con Certbot.
6. Adaptar e instalar `supervisor-financiera.conf.example`; ejecutar `supervisorctl reread`, `update` y `start financiera-worker:*`.
7. Ejecutar `APP_DIR=/var/www/financiera/current bash deployment/deploy.sh`.
8. Crear el primer usuario con `php artisan app:create-admin administrador@dominio.com`.
9. Configurar cron: `* * * * * cd /var/www/financiera/current && php artisan schedule:run >> /dev/null 2>&1`.

No ejecutar `db:seed` en producción: los seeders contienen información demostrativa. Antes de cada despliegue se debe generar un respaldo consistente de MySQL y verificar `/up` después de publicar.
