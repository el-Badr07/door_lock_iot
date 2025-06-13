<?php
/**
 * Test database connection script
 * 
 * This script tests the database connection and displays the current time from the database.
 * It's useful for verifying that the database is properly set up and accessible.
 */

// Load environment variables
$dotenv = parse_ini_file(__DIR__ . '/.env');
foreach ($dotenv as $key => $value) {
    putenv("$key=$value");
}

// Database connection parameters
$dbConfig = [
    'host' => getenv('DB_HOST'),
    'port' => getenv('DB_PORT') ?: '5432',
    'dbname' => getenv('DB_NAME'),
    'user' => getenv('DB_USER'),
    'password' => getenv('DB_PASSWORD')
];

echo "=== Database Connection Test ===\n\n";

try {
    // Attempt to connect to the database
    $dsn = "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']}";
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Successfully connected to database: {$dbConfig['dbname']}\n";
    
    // Get database version
    $version = $pdo->query('SELECT version()')->fetchColumn();
    echo "📊 Database version: $version\n";
    
    // Get current time from database
    $currentTime = $pdo->query('SELECT NOW()')->fetchColumn();
    echo "⏰ Database time: $currentTime\n";
    
    // Check if tables exist
    $tables = [
        'users' => 'Users table',
        'rfid_cards' => 'RFID Cards table',
        'access_logs' => 'Access Logs table',
        'schedules' => 'Schedules table'
    ];
    
    echo "\n=== Checking Database Tables ===\n";
    
    $allTablesExist = true;
    foreach ($tables as $table => $description) {
        $stmt = $pdo->prepare("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = ?)");
        $stmt->execute([$table]);
        $exists = $stmt->fetchColumn();
        
        if ($exists) {
            echo "✅ $description exists\n";
        } else {
            echo "❌ $description is missing\n";
            $allTablesExist = false;
        }
    }
    
    if ($allTablesExist) {
        echo "\n✅ All required tables exist\n";
    } else {
        echo "\n❌ Some tables are missing. You may need to run the database migrations.\n";
        echo "   Run: docker-compose exec backend php database/run_migrations.php\n";
    }
    
    // Check if admin user exists
    $stmt = $pdo->prepare("SELECT id, email, role FROM users WHERE email = ?");
    $stmt->execute(['admin@example.com']);
    $adminUser = $stmt->fetch();
    
    echo "\n=== Checking Admin User ===\n";
    
    if ($adminUser) {
        echo "✅ Admin user exists (ID: {$adminUser['id']}, Email: {$adminUser['email']}, Role: {$adminUser['role']})\n";
        echo "   You can log in with email: admin@example.com and password: admin123\n";
    } else {
        echo "❌ Admin user not found. The database may not be properly initialized.\n";
        echo "   Try running the database initialization script.\n";
    }
    
    echo "\n=== Test Completed Successfully ===\n";
    
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n\n";
    
    // Provide troubleshooting tips
    echo "Troubleshooting tips:\n";
    echo "1. Make sure the database service is running\n";
    echo "   Run: docker-compose up -d db\n";
    echo "2. Check your .env file for correct database credentials\n";
    echo "3. Verify the database container is healthy: docker-compose ps\n";
    echo "4. View database logs: docker-compose logs db\n\n";
    
    exit(1);
}

// Test API key verification
$apiKey = getenv('API_KEY');
if (empty($apiKey) || $apiKey === 'your_secure_api_key_for_esp32') {
    echo "\n⚠️  WARNING: Default API key detected. Please change the API_KEY in your .env file.\n";
    echo "   Run: php generate_secrets.php to generate a secure API key.\n";
} else {
    echo "\n🔑 API key is set (first 8 chars shown): " . substr($apiKey, 0, 8) . "...\n";
}

// Test JWT secret
$jwtSecret = getenv('JWT_SECRET');
if (empty($jwtSecret) || $jwtSecret === 'your_jwt_secret_key_here') {
    echo "⚠️  WARNING: Default JWT secret detected. Please change the JWT_SECRET in your .env file.\n";
    echo "   Run: php generate_secrets.php to generate a secure JWT secret.\n";
} else {
    echo "🔐 JWT secret is set\n";
}

echo "\nTo start the application, run: docker-compose up -d\n";
echo "Then visit: http://localhost in your browser\n\n";
