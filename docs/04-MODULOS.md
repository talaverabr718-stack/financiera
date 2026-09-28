# Módulos

| Módulo | Responsabilidad | Control de acceso |
|---|---|---|
| Clientes | Directorio, expediente y transferencias | módulo `clients` y alcance de cartera |
| Solicitudes y créditos | Registro, revisión y desembolso de crédito | permisos del módulo correspondiente |
| Cobranza | Rutas, visitas, registro de pagos, autorizaciones y correcciones | `collections`; operaciones sensibles requieren autorización |
| Mora | Casos, recálculo y cargos configurados | `delinquency` |
| Caja y contabilidad | Movimientos, períodos, cuentas y reportes | módulos contables |
| Configuración | Marca, apariencia, usuarios, roles y permisos | `settings`, con protección de al menos un administrador |
| Reportes | Consultas operativas y exportación | permisos de reportes |

Las rutas aplican `EnsureModuleAccess` cuando el módulo está configurado. Los colaboradores deben mantenerse dentro del alcance de cartera aplicado por backend.
