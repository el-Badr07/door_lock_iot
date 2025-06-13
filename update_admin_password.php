<?php

require_once __DIR__ . '/api/config/Database.php';

// Load environment variables for this script to ensure database connection
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = explode("
", file_get_contents($envFile));
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        // Robustly remove quotes and then trim whitespace
        $value = str_replace(['"', "'"], '', $value);
        $value = trim($value);
        putenv("$key=$value");
        $_ENV[$key] = $value;
    }
}

try {
    $db = Database::getInstance()->getConnection();
    
    $adminEmail = 'admin@example.com';
    $newPassword = 'admin123';
    $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    
    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
    $stmt->execute([$newPasswordHash, $adminEmail]);
    
    if ($stmt->rowCount() > 0) {
        echo "Admin user password updated successfully to: $newPassword
";
        echo "New hash: $newPasswordHash
";
    } else {
        echo "Admin user ( $adminEmail ) not found or password already updated.
";
    }
    
} catch (Exception $e) {
    echo "Error updating admin password: " . $e->getMessage() . "
";
}

?> 