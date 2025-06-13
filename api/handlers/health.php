<?php

function handleHealthCheck() {
    $status = [
        'status' => 'ok',
        'timestamp' => date('c'),
        'services' => []
    ];
    
    // Check database connection
    try {
        $db = Database::getInstance()->getConnection();
        $db->query('SELECT 1');
        $status['services']['database'] = 'ok';
    } catch (Exception $e) {
        $status['services']['database'] = 'error: ' . $e->getMessage();
        $status['status'] = 'degraded';
    }
    
    // Add more service checks here as needed
    
    Response::json($status);
}

// Register health check route at /api/health
if (($uri[1] ?? '') === 'health' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    handleHealthCheck();
    exit();
}
