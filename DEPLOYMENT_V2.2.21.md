# Farm Platform V2.2.21 — Effective-Date & UI Consistency

This stabilization release closes several integrity and UX gaps found during live QA.

- Stock reversals/restorations now inherit the original transaction business date; `created_at` still records when the correction was posted.
- Layer, Broiler and Ruminant Edit Expense forms now mirror Add Expense attribution fields.
- Ruminant Edit Expense supports Production Type -> matching Production Cycle dependency.
- Inactive inventory items with stock history are protected as archived audit records instead of presenting a permanent-delete action that must fail.
- Inventory `Feed Type` label is renamed to `Usage Classification` to cover feed and non-feed stock without colliding with Production Type terminology.
- Success notifications auto-dismiss sooner (3.5 seconds); info/warning notifications remain slightly longer.

No database migration is required.
