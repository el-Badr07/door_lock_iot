<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-KEY');
header('Content-Type: application/json');

// Load environment variables (already done by init.php)
// Environment variables are now accessible via getenv() or $_ENV

// Autoloader
spl_autoload_register(function ($class) {
    $baseDir = __DIR__ . '/../api/';
    
    // List of directories to search within api/ (excluding handlers/)
    $searchDirs = ['config/', 'models/', 'utils/']; 
    
    foreach ($searchDirs as $dir) {
        $file = $baseDir . $dir . str_replace('\\', '/', $class) . '.php';
        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});

// Initialize Auth class AFTER autoloader is registered
Auth::init();

// Parse request
$requestMethod = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = explode('/', trim($uri, '/'));

// Remove 'public' from the URI if present
if (isset($uri[0]) && $uri[0] === 'public') {
    $uri = array_slice($uri, 1);
}

// Route the request
$endpoint = $uri[1] ?? '';
$id = $uri[2] ?? null;

// Include route handlers explicitly as they contain global functions
require_once __DIR__ . '/../api/handlers/access.php';
require_once __DIR__ . '/../api/handlers/users.php';
require_once __DIR__ . '/../api/handlers/auth.php';
require_once __DIR__ . '/../api/handlers/logs.php';
require_once __DIR__ . '/../api/handlers/health.php'; // Ensure health handler is included

// Handle preflight requests
if ($requestMethod === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Route the request based on the endpoint
switch (true) {
    case $endpoint === 'verify-access' && $requestMethod === 'POST':
        handleVerifyAccess();
        break;
    case $endpoint === 'users' && isset($uri[2]) && $uri[3] === 'cards' && isset($uri[4]) && $requestMethod === 'PUT':
        handleUpdateUserCard($uri[2], $uri[4]);
        break;
    case $endpoint === 'users' && isset($uri[2]) && $uri[3] === 'cards' && isset($uri[4]) && $requestMethod === 'DELETE':
        handleDeleteUserCard($uri[2], $uri[4]);
        break;
    case $endpoint === 'users' && isset($uri[2]) && isset($uri[3]) && $uri[3] === 'cards' && $requestMethod === 'GET':
        handleGetUserCards($uri[2]);
        break;
    case $endpoint === 'users' && isset($uri[2]) && isset($uri[3]) && $uri[3] === 'cards' && $requestMethod === 'POST':
        handleAddUserCard($uri[2]);
        break;
    case $endpoint === 'users' && isset($id) && $requestMethod === 'PUT':
        handleUpdateUser($id);
        break;
    case $endpoint === 'users' && isset($id) && $requestMethod === 'DELETE':
        handleDeleteUser($id);
        break;
    case $endpoint === 'users' && $requestMethod === 'GET':
        handleGetUsers();
        break;
    case $endpoint === 'users' && $requestMethod === 'POST':
        handleCreateUser();
        break;
    case $endpoint === 'access-logs' && $requestMethod === 'GET':
        handleGetAccessLogs();
        break;
    case $endpoint === 'login' && $requestMethod === 'POST':
        handleLogin();
        break;
    case $endpoint === 'health' && $requestMethod === 'GET':
        handleHealthCheck();
        break;
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Not Found']);
        break;
}
