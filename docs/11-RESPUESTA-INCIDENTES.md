# Respuesta a incidentes

## Aplicación inaccesible

Gravedad alta. Verifique `/up`, Nginx/PHP-FPM, variables y logs. Contenga habilitando mantenimiento si es necesario; conserve hora, commit y registros.

## MySQL o migración

Gravedad alta. Verifique conectividad, espacio, estado del servicio y migraciones aplicadas. No elimine datos ni restaure producción sin revisión del impacto financiero.

## Acceso no autorizado o posible exposición

Gravedad crítica. Preserve evidencia, invalide sesiones o credenciales afectadas según el caso, limite acceso y escale al responsable de seguridad.

## Inconsistencia financiera

Gravedad crítica. Suspenda la operación afectada, conserve auditoría, pagos, asignaciones y logs; investigue antes de corregir. Use mecanismos autorizados de reverso, nunca eliminación física.
