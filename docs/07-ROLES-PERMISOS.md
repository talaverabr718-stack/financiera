# Roles y permisos

La autorización combina módulos habilitados, rol del usuario y overrides individuales.

| Nivel | Efecto |
|---|---|
| `view` | Acceso de consulta al módulo |
| `manage` | Implica `view` |
| `full` | Implica `manage` y `view` |

El middleware `EnsureModuleAccess` niega la solicitud con 401 sin usuario, 403 sin permiso y 404 para módulos deshabilitados. No presupone que `full` otorgue acceso a módulos inexistentes o deshabilitados.

Los permisos se almacenan por rol y pueden sobrescribirse por usuario. Al editar permisos, el sistema impide dejar la operación sin un usuario activo con acceso total a Configuración.

La matriz efectiva depende de los módulos registrados y de los permisos configurados en la base; debe revisarse desde Configuración antes de otorgar acceso.
