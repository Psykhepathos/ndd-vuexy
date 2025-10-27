<?php
$lines = file('app/Services/SemPararSoapService.php');
$balance = 0;
foreach ($lines as $lineNum => $line) {
    $opens = substr_count($line, '{');
    $closes = substr_count($line, '}');
    $balance += $opens - $closes;
    if ($lineNum >= 508 && $lineNum <= 610) {
        echo sprintf("Line %d: opens=%d closes=%d balance=%d | %s",
            $lineNum + 1, $opens, $closes, $balance, trim($line)) . "\n";
    }
}
echo "Final balance: $balance\n";
