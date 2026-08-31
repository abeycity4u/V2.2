from pathlib import Path

root = Path(__file__).resolve().parents[1]
css_path = root / 'assets/css/style.css'
css = css_path.read_text()
marker = 'V2.2.41 Sales Report print layout stabilization'
block = r'''

/* V2.2.41 Sales Report print layout stabilization */
@media print {
    body:has(#debtLedgerPrintArea) { padding-top: 0 !important; }
    @page { size: A4 landscape; margin: 8mm; }
    body:has(#debtLedgerPrintArea) .container-fluid { width: 100% !important; max-width: none !important; padding: 0 !important; margin: 0 !important; }
    body:has(#debtLedgerPrintArea) .card-body { padding: 8px !important; }
    body:has(#debtLedgerPrintArea) .table-responsive { overflow: visible !important; width: 100% !important; }
    body:has(#debtLedgerPrintArea) table.table { width: 100% !important; max-width: 100% !important; table-layout: fixed !important; font-size: 7.5pt !important; margin-bottom: 8px !important; }
    body:has(#debtLedgerPrintArea) table.table th,
    body:has(#debtLedgerPrintArea) table.table td { padding: 3px 4px !important; white-space: normal !important; overflow-wrap: anywhere !important; word-break: normal !important; vertical-align: top !important; }
    body:has(#debtLedgerPrintArea) #debtLedgerPrintArea table { break-inside: avoid-page; page-break-inside: avoid; }
    body:has(#debtLedgerPrintArea) #debtLedgerPrintArea thead { display: table-header-group; break-after: avoid-page; page-break-after: avoid; }
    body:has(#debtLedgerPrintArea) #debtLedgerPrintArea tbody tr:first-child { break-before: avoid-page; page-break-before: avoid; }
    body:has(#debtLedgerPrintArea) table.table tr { break-inside: avoid-page; page-break-inside: avoid; }
}
'''
if marker not in css:
    css_path.write_text(css.rstrip() + block + '\n')

(root / 'scripts/verify_v2241_sales_print_layout.php').write_text(r'''<?php
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
''')

(root / 'DEPLOYMENT_V2.2.41.md').write_text('''# V2.2.41 — Sales Report Print Layout Stabilization\n\nPrint-only stabilization for `management/sales_records.php`.\n\n- A4 landscape for the Sales Report.\n- Printable-width tables with wrapping and compact print typography.\n- `.table-responsive` overflow released during printing.\n- Debt ledger avoids orphaning its header from the first row.\n- V2.2.40 receivable/payment logic is unchanged.\n\nVerify with `php scripts/verify_v2241_sales_print_layout.php`, the V2.2.40 verifier, PHP syntax check, and live Print Monthly QA.\n''')
