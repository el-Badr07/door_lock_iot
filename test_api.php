<?php
/**
 * Test script for Door Lock IoT API
 * 
 * This script tests the main API endpoints to ensure they're working correctly.
 * Run this after starting the application with docker-compose up -d
 */

// Load environment variables
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = explode("\n", file_get_contents($envFile));
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = str_replace(['"', "'"], '', trim($value));
        putenv("$key=$value");
        $_ENV[$key] = $value;
    }
}

// Configuration
$baseUrl = 'http://localhost';
$adminEmail = 'admin@example.com';
$adminPassword = 'admin123';
$apiKey = getenv('API_KEY') ?: $_ENV['API_KEY'] ?? 'your_generated_api_key_here_random_string';

echo "=== Door Lock IoT API Tester ===\n\n";

// Helper function to make HTTP requests
function httpRequest($method, $endpoint, $data = null, $token = null, $apiKey = null) {
    global $baseUrl;
    
    $url = rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');
    $headers = ['Content-Type: application/json'];
    
    if ($token) {
        $headers[] = "Authorization: Bearer $token";
    }
    
    if ($apiKey) {
        $headers[] = "X-API-KEY: $apiKey";
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    if ($data !== null) {
        $jsonData = json_encode($data);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        $headers[] = 'Content-Length: ' . strlen($jsonData);
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'body' => json_decode($response, true) ?: $response
    ];
}

// Test 1: Health check
echo "[1/6] Testing health check... ";
$result = httpRequest('GET', '/api/health');
if ($result['code'] === 200) {
    echo "✓ OK\n";
} else {
    echo "✗ FAILED (HTTP {$result['code']})\n";
    print_r($result['body']);
    exit(1);
}

// Test 2: Login with admin credentials
echo "[2/6] Logging in as admin... ";
$result = httpRequest('POST', '/api/login', [
    'email' => $adminEmail,
    'password' => $adminPassword
]);

if ($result['code'] === 200 && !empty($result['body']['data']['token'])) {
    $token = $result['body']['data']['token'];
    $userId = $result['body']['data']['user']['id'];
    echo "✓ Logged in (User ID: $userId)\n";
} else {
    echo "✗ LOGIN FAILED (HTTP {$result['code']})\n";
    print_r($result['body']);
    exit(1);
}

// Test 3: Get users list
echo "[3/6] Testing users list... ";
$result = httpRequest('GET', '/api/users', null, $token);
if ($result['code'] === 200 && isset($result['body']['data']) && is_array($result['body']['data'])) {
    $userCount = count($result['body']['data']);
    echo "✓ Found $userCount users\n";
} else {
    echo "✗ FAILED (HTTP {$result['code']})\n";
    print_r($result['body']);
    exit(1);
}

// Test 4: Create a test user
echo "[4/6] Creating test user... ";
$testUserEmail = 'test_' . time() . '@example.com';
$result = httpRequest('POST', '/api/users', [
    'name' => 'Test User',
    'email' => $testUserEmail,
    'password' => 'test1234',
    'role' => 'student'
], $token);

if ($result['code'] === 201 && isset($result['body']['data']['id'])) {
    $testUserId = $result['body']['data']['id'];
    echo "✓ Created user ID: $testUserId\n";
} else {
    echo "✗ FAILED (HTTP {$result['code']})\n";
    print_r($result['body']);
    exit(1);
}

// Test 5: Verify access with a test card
echo "[5/6] Testing access verification... ";
$testCardUid = 'TEST' . bin2hex(random_bytes(8));
$result = httpRequest('POST', '/api/verify-access', [
    'card_uid' => $testCardUid,
    'door_location' => 'test_door',
    'device_info' => 'test_script'
], null, $apiKey);

if ($result['code'] === 200) {
    $accessGranted = $result['body']['access_granted'] ? 'GRANTED' : 'DENIED';
    $reason = $result['body']['reason'];
    echo "✓ $accessGranted ($reason)\n";
} else {
    echo "✗ FAILED (HTTP {$result['code']})\n";
    print_r($result['body']);
    exit(1);
}

// Test 6: Get access logs
echo "[6/6] Testing access logs... ";
$result = httpRequest('GET', '/api/access-logs', null, $token);
if ($result['code'] === 200 && isset($result['body']['data'])) {
    $logCount = count($result['body']['data']);
    echo "✓ Found $logCount log entries\n";
} else {
    echo "✗ FAILED (HTTP {$result['code']})\n";
    print_r($result['body']);
    exit(1);
}

echo "\n=== All tests completed successfully! ===\n\n";
echo "API Base URL: $baseUrl\n";
echo "Admin Panel: $baseUrl/admin (use the admin credentials to log in)\n\n";
echo "Test user created:\n";
echo "- Email: $testUserEmail\n";
echo "- Password: test1234\n";
echo "- Role: student\n\n";

echo "You can now use the API with the following token for authenticated requests:\n";
echo "Authorization: Bearer $token\n\n";
echo "Example curl command to get users:\n";
echo "curl -H \"Authorization: Bearer $token\" $baseUrl/api/users\n\n";

echo "To clean up test data, you can delete the test user with:\n";
echo "curl -X DELETE -H \"Authorization: Bearer $token\" $baseUrl/api/users/$testUserId\n\n";
