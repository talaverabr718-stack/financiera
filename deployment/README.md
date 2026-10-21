# Despliegue en VPS

Requisitos: Linux actualizado, Nginx, PHP 8.2 o superior con FPM y extensiones de Laravel, Composer 2, Node.js LTS, MySQL 8, Supervisor y Certbot.

1. Publicar el repositorio en `/var/www/financiera/current` y apuntar Nginx exclusivamente a `public/`.
2. Copiar `.env.production.example` a `.env`, completar secretos y ejecutar `php artisan key:generate` una sola vez.
3. Crear una base y usuario MySQL exclusivos, sin permisos globales.
4. Dar a `www-data` escritura solamente sobre `storage/` y `bootstrap/cache/`.
5. Adaptar e instalar `nginx.conf.example` y emitir el certificado TLS con Certbot.
6. Adaptar e instalar `supervisor-financiera.conf.example`; ejecutar `supervisorctl reread`, `update` y `start financiera-worker:*`.
7. Crear y verificar un respaldo consistente de MySQL fuera de `current/`.
8. Ejecutar el preflight manual: `php deployment/preflight.php`. El despliegue también lo ejecuta y se detiene ante una configuración insegura.
9. Desplegar indicando el respaldo verificado: `APP_DIR=/var/www/financiera/current BACKUP_REFERENCE=/ruta/al/respaldo.sql.gz bash deployment/deploy.sh`.
10. Crear el primer usuario con `php artisan app:create-admin administrador@dominio.com --name="Nombre Administrador"`. El comando solicita la contraseña de forma oculta y solo funciona si no existen usuarios.
11. Configurar cron: `* * * * * cd /var/www/financiera/current && php artisan schedule:run >> /dev/null 2>&1`.
12. Verificar `https://dominio/up`, el inicio de sesión, recuperación de contraseña, colas y un reporte exportable.

No ejecutar seeders demostrativos en producción. `db:seed` usa únicamente el seeder estructural vacío y los datos demo están bloqueados por entorno. Antes de cada despliegue se debe generar un respaldo consistente de MySQL y verificar `/up` después de publicar.

## Ensayo obligatorio en preproducción

Use una base MySQL 8 exclusiva. Ejecute primero las migraciones desde cero y luego la suite completa con las variables `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD`. La base de pruebas se recrea, por lo que nunca debe apuntar a información real.

Antes del primer pase a producción también debe ensayarse una restauración del respaldo, no solamente su creación. Conserve al menos un respaldo previo al despliegue fuera del directorio publicado y documente quién lo verificó.
