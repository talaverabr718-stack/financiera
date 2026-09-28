# Financiera

## Arranque seguro para producción

La aplicación no crea usuarios ni datos de demostración al iniciar. Las migraciones son versionadas y solo se aplican por una acción explícita de despliegue; los reinicios normales de Docker no ejecutan seeders ni alteran datos existentes.

1. Copia `.env.production.example` a `.env` y define valores únicos para `APP_KEY`, `DB_PASSWORD` y demás secretos. No publiques este archivo.
2. Construye la imagen y arranca los servicios. `APP_RUN_MIGRATIONS` debe permanecer en `false` durante los reinicios normales.
3. En una ventana administrativa controlada, ejecuta una sola vez las migraciones:

   ```bash
   php artisan migrate --force
   ```

4. Crea el primer administrador de forma interactiva. La contraseña no se pasa como argumento ni queda en el historial del shell:

   ```bash
   php artisan app:create-admin administrador@dominio.com --name="Nombre Administrador"
   ```

El comando solo funciona si no existen usuarios y asigna el rol `administrator`. Para crear usuarios posteriores usa Configuración → Usuarios.

## Seeders

- `php artisan db:seed --force` ejecuta solo `ProductionSeeder`, que no crea usuarios, clientes, créditos ni movimientos.
- Los seeders demostrativos están bloqueados cuando `APP_ENV=production`.
- Para un entorno local o de pruebas, nunca producción:

  ```bash
  php artisan db:seed --class=Database\\Seeders\\DemoDatabaseSeeder
  ```

## Verificación

```bash
php artisan test --filter=ProductionBootstrapTest
# Contra una base MySQL exclusiva de pruebas:
DB_CONNECTION=mysql DB_DATABASE=financiera_test php artisan test --group=mysql
```

Antes de desplegar, respalda MySQL, ejecuta las migraciones una sola vez desde el proceso de despliegue y comprueba `/up`.

## Autenticación

El acceso primario usa exclusivamente correo y contraseña robusta. El PIN ya no es una credencial ni se almacena. Cinco fallos consecutivos bloquean temporalmente la cuenta; los intentos posteriores aumentan el tiempo, con un máximo de quince minutos. La recuperación de contraseña desbloquea la cuenta tras usar un token de un solo uso con vigencia de 60 minutos. Los eventos se registran en `authentication_events`.

## Documentación técnica

- [Instalación](docs/01-INSTALACION.md)
- [Entornos](docs/02-ENTORNOS.md)
- [Arquitectura](docs/03-ARQUITECTURA.md)
- [Módulos](docs/04-MODULOS.md)
- [Configuración](docs/05-CONFIGURACION.md)
- [Reglas financieras](docs/06-REGLAS-FINANCIERAS.md)
- [Roles y permisos](docs/07-ROLES-PERMISOS.md)
- [Colas y scheduler](docs/08-COLAS-SCHEDULER.md)
- [Respaldo y restauración](docs/09-RESPALDO-RESTAURACION.md)
- [Despliegue y rollback](docs/10-DESPLIEGUE-ROLLBACK.md)
- [Respuesta a incidentes](docs/11-RESPUESTA-INCIDENTES.md)
- [Rotación de credenciales](docs/12-ROTACION-CREDENCIALES.md)
