# V2.2.40 — Receivable Payment Overpayment Protection

## Purpose
Prevent debt-payment entry from creating negative receivables or implicit customer advances.

## Changes
- General customer payments now calculate the customer's total allocatable open receivable before allocation.
- Payments are rejected when the customer has no outstanding balance.
- Payments greater than the customer's outstanding balance are rejected.
- Specific-sale payment protection remains enforced against that sale's outstanding balance.
- Any unexpected FIFO remainder fails closed; it is no longer inserted as an unallocated/advance payment.
- Payment modal shows the selected customer's available outstanding and disables payment submission when the selected balance is zero or negative.

## Verification
Run:

```bash
php scripts/verify_v2240_receivable_payment_overpayment_protection.php
php scripts/verify_v2239_receivables_cash_reconciliation.php
```

## Live QA recovery checkpoint
The V2.2.39 reproduction intentionally created a ₦1,000 unallocated payment for `Credit Test Customer`, leaving a -₦1,000 balance. Keep that row until V2.2.40 is deployed. After deployment, remove only that failed-test payment entry, confirm the customer returns to ₦0.00, then retry a ₦1,000 payment and confirm it is rejected with no new ledger row.
