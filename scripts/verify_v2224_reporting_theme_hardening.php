<?php
$root=dirname(__DIR__);$n=0;function ck($ok,$m){global $n;if(!$ok){fwrite(STDERR,"FAIL: $m\n");exit(1);}echo "PASS: $m\n";$n++;}
$p=file_get_contents($root.'/management/profitability.php');ck(substr_count($p,'Production type narrows Layer, Broiler or ruminant species')===1,'Profitability helper copy appears once');
$h=file_get_contents($root.'/navbar_head.php');$nbar=file_get_contents($root.'/navbar.php');ck(strpos($h,"data-bs-theme")!==false&&strpos($h,"theme.css")!==false&&strpos($nbar,'themeQuickToggle')!==false,'dark mode is wired through app and Bootstrap theme attributes');
foreach(['poultry/layer_feeds.php','poultry/broiler_feeds.php','ruminant/ruminant_feeds_record.php'] as $f){$s=file_get_contents($root.'/'.$f);ck(strpos($s,'Actual Received')!==false&&strpos($s,'Effective Used')!==false&&strpos($s,'Effective Net Change')!==false,basename($f).' uses clarified feed summary labels');}
ck(is_file($root.'/assets/css/theme.css'),'late-loaded theme stylesheet exists');
echo "V2.2.24 verification passed: {$n} check(s).\n";
