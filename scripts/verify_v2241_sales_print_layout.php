<?php
$css = file_get_contents(dirname(__DIR__) . '/assets/css/style.css');
$checks = [
 'version marker' => str_contains($css, 'V2.2.41 Sales Report print layout stabilization'),
 'sales report scoped' => str_contains($css, 'body:has(#debtLedgerPrintArea)'),
 'landscape print page' => str_contains($css, 'size: A4 landscape'),
 'responsive overflow released' => str_contains($css, 'overflow: visible !important'),
 'print table constrained' => str_contains($css, 'table-layout: fixed !important'),
 'cells wrap in print' => str_contains($css, 'overflow-wrap: anywhere !important'),
 'debt table avoids orphan break' => str_contains($css, 'break-inside: avoid-page'),
 'first debt row stays with header' => str_contains($css, '#debtLedgerPrintArea tbody tr:first-child'),
];
$failed=0; foreach($checks as $name=>$ok){ echo ($ok?'PASS':'FAIL')." - $name\n"; if(!$ok)$failed++; }
echo 'Result: '.(count($checks)-$failed).'/'.count($checks)." passed\n"; exit($failed?1:0);
