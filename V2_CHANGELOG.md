## V2.2.37 — Feed Full Audit Display Integrity
- Removed legacy client-side DataTables audit filters from Layer and Ruminant feed pages so server-backed Full Audit shows reversal/restoration rows.
- Normalized zero effective feed usage from `-0.00` to `0.00`.


## V2.2.31 — Permission & Administration Hardening
- Tenant-scoped permission matrix with refresh-effective authorization.
- Fixed Broiler Feed Operational/Full Audit switching.
- Added permission-driven Sales Inventory and Expense access.
- Restored tenant-scoped Platform Owner user management and hardened Team User deletion.
- Fixed Farm Admin access label and Permissions dark-mode guidance contrast.
- Hardened permanent farm purge for V2.2 allocation, ruminant, financial, permission and audit data.

## V2.2.22 — Inventory & Expense Cleanup
- Distinguishes setup-only opening-stock history from real operational audit history so newly-created test/items can be deleted cleanly.
- Prevents category-delete HTTP 500 errors and blocks categories that are still assigned to stock items.
- Uses the shared modern confirmation UI for Inventory Ledger deletion.
- Reduces success notification display time to about 2.5 seconds.
- Aligns Layer/Broiler Add/Edit Expense Production Type presentation while preserving Ruminant dependent attribution editing.

# V2.2.3 / Integrated Daily Feed Consumption

## Added
- Active Feed Item selector directly on Layer Daily Record.
- Active Feed Item selector directly on Broiler Daily Record.
- Active Feed Item selector directly on Ruminant Daily Record.
- Daily feed usage now links operational records to inventory and financial costing.
- Ruminant daily records retain the selected feed item's unit.
- Automatic inventory reversal/re-application when a daily record is edited.
- Automatic inventory restoration when a daily record is deleted.
- Database migration `015_daily_feed_integration.sql`.
- Release contract `scripts/verify_v223_daily_feed_integration.php`.

## Safety
- Daily record write and linked stock transaction are atomic.
- Insufficient stock prevents the daily record from being saved.
- Feed item must belong to the current farm, be active, and match the module's feed category.
- Existing dedicated Feed Records pages remain available for detailed transaction management.

## V2.2.10 — Feed reversal audit integrity
- Daily-record feed movements are now append-only for edit/delete reversal flows.
- Original usage rows are retained and marked `is_reversed=1` instead of being deleted.
- Automatic restoration rows link to the original with `reversal_of_id`.
- Financial feed-consumption costing ignores reversed usage rows.
- Feed ledgers display `Reversed` and `Restoration` status badges for audit clarity.
- This prevents apparent stock inflation such as a restoration being displayed as `100 -> 115` after the original `-15` movement had been deleted.

## V2.2.11 — Stock Ledger Integrity Pass

- Centralized all stock mutations in `lib/stock_service.php`.
- Daily Record, Feed Record and Inventory/API stock movements now share one append-only ledger engine.
- Manual feed edit/delete now creates linked reversals instead of mutating/deleting the original ledger row.
- Inventory category/item cleanup no longer deletes stock history.
- Reversal rows now use the correction/posting date while retaining the original effective date on the original transaction.
- Dashboard and stock charts exclude reversed originals from active balances.
- Stock History now separates audit rows from active balance calculations and exposes ledger reconciliation status.
- Feed-cost calculations exclude reversal rows so a reversed receipt cannot become a fake feed expense.
- Added `migrations/016_stock_ledger_integrity.sql` and `scripts/reconcile_stock_ledger.php`.
- Added `scripts/verify_v2211_stock_ledger_integrity.php` contract checks.


## V2.2.12 — Stock Ledger Hardening
- Physical stock reconciliation now includes every posted movement; reversed originals are not deleted from arithmetic because linked reversals cancel them.
- Stock History and dashboard stock charts reconstruct balances from posted event order (`created_at`, `id`) rather than trusting potentially backdated `new_stock` snapshots.
- Added safe legacy opening-balance migration for stock items that have current stock but no ledger history (e.g. Lasota). Existing mismatched histories are reported, not guessed or overwritten.
- Added reconciliation CLI help.

## V2.2.16 — Historical Cost Integrity
- Hardened transaction-level historical inventory costing against later price changes.
- Added weighted-average cost replay for back-dated corrections and reversal valuation re-alignment.
- Required explicit receipt price on Inventory > Update Stock > Received.
- Scoped profitability feed consumption to actual feed inventory only.
- Kept sales and expense source-price snapshots unchanged.

## V2.2.26 — Dark Theme Consistency Audit
- Hardened dark-mode contrast across dashboard inventory command center, quick actions, daily records, feed/expense tables, forms, modals and reporting pages after full screenshot QA.
- Added paired dark surfaces/foregrounds to prevent light cards with pale dark-mode text.
- Added explicit dark-mode support for calendar cells, striped table even/odd states, native select options, modal close buttons and legacy light utility surfaces.
- No business/accounting/database logic changed.

## V2.2.27 — Asset Cache & Theme Reload Hardening
- Replaced the stale fixed `2024.06.01` browser asset version with per-file `filemtime()` cache keys.
- Ensured deployed CSS/JS updates are fetched immediately after a release instead of reusing old cached theme files.
- Added dual `data-theme` / `data-bs-theme` dark selectors for the Dashboard stock command center and quick actions.
- No database or financial/stock behavior changed.

## V2.2.28 — Production Theme Contrast Completion
- Completed dark-mode contrast hardening across Poultry/Ruminant production modules.
- Fixed legacy calendar date/record-count visibility.
- Fixed Ruminant Animal Profile contrast.
- No database or business-logic changes.

## V2.2.29 — Final Theme Contrast Cleanup
- Fixed faded production intelligence strips across Layer, Broiler and Ruminant Daily/Feed/Expense pages.
- Fixed Inventory Ledger sticky Actions header/body cells retaining white backgrounds in dark mode.
- No database or business-logic changes.

## V2.2.30 — Inventory Health Contrast Completion
- Fixed dark-mode contrast for the Inventory smart health percentage ring.

## V2.2.32 — Permission Governance & Role Limits
- Reworked tenant permission governance and subscribed-role visibility.
- Added Platform Owner per-role user caps per farm.
- Added explicit cycle permission and direct Sales navigation.
- Hardened custom deletion confirmations and Broiler audit switching.
- Completed permission-page dark-mode contrast cleanup.

## V2.2.33 — Feed Audit Consistency & User Limit Clarity
- Server-backed Layer/Ruminant feed audit views.
- Clear role-limit usage messages and used/max role capacity.

## V2.2.34
Role-limit count correction, exact account-limit semantics, duplicate Sales navigation removal, and delegated production expense entry for Sales Representative.

## V2.2.35 — Exact Role Quotas & Child-Aware Navigation
- Excluded protected Farm Admin from specialist user quotas.
- Made Poultry/Ruminant parent navigation child-permission aware.
- Removed duplicate expense-entry links from Management.

## V2.2.36 — Sales Expense Delegation Fix
- Fixed an authorization short-circuit that blocked Sales Representatives from delegated Layer, Broiler and Ruminant expense pages even when the tenant permission matrix allowed them.
- Preserved child-aware Poultry/Ruminant navigation and exact role quotas from V2.2.35.
