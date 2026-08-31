<?php
// V2.2 financial schema preflight. Run from CLI after deployment.
require_once dirname(__DIR__) . '/config.php';

$required = [
    'stock_transactions' => ['cycle_id','unit_cost','total_cost','source_type','source_id','is_reversed','reversal_of_id','reversed_at'],
    'financial_allocations' => ['id','farm_id','expense_id','cycle_id','allocation_percent','allocated_amount'],
    'financial_settings' => ['id','farm_id','feed_costing_method','default_currency'],
];

$pdo = $pdo ?? null;
if (!$pdo instanceof PDO) { fwrite(STDERR, "Database connection unavailable.\n"); exit(2); }
$errors=[];
foreach ($required as $table=>$columns) {
    $q=$pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
    $q->execute([$table]);
    $have=array_flip($q->fetchAll(PDO::FETCH_COLUMN));
    if (!$have) { $errors[]="Missing table: $table"; continue; }
    foreach($columns as $c) if(!isset($have[$c])) $errors[]="Missing column $table.$c";
}
if ($errors) { echo "V2.2 financial schema is incomplete:\n- ".implode("\n- ",$errors)."\n"; exit(1); }
echo "V2.2 financial schema OK.\n";
