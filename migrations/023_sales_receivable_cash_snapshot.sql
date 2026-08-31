-- V2.2.39: preserve the cash received at sale creation so later sale edits
-- recalculate receivables from immutable cash facts instead of stale debt.
ALTER TABLE sales_records
    ADD COLUMN payment_received DECIMAL(14,2) NULL DEFAULT NULL AFTER unit_price;
