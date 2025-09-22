<?php
/**
 * Simple connection test for debugging
 */
header('Content-Type: application/json');

// Basic connection test
echo json_encode([
    'success' => true,
    'message' => 'Connection working!',
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => PHP_VERSION,
    'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
]);
?>