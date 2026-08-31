# V2.2.41 — Sales Report Print Layout Stabilization

Print-only stabilization for `management/sales_records.php`.

- A4 landscape for the Sales Report.
- Printable-width tables with wrapping and compact print typography.
- `.table-responsive` overflow released during printing.
- Debt ledger avoids orphaning its header from the first row.
- V2.2.40 receivable/payment logic is unchanged.

Verify with `php scripts/verify_v2241_sales_print_layout.php`, the V2.2.40 verifier, PHP syntax check, and live Print Monthly QA.
