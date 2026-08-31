# V2.2.29 — Final Theme Contrast Cleanup

This release completes the remaining dark-mode contrast issues reported after V2.2.28.

- Production intelligence strips now use a dark surface with explicit readable heading/helper/icon colours across Layer, Broiler and Ruminant Daily, Feed and Expense pages.
- Inventory Ledger's sticky Actions column no longer retains the light-mode white background in dark mode.
- Both `data-theme="dark"` and Bootstrap `data-bs-theme="dark"` are supported.
- No database or business-logic changes.
