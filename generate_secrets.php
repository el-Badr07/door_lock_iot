<?php
// Generate secure random strings for secrets
echo "Generate these secure values and add them to your .env file:\n\n";

// Generate a secure API key (32 characters)
$apiKey = bin2hex(random_bytes(16));
echo "API_KEY=$apiKey\n";

// Generate a secure JWT secret (64 characters)
$jwtSecret = bin2hex(random_bytes(32));
echo "JWT_SECRET=$jwtSecret\n";

// Generate an app key (base64 encoded 32 random bytes)
$appKey = 'base64:' . base64_encode(random_bytes(32));
echo "APP_KEY=$appKey\n\n";

echo "Copy these values to your .env file and keep them secure!\n";
echo "Never commit your .env file to version control.\n";
