<?php
$root=dirname(__DIR__);
$css=file_get_contents($root.'/assets/css/print.css');
$js=file_get_contents($root.'/assets/js/print-manager.js');
$head=file_get_contents($root.'/navbar_head.php');
$style=file_get_contents($root.'/assets/css/style.css');
$checks=[];
$must=function($ok,$msg)use(&$checks){$checks[]=[$ok,$msg];};
$must(str_contains($head,"/assets/css/print.css"),'central print stylesheet loaded globally');
$must(str_contains($head,"/assets/js/print-manager.js"),'central print manager loaded globally');
$must(str_contains($js,'widestTableColumnCount'),'orientation auto-detects wide tables');
$must(str_contains($js,"label === 'actions'"),'Actions columns are centrally detected');
$must(str_contains($css,'.print-action-column'),'Actions columns are centrally hidden');
$must(str_contains($css,'.table-responsive'),'responsive table overflow is centrally released');
$must(str_contains($css,'table-header-group'),'table headers repeat across printed pages');
$must(str_contains($css,'break-inside: avoid-page'),'row/section page breaks are centrally controlled');
$must(!str_contains($style,'V2.2.41 Sales Report print layout stabilization'),'Sales-specific print patch removed from style.css');
$must(!str_contains($style,'/* Print Styles */'),'legacy global print block removed from style.css');
$native=[];
$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
foreach($it as $f){
    $p=str_replace('\\','/',$f->getPathname());
    if(!preg_match('/\.(php|js)$/',$p) || str_contains($p,'/assets/vendor/') || (str_ends_with($p,'/assets/js/print-manager.js') || str_ends_with($p,'/scripts/verify_v2241_central_print_architecture.php'))) continue;
    if(str_contains(file_get_contents($p),'window.print(')) $native[]=$p;
}
$must(!$native,'no application page bypasses PrintManager with window.print()');
foreach($checks as [$ok,$msg]) echo ($ok?'PASS':'FAIL')." - $msg\n";
$fail=count(array_filter($checks,fn($x)=>!$x[0]));
if($native) echo "Native print leftovers:\n".implode("\n",$native)."\n";
echo 'Result: '.(count($checks)-$fail).'/'.count($checks)." passed\n";
exit($fail?1:0);
