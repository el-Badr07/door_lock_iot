<?php
/**
 * Initialize the Door Lock IoT application
 * 
 * This script will:
 * 1. Check for required PHP extensions
 * 2. Verify .env file exists and is writable
 * 3. Generate secrets if needed
 * 4. Run database migrations
 */

// Check PHP version
if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    die("Error: PHP 8.0.0 or higher is required. Current version: " . PHP_VERSION . "\n");
}

// Check required extensions
$requiredExtensions = ['pdo', 'pdo_pgsql', 'json', 'mbstring', 'openssl'];
$missingExtensions = [];

foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingExtensions[] = $ext;
    }
}

if (!empty($missingExtensions)) {
    die("Error: The following PHP extensions are required but missing: " . implode(', ', $missingExtensions) . "\n");
}

// Check if .env file exists
$envFile = __DIR__ . '/../.env';
if (!file_exists($envFile)) {
    if (!copy(__DIR__ . '/.env.example', $envFile)) {
        die("Error: Could not create .env file. Please create it manually from .env.example\n");
    }
    echo "Created .env file from .env.example\n";
}

// Check if .env is writable
if (!is_writable($envFile)) {
    die("Error: .env file is not writable. Please check permissions.\n");
}

// Load environment variables
$envVars = [];

if (file_exists($envFile)) {
    $lines = explode("\n", file_get_contents($envFile));
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, "'\" \t\n\r\0\x0B"); // Trim quotes and whitespace
        $envVars[$key] = $value;
        putenv("$key=$value");
        $_ENV[$key] = $value;
    }
}

// Check if we need to generate secrets
$placeholderSecrets = [
    'API_KEY' => 'your_secure_api_key_for_esp32',
    'JWT_SECRET' => 'your_jwt_secret_key_here',
    'APP_KEY' => 'base64:randomstringof32charactershere12345'
];

$needsSecrets = false;
foreach ($placeholderSecrets as $key => $placeholder) {
    if (empty($envVars[$key]) || $envVars[$key] === $placeholder) {
        $needsSecrets = true;
        break;
    }
}

if ($needsSecrets) {
    echo "Generating secure secrets...\n";
    
    if (empty($envVars['API_KEY']) || $envVars['API_KEY'] === $placeholderSecrets['API_KEY']) {
        $envVars['API_KEY'] = bin2hex(random_bytes(16));
        echo "- Generated new API_KEY\n";
    }
    
    if (empty($envVars['JWT_SECRET']) || $envVars['JWT_SECRET'] === $placeholderSecrets['JWT_SECRET']) {
        $envVars['JWT_SECRET'] = bin2hex(random_bytes(32));
        echo "- Generated new JWT_SECRET\n";
    }
    
    if (empty($envVars['APP_KEY']) || $envVars['APP_KEY'] === $placeholderSecrets['APP_KEY'] || strpos($envVars['APP_KEY'], 'base64:') !== 0) {
        $envVars['APP_KEY'] = 'base64:' . base64_encode(random_bytes(32));
        echo "- Generated new APP_KEY\n";
    }
    
    // Update .env file with new values
    $newEnvContent = "";
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            $newEnvContent .= $line . "\n";
            continue;
        }
        
        list($key) = explode('=', $line, 2);
        $key = trim($key);
        
        // Preserve existing values if they are secrets and already set
        if (array_key_exists($key, $placeholderSecrets) && !empty($envVars[$key]) && $envVars[$key] !== $placeholderSecrets[$key]) {
            $newEnvContent .= "$key=\"{$envVars[$key]}\"\n"; // Ensure values are quoted
        } else if (isset($envVars[$key])) { // For other variables, just use the value directly without quoting
            $newEnvContent .= "$key={$envVars[$key]}\n";
        } else {
            $newEnvContent .= $line . "\n";
        }
    }
    
    // Add any newly generated secrets that weren't in the original file or were placeholders
    foreach ($placeholderSecrets as $secretKey => $placeholder) {
        // Only add if it was originally a placeholder or missing
        if (!isset($envVars[$secretKey]) || $envVars[$secretKey] === $placeholder) {
             $newEnvContent .= "$secretKey=\"{$envVars[$secretKey]}\"\n";
        }
    }
    
    file_put_contents($envFile, $newEnvContent);
    echo "Updated .env file with new secrets\n\n";
}

// Check if we're running in Docker
$isDocker = getenv('DOCKER_CONTAINER') === 'true' || file_exists('/.dockerenv');

if ($isDocker) {
    echo "Running inside Docker container...\n";
    
    // Wait for PostgreSQL to be ready
    $maxAttempts = 30;
    $attempt = 0;
    $dbConnected = false;
    
    echo "Waiting for database to be ready...\n";
    
    while ($attempt < $maxAttempts) {
        try {
            $db = new PDO(
                "pgsql:host=" . getenv('DB_HOST') . 
                ";port=" . (getenv('DB_PORT') ?: '5432') . 
                ";dbname=" . getenv('DB_NAME'),
                getenv('DB_USER'),
                getenv('DB_PASSWORD')
            );
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $dbConnected = true;
            break;
        } catch (PDOException $e) {
            $attempt++;
            if ($attempt % 5 === 0) {
                echo "  Attempt $attempt/$maxAttempts: " . $e->getMessage() . "\n";
            } else {
                echo ".";
            }
            sleep(1);
        }
    }
    
    if (!$dbConnected) {
        die("\nError: Could not connect to the database after $maxAttempts attempts.\n");
    }
    
    echo "\nDatabase connection successful!\n";
}

// Run database migrations
echo "\nRunning database migrations...\n";
require_once __DIR__ . '/database/run_migrations.php';

echo "\nInitialization complete!\n";
echo "You can now access the application at http://localhost\n";
echo "Admin credentials:\n";
echo "  Email: admin@example.com\n";
echo "  Password: admin123\n\n";

echo "Don't forget to change the default admin password after logging in!\n";
