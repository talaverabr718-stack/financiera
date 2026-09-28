# Respaldo y restauración

Antes de desplegar, `deployment/README.md` exige un respaldo consistente de MySQL. Ejecute respaldos desde una cuenta autorizada, almacénelos fuera del servidor y verifique que el archivo sea legible. Pruebe restauraciones solo en una base aislada.

No existe en el repositorio un script de respaldo ni evidencia de una restauración ejecutada; por tanto estos procedimientos requieren validación operativa. Nunca restaure automáticamente una copia antigua sobre producción: pueden existir movimientos financieros posteriores. Preserve logs, commit desplegado y evidencia antes de cualquier recuperación.
