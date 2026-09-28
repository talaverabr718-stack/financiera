# Rotación de credenciales

Rote contraseñas MySQL, cuentas administrativas, tokens e integraciones mediante valores nuevos almacenados fuera de Git. Actualice servicios dependientes y verifique conectividad antes de revocar el valor anterior.

`APP_KEY` no debe cambiarse sin análisis: puede invalidar sesiones y datos cifrados existentes. Planifique mantenimiento, respaldo, impacto y validación previa. No incluya secretos en comandos, documentación ni historial de shell.
