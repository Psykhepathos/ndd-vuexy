<?php

$log = file_get_contents('storage/logs/laravel.log');

// Find last SOAP Request
preg_match_all('/\[SemParar\] SOAP Request.*?"request":"(.*?)"\}/', $log, $matches);
if (empty($matches[1])) {
    echo "No SOAP requests found\n";
    exit;
}

$lastRequest = end($matches[1]);
$lastRequest = stripcslashes($lastRequest);

echo "============ LAST SOAP REQUEST ============\n";
echo $lastRequest;
echo "\n\n";

// Find last SOAP Response
preg_match_all('/\[SemParar\] SOAP Response.*?"response":"(.*?)"\}/', $log, $matchesResp);
if (!empty($matchesResp[1])) {
    $lastResponse = end($matchesResp[1]);
    $lastResponse = stripcslashes($lastResponse);

    echo "============ LAST SOAP RESPONSE ============\n";
    echo $lastResponse;
    echo "\n";
}
