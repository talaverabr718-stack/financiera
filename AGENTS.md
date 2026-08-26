# Financiera repository guide

- Laravel 12, PHP 8.2+, MySQL production and SQLite tests.
- Timezone `America/Managua`, locale `es`, base currency NIO.
- Never hardcode interest, delinquency, payment priority, legal rates, taxes, or accounting accounts.
- Financial records are immutable: use authorized reversals; never physically delete loans, installments, payments, disbursements, cash closings, or journal entries.
- Use Form Requests, policies, services/actions, database transactions, row locks, unique document sequences, and idempotency keys.
- Every seller query must enforce the active portfolio assignment on the backend.
- Do not implement amortization or payment allocation until its configuration is explicitly approved.
- Run focused tests first and preserve unrelated work.
