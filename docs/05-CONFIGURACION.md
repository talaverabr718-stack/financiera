# Configuración

| Variable | Propósito | Entorno |
|---|---|---|
| `APP_ENV` | Entorno Laravel | todos |
| `APP_DEBUG` | Diagnóstico; debe ser `false` en producción | todos |
| `APP_KEY` | Cifrado de Laravel; generar solo en instalación nueva | todos |
| `APP_URL` | URL pública | todos |
| `DB_*` | Conexión MySQL | desarrollo/producción |
| `SESSION_*` | Seguridad y duración de sesión | todos |
| `QUEUE_CONNECTION` | Driver de colas; el ejemplo usa `database` | todos |
| `CACHE_STORE` | Driver de caché; el ejemplo usa `database` | todos |
| `MAIL_*` | Entrega de correo | todos |
| `GOOGLE_MAPS_*` | Integración de mapas si se configura | aplicable |
| `APP_RUN_MIGRATIONS` | Control de migraciones del arranque Docker | Docker |

Use `.env.example` para desarrollo y `.env.production.example` como referencia de producción. Nunca incluya valores reales de contraseñas, tokens, claves de mapas o `APP_KEY` en documentación o control de versiones. No regenere `APP_KEY` en una instalación existente sin evaluar el impacto sobre datos cifrados y sesiones.
