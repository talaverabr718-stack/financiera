# Despliegue y rollback

El procedimiento implementado está en `deployment/deploy.sh`: instala dependencias sin desarrollo, ejecuta `npm ci` y `npm run build`, habilita mantenimiento, ejecuta migraciones, reconstruye cachés, reinicia colas y vuelve a publicar la aplicación.

Antes: valide CI, genere respaldo MySQL y registre el commit. Después: compruebe `/up`, logs y workers.

Rollback de código y restauración de datos son operaciones distintas. No restaure MySQL automáticamente. La compatibilidad de migraciones debe evaluarse antes de publicar; el script no implementa rollback automático.
