<?php

$args = array_slice($argv, 1);
$isTesting = in_array('--testing', $args) || in_array('-Testing', $args) || in_array('-t', $args);
$isSinSeed = in_array('--sin-seed', $args) || in_array('--no-seed', $args) || in_array('-SinSeed', $args);

if (PHP_OS_FAMILY === 'Windows') {
    $cmd = 'pwsh -ExecutionPolicy Bypass -File scripts/db_reset.ps1';
    if ($isTesting) {
        $cmd .= ' -Testing';
    }
    if ($isSinSeed) {
        $cmd .= ' -SinSeed';
    }
} else {
    $cmd = 'bash scripts/db_reset.sh';
    if ($isTesting) {
        $cmd .= ' --testing';
    }
    if ($isSinSeed) {
        $cmd .= ' --sin-seed';
    }
}

passthru($cmd, $returnCode);
exit($returnCode);
