# Reglas financieras verificadas

## Mora

`DelinquencyTrackingService` usa la zona horaria configurada. Una cuota no entra en mora el día de vencimiento; entra al día siguiente. Los saldos se manipulan con BCMath y precisión de dos decimales.

La mora puede configurarse como porcentaje diario o cargo fijo por cuota vencida. El cargo se registra mediante acumulaciones de mora y no puede reducir el importe debido por debajo de lo ya pagado. Los pagos parciales conservan el caso activo; al liquidar la única cuota vencida el caso se resuelve.

Pruebas: `DelinquencyTrackingTest`, `MoraPropagationTest`.

## Pagos y correcciones

Cobranza registra pagos dentro de transacciones y bloqueos. Existen controles de idempotencia, autorización de segundo pago y autorización previa para corrección de monto. El detalle de asignación se conserva en `payment_allocations`.

## Pendiente de validación funcional

La fórmula contractual de interés y sus políticas de aprobación deben consultarse en los servicios y pruebas específicos antes de documentarse como norma de negocio.
