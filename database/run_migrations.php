<?php

// Load environment variables - already handled by init.php
// No need to parse .env again here, variables are already in environment

// Database connection parameters
$dbConfig = [
    'host' => getenv('DB_HOST'),
    'port' => getenv('DB_PORT') ?: '5432',
    'dbname' => getenv('DB_NAME'),
    'user' => getenv('DB_USER'),
    'password' => getenv('DB_PASSWORD')
];

// Create database connection
try {
    $dsn = "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']}";
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to database successfully.\n";
    
    // Create migrations table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS migrations (
        id SERIAL PRIMARY KEY,
        migration VARCHAR(255) NOT NULL,
        batch INTEGER NOT NULL,
        executed_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Get all migration files
    $migrationFiles = glob(__DIR__ . '/migrations/*.sql');
    sort($migrationFiles);
    
    // Get already executed migrations
    $stmt = $pdo->query("SELECT migration FROM migrations");
    $executedMigrations = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $batch = 1;
    if (!empty($executedMigrations)) {
        $batch = $pdo->query("SELECT MAX(batch) FROM migrations")->fetchColumn() + 1;
    }
    
    $migrationsRun = 0;
    
    // Run new migrations
    foreach ($migrationFiles as $file) {
        $migrationName = basename($file);
        
        if (!in_array($migrationName, $executedMigrations)) {
            echo "Running migration: $migrationName\n";
            
            // Read and execute SQL file
            $sql = file_get_contents($file);
            
            try {
                $pdo->beginTransaction();
                $pdo->exec($sql);
                
                // Record migration
                $stmt = $pdo->prepare("INSERT INTO migrations (migration, batch) VALUES (?, ?)");
                $stmt->execute([$migrationName, $batch]);
                
                $pdo->commit();
                $migrationsRun++;
                echo "Migration $migrationName completed successfully.\n";
            } catch (PDOException $e) {
                $pdo->rollBack();
                echo "Error running migration $migrationName: " . $e->getMessage() . "\n";
                exit(1);
            }
        }
    }
    
    if ($migrationsRun > 0) {
        echo "\nSuccessfully ran $migrationsRun migration(s).\n";
    } else {
        echo "\nNo new migrations to run.\n";
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    exit(1);
}
