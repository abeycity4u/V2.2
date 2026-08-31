<?php
$root = dirname(__DIR__);
$theme = file_get_contents($root . '/assets/css/theme.css');
$checks = [
    'production panel body dark override' => 'body.poultry-page .poultry-panel > .card-body',
    'ruminant panel body dark override' => 'body.ruminant-page .poultry-panel > .card-body',
    'legacy calendar dark override' => '.calendar-legacy .calendar-day.has-record',
    'calendar date foreground override' => '.calendar-day .calendar-date',
    'calendar meta foreground override' => '.calendar-day .calendar-meta',
    'animal profile foreground hardening' => 'body.ruminant-page .poultry-panel .card-body .fw-semibold',
];
$failed = 0;
foreach ($checks as $label => $needle) {
    if (strpos($theme, $needle) === false) { echo "FAIL: $label\n"; $failed++; }
    else { echo "PASS: $label\n"; }
}
if ($failed) { exit(1); }
echo "V2.2.28 verification passed: ".count($checks)." check(s).\n";
