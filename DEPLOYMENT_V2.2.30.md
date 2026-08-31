# V2.2.30 — Inventory Health Contrast Completion

UI-only corrective release.

- Fixes the inventory health score percentage becoming unreadable in dark mode.
- Replaces the score ring's hard-coded white center with the active dark surface token.
- Forces the score value to use the strong dark-theme foreground color.
- Supports both `data-theme="dark"` and Bootstrap `data-bs-theme="dark"`.
- No database migration required.
