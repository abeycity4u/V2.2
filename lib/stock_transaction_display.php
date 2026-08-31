<?php
/**
 * Preserve the stored stock snapshots for ledger display.
 * Daily-record transactions are append-only in V2.2.10, so their snapshots
 * represent the actual stock movement at the time it occurred. Recomputing
 * by transaction date can misrepresent a backdated edit/delete reversal.
 */
function enrichStockTransactionDisplaySnapshots(PDO $pdo, int $farmId, array &$transactions): void
{
    foreach ($transactions as &$transaction) {
        $transaction['display_previous_stock'] = (float)($transaction['previous_stock'] ?? 0);
        $transaction['display_new_stock'] = (float)($transaction['new_stock'] ?? 0);
    }
    unset($transaction);
}
