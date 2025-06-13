<?php
/**
 * Database Initialization Script
 * 
 * This script initializes the database with the required schema and an admin user.
 * It should be run once during the initial setup.
 */

// Load environment variables - already handled by init.php
// No need to parse .env again here, variables are already in environment

// Database connection parameters
$dbConfig = [
    'host' => getenv('DB_HOST'),
    'port' => getenv('DB_PORT') ?: '5432',
    'dbname' => 'postgres', // Connect to default postgres database first
    'user' => getenv('DB_USER'),
    'password' => getenv('DB_PASSWORD')
];

// Create database if it doesn't exist
try {
    echo "Connecting to PostgreSQL server...\n";
    $dsn = "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']}";
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if database exists
    $dbName = getenv('DB_NAME');
    echo "Checking if database '$dbName' exists...\n";
    
    $stmt = $pdo->query("SELECT 1 FROM pg_database WHERE datname = '$dbName'");
    $dbExists = $stmt->fetchColumn() !== false;
    
    if (!$dbExists) {
        echo "Creating database '$dbName'...\n";
        $pdo->exec("CREATE DATABASE \"$dbName\"");
        echo "Database created successfully.\n";
    } else {
        echo "Database already exists.\n";
    }
    
    // Connect to the new database
    echo "Connecting to database '$dbName'...\n";
    $pdo = new PDO(
        "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname=$dbName",
        $dbConfig['user'],
        $dbConfig['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Run migrations
    echo "Running migrations...\n";
    require_once __DIR__ . '/run_migrations.php';
    
    echo "\nDatabase initialization complete!\n";
    echo "You can now access the application at http://localhost\n";
    echo "Admin credentials:\n";
    echo "  Email: admin@example.com\n";
    echo "  Password: admin123\n";
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage() . "\n");
}
