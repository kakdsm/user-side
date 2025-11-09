<?php
$maintenanceFlagFile = __DIR__ . '/../../.maintenance';

if (file_exists($maintenanceFlagFile)) {
    // Send headers first
    header('HTTP/1.1 503 Service Temporarily Unavailable');
    header('Retry-After: 3600'); 

    // Now include HTML
    readfile(__DIR__ . '/maintenance.html'); // safer than include
    exit();
}
