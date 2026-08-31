<?php
/**
 * Canonical stock reporting helpers.
 *
 * Physical ledger reconciliation intentionally includes every posted movement,
 * including original rows later marked reversed and their compensating reversal
 * rows. Reporting cards such as "Actual Received" and "Effective Used"
 * answer a different question: what effective (currently valid) business
 * movements remain after corrections? For those cards we exclude both the
 * reversed original and the linked reversal row, while retaining the replacement
 * movement created by an edit.
 */



if (!function_exists('stock_effective_sql_predicate')) {
    /**
     * SQL equivalent of stock_effective_movement_rows().
     * Use this anywhere a report must select currently valid stock movements.
     */
    function stock_effective_sql_predicate(string $alias = ''): string
    {
        $prefix = $alias !== '' ? rtrim($alias, '.') . '.' : '';
        return $prefix . 'is_reversed = 0 AND ' . $prefix . 'reversal_of_id IS NULL';
    }
}

if (!function_exists('stock_effective_movement_rows')) {
    function stock_effective_movement_rows(array $transactions): array
    {
        return array_values(array_filter($transactions, static function (array $row): bool {
            $isReversed = (int)($row['is_reversed'] ?? 0) === 1;
            $isReversalRow = !empty($row['reversal_of_id']);
            return !$isReversed && !$isReversalRow;
        }));
    }
}

if (!function_exists('stock_effective_movement_summary')) {
    function stock_effective_movement_summary(array $transactions): array
    {
        $summary = [
            'received' => 0.0,
            'used' => 0.0,
            'balance' => 0.0,
        ];

        foreach (stock_effective_movement_rows($transactions) as $row) {
            $quantity = round((float)($row['quantity'] ?? 0), 2);
            if (($row['transaction_type'] ?? '') === 'received') {
                $summary['received'] += $quantity;
            } elseif (($row['transaction_type'] ?? '') === 'used') {
                $summary['used'] += $quantity;
            }
        }

        $summary['received'] = round($summary['received'], 2);
        $summary['used'] = round($summary['used'], 2);
        $summary['balance'] = round($summary['received'] - $summary['used'], 2);
        return $summary;
    }
}

if (!function_exists('stock_effective_summary_unit_label')) {
    function stock_effective_summary_unit_label(array $transactions): string
    {
        $units = [];
        foreach (stock_effective_movement_rows($transactions) as $row) {
            $unit = trim((string)($row['unit'] ?? ''));
            if ($unit !== '') {
                $units[$unit] = true;
            }
        }
        $units = array_keys($units);
        if (count($units) === 1) {
            return $units[0];
        }
        if (count($units) === 0) {
            return '';
        }
        return 'Mixed units';
    }
}
