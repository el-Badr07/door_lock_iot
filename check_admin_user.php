<?php

require_once __DIR__ . '/api/config/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("SELECT id, email, password_hash, status, role FROM users WHERE email = ?");
    $stmt->execute(['admin@example.com']);
    $adminUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($adminUser) {
        echo "Admin User Found:\n";
        echo "ID: " . $adminUser['id'] . "\n";
        echo "Email: " . $adminUser['email'] . "\n";
        echo "Password Hash: " . $adminUser['password_hash'] . "\n";
        echo "Status: " . $adminUser['status'] . "\n";
        echo "Role: " . $adminUser['role'] . "\n";
    } else {
        echo "Admin user (admin@example.com) not found in the database.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

?> 