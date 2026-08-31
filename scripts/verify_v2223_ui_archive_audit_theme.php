<?php
$root=dirname(__DIR__); $checks=[];
function ck($ok,$name){ echo ($ok?'PASS':'FAIL').": $name\n"; if(!$ok)$GLOBALS['bad']=true; }
$inv=file_get_contents($root.'/inventory.php'); ck(strpos($inv,'Archived Inventory Items')!==false,'archived inventory manager exists'); ck(strpos($inv,'Protected history')!==false,'historical items remain protected');
$stock=file_get_contents($root.'/lib/stock_service.php'); ck(strpos($stock,"item['is_active']")!==false,'backend blocks inactive stock movement');
foreach(['poultry/layer_feeds.php','poultry/broiler_feeds.php','ruminant/ruminant_feeds_record.php'] as $f){$s=file_get_contents($root.'/'.$f);ck(strpos($s,"AND is_active = 1")!==false,"$f hides archived stock cards");ck(strpos($s,'data-feed-view="audit"')!==false,"$f has full audit view");ck(strpos($s,'feed-audit-row')!==false,"$f marks correction rows");}
$profit=file_get_contents($root.'/management/profitability.php');ck(strpos($profit,'profitability-filter-help')!==false,'profitability helper moved below aligned filters');
$css=file_get_contents($root.'/assets/css/style.css');$head=file_get_contents($root.'/navbar_head.php');$nav=file_get_contents($root.'/navbar.php');ck(strpos($css,'data-theme="dark"')!==false && strpos($head,'farm-theme')!==false && strpos($nav,'themeToggle')!==false,'persistent dark mode exists');
exit(!empty($GLOBALS['bad'])?1:0);
