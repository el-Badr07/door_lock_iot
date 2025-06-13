# IoT Door Access Control System - Technical Documentation

## Table of Contents

1. [System Overview](#system-overview)
2. [Architecture](#architecture)
3. [Database Schema](#database-schema)
4. [API Reference](#api-reference)
5. [Backend Components](#backend-components)
6. [Security Implementation](#security-implementation)
7. [Configuration](#configuration)
8. [Deployment](#deployment)
9. [Testing](#testing)
10. [Troubleshooting](#troubleshooting)
11. [Development Guide](#development-guide)

---

## System Overview

The IoT Door Access Control System is a lightweight, secure solution for managing physical access using RFID cards. Built with PHP and PostgreSQL, it provides a RESTful API for ESP32 integration and web-based management.

### Key Features

- **Card-based Authentication**: RFID card verification without time restrictions
- **User Management**: Admin interface for managing users and their access cards
- **Access Logging**: Complete audit trail of all access attempts
- **JWT Security**: Secure admin authentication with JSON Web Tokens
- **Docker Support**: Containerized deployment for easy setup and scaling

### Design Principles

- **Simplicity**: Minimal configuration, maximum functionality
- **Security**: Industry-standard authentication and validation
- **Reliability**: Robust error handling and transaction safety
- **Scalability**: Designed for easy horizontal scaling

---

## Architecture

### System Components

```
┌─────────────────────────────────────────────────────────────┐
│                    IoT Door Access System                  │
├─────────────────────────────────────────────────────────────┤
│  Hardware Layer                                             │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐        │
│  │   ESP32     │  │ RFID Reader │  │ Door Lock   │        │
│  │ Controller  │  │   (RC522)   │  │ (Magnetic)  │        │
│  └─────────────┘  └─────────────┘  └─────────────┘        │
├─────────────────────────────────────────────────────────────┤
│  Network Layer                                              │
│  ┌─────────────────────────────────────────────────────────┐│
│  │              HTTP/HTTPS Communication                   ││
│  └─────────────────────────────────────────────────────────┘│
├─────────────────────────────────────────────────────────────┤
│  Application Layer                                          │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐        │
│  │    React    │  │ PHP/Apache  │  │ PostgreSQL  │        │
│  │  Frontend   │  │   Backend   │  │  Database   │        │
│  │ (Optional)  │  │             │  │             │        │
│  └─────────────┘  └─────────────┘  └─────────────┘        │
└─────────────────────────────────────────────────────────────┘
```

### Technology Stack

- **Backend**: PHP 8.x with Apache
- **Database**: PostgreSQL 13+
- **Authentication**: JWT (JSON Web Tokens)
- **Containerization**: Docker & Docker Compose
- **Frontend**: React (optional, for admin interface)
- **Hardware**: ESP32 with RC522 RFID reader

### Data Flow

1. **RFID Card Scan**: ESP32 reads card UID from RFID reader
2. **API Request**: ESP32 sends POST request to `/api/verify-access`
3. **Validation**: Backend validates card against database
4. **Response**: API returns access decision and logs attempt
5. **Action**: ESP32 controls door lock based on response

---

## Database Schema

### Entity Relationship Diagram

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│     USERS       │     │   RFID_CARDS    │     │  ACCESS_LOGS    │
├─────────────────┤     ├─────────────────┤     ├─────────────────┤
│ id (PK)         │────┐│ id (PK)         │     │ id (PK)         │
│ name            │    └│ user_id (FK)    │     │ user_id (FK)    │
│ email (UNIQUE)  │     │ card_uid (UNIQUE)│────┐│ card_uid        │
│ password_hash   │     │ is_active       │    ││ access_time     │
│ role            │     │ registered_at   │    ││ access_granted  │
│ status          │     │ last_used_at    │    ││ access_granted  │
│ created_at      │     │ notes           │    │└─────────────────┘
│ updated_at      │     └─────────────────┘    │
└─────────────────┘                            │
                                               │
            ┌──────────────────────────────────┘
            │
            └─── Links card_uid to access logs
```

### Table Definitions

#### users
```sql
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'student',
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_role CHECK (role IN ('admin', 'student')),
    CONSTRAINT chk_status CHECK (status IN ('active', 'inactive', 'suspended'))
);
```

**Fields:**
- `id`: Primary key, auto-incrementing
- `name`: User's full name
- `email`: Unique email address for login
- `password_hash`: Bcrypt hashed password
- `role`: Either 'admin' or 'student'
- `status`: 'active', 'inactive', or 'suspended'
- `created_at`, `updated_at`: Timestamp tracking

#### rfid_cards
```sql
CREATE TABLE rfid_cards (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    card_uid VARCHAR(50) UNIQUE NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    registered_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP WITH TIME ZONE,
    notes TEXT
);
```

**Fields:**
- `id`: Primary key, auto-incrementing
- `user_id`: Foreign key to users table
- `card_uid`: Unique RFID card identifier
- `is_active`: Whether card is currently active
- `registered_at`: When card was first registered
- `last_used_at`: Last successful access time
- `notes`: Optional notes about the card

#### access_logs
```sql
CREATE TABLE access_logs (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    card_uid VARCHAR(50) NOT NULL,
    access_time TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    access_granted BOOLEAN NOT NULL,
    failure_reason VARCHAR(255)
);
```

**Fields:**
- `id`: Primary key, auto-incrementing
- `user_id`: Foreign key to users (NULL if card not registered)
- `card_uid`: RFID card that was scanned
- `access_time`: When access was attempted
- `access_granted`: Whether access was granted
- `failure_reason`: Reason for denial (if applicable)

### Indexes

```sql
-- Performance optimization indexes
CREATE INDEX idx_rfid_cards_card_uid ON rfid_cards(card_uid);
CREATE INDEX idx_access_logs_access_time ON access_logs(access_time);
CREATE INDEX idx_access_logs_card_uid ON access_logs(card_uid);
```

---

## API Reference

### Base URL
- **Development**: `http://localhost`
- **Production**: `https://your-domain.com`

### Authentication

Most endpoints require JWT authentication via the `Authorization` header:
```
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

### Response Format

All API responses follow this structure:

**Success Response:**
```json
{
  "success": true,
  "data": { ... },
  "message": "Optional success message"
}
```

**Error Response:**
```json
{
  "success": false,
  "error": {
    "message": "Error description",
    "details": ["Field-specific errors..."]
  }
}
```

### Endpoints

#### 1. Health Check
**GET** `/api/health`

Check if the API is operational.

**Response:**
```json
{
  "status": "healthy",
  "timestamp": "2025-06-11T13:35:38+00:00",
  "version": "1.2.0"
}
```

#### 2. Authentication

##### Login
**POST** `/api/login`

Authenticate admin user and receive JWT token.

**Request:**
```json
{
  "email": "admin@example.com",
  "password": "admin123"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "user": {
      "id": 1,
      "name": "Admin User",
      "email": "admin@example.com",
      "role": "admin"
    }
  }
}
```

#### 3. Access Verification

##### Verify Card Access
**POST** `/api/verify-access`

**No authentication required** - used by ESP32 devices.

**Request:**
```json
{
  "card_uid": "1234567890"
}
```

**Success Response:**
```json
{
  "access_granted": true,
  "user": {
    "id": 2,
    "name": "John Doe",
    "role": "student"
  },
  "reason": "Access granted",
  "timestamp": "2025-06-11T13:35:38+00:00"
}
```

**Failure Response:**
```json
{
  "access_granted": false,
  "user": null,
  "reason": "Card not registered",
  "timestamp": "2025-06-11T13:35:38+00:00"
}
```

#### 4. User Management

##### Get All Users
**GET** `/api/users`

**Authentication:** Required (Admin only)

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Admin User",
      "email": "admin@example.com",
      "role": "admin",
      "status": "active",
      "created_at": "2025-06-11T10:00:00+00:00",
      "card_count": 1,
      "last_access": "2025-06-11T13:30:00+00:00"
    }
  ]
}
```

##### Create User
**POST** `/api/users`

**Authentication:** Required (Admin only)

**Request:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "securepassword123",
  "role": "student",
  "card_uid": "ABCD1234"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 5,
      "name": "John Doe",
      "email": "john@example.com",
      "role": "student",
      "status": "active"
    },
    "card": {
      "id": 3,
      "card_uid": "ABCD1234",
      "is_active": true
    }
  }
}
```

##### Update User
**PUT** `/api/users/{id}`

**Authentication:** Required

**Request:**
```json
{
  "name": "John Smith",
  "email": "john.smith@example.com",
  "role": "student",
  "status": "active",
  "password": "newpassword123"  // Optional
}
```

##### Delete User
**DELETE** `/api/users/{id}`

**Authentication:** Required (Admin only)

**Response:**
```json
{
  "success": true,
  "message": "User deleted successfully"
}
```

#### 5. Card Management

##### Get User Cards
**GET** `/api/users/{user_id}/cards`

**Authentication:** Required

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "card_uid": "ABCD1234",
      "is_active": true,
      "registered_at": "2025-06-11T10:00:00+00:00",
      "last_used_at": "2025-06-11T13:30:00+00:00",
      "notes": "Primary access card"
    }
  ]
}
```

##### Add Card to User
**POST** `/api/users/{user_id}/cards`

**Authentication:** Required

**Request:**
```json
{
  "card_uid": "EFGH5678",
  "is_active": true,
  "notes": "Backup card"
}
```

##### Update Card
**PUT** `/api/users/{user_id}/cards/{card_id}`

**Authentication:** Required

**Request:**
```json
{
  "card_uid": "EFGH5678",
  "is_active": false,
  "notes": "Deactivated - lost card"
}
```

##### Delete Card
**DELETE** `/api/users/{user_id}/cards/{card_id}`

**Authentication:** Required

#### 6. Access Logs

##### Get Access Logs
**GET** `/api/access-logs`

**Authentication:** Required (Admin only)

**Query Parameters:**
- `limit`: Number of records (default: 100)
- `offset`: Pagination offset (default: 0)
- `user_id`: Filter by user ID
- `from_date`: Filter from date (YYYY-MM-DD)
- `to_date`: Filter to date (YYYY-MM-DD)

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "user_name": "John Doe",
      "card_uid": "ABCD1234",
      "access_time": "2025-06-11T13:30:00+00:00",
      "access_granted": true,
      "failure_reason": null
    }
  ],
  "total": 150,
  "pagination": {
    "limit": 100,
    "offset": 0,
    "has_more": true
  }
}
```

---

## Backend Components

### File Structure

```
api/
├── config/
│   ├── Database.php          # Database connection singleton
│   └── Response.php          # Standardized API responses
├── handlers/
│   ├── access.php           # Access verification logic
│   ├── auth.php             # Authentication endpoints
│   ├── health.php           # Health check endpoint
│   ├── logs.php             # Access logs management
│   └── users.php            # User and card management
├── models/
│   └── (future model classes)
└── utils/
    └── Auth.php             # JWT authentication utility
```

### Core Classes

#### Database.php
Singleton pattern for database connections with connection pooling.

```php
class Database {
    private static $instance = null;
    private $connection;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        // Returns PDO connection with proper error handling
    }
}
```

#### Response.php
Standardizes all API responses for consistency.

```php
class Response {
    public static function json($data, $statusCode = 200) {
        http_response_code($statusCode);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    public static function error($message, $statusCode = 400, $details = []) {
        // Standardized error responses
    }
}
```

#### Auth.php
JWT token generation and validation.

```php
class Auth {
    public static function generateToken($user) {
        // Creates JWT with user claims
    }
    
    public static function validateToken($token) {
        // Validates and decodes JWT
    }
    
    public static function requireAuth() {
        // Middleware for protected endpoints
    }
}
```

### Request Flow

1. **Request Routing**: `public/index.php` parses URL and routes to handlers
2. **Authentication**: Protected endpoints validate JWT tokens
3. **Input Validation**: All inputs are sanitized and validated
4. **Database Operations**: Use prepared statements for security
5. **Response**: Standardized JSON responses via Response class

### Error Handling

- **Database Errors**: Transactions with rollback on failure
- **Validation Errors**: Field-specific error messages
- **Authentication Errors**: Proper HTTP status codes
- **Logging**: All errors logged for debugging

---

## Security Implementation

### Authentication & Authorization

#### JWT Implementation
- **Algorithm**: HS256 (HMAC SHA-256)
- **Expiration**: 24 hours
- **Claims**: user_id, email, role, exp, iat
- **Secret**: Generated randomly per installation

#### Role-Based Access Control
- **Admin**: Full access to all endpoints
- **Student**: Limited access to own user data
- **Anonymous**: Only access verification endpoint

### Input Validation

#### SQL Injection Prevention
```php
// All database queries use prepared statements
$stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
```

#### Cross-Site Scripting (XSS)
- All user inputs are escaped before database storage
- JSON responses automatically escape special characters
- Content-Type headers properly set

#### Request Validation
```php
// Example validation for user creation
$required = ['name', 'email', 'password', 'role'];
$missing = [];
foreach ($required as $field) {
    if (empty($input[$field])) {
        $missing[] = $field;
    }
}
```

### Password Security

#### Bcrypt Hashing
```php
// Password hashing
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

// Password verification
$valid = password_verify($password, $hash);
```

#### Password Requirements
- Minimum 8 characters
- Validated on both frontend and backend
- Hashed with bcrypt cost factor 12

### Environment Security

#### Sensitive Data
All sensitive configuration stored in environment variables:
- Database credentials
- JWT secret
- API keys
- Encryption keys

#### Generated Secrets
System automatically generates secure random values:
```php
$apiKey = bin2hex(random_bytes(32));      // 64-char hex
$jwtSecret = bin2hex(random_bytes(32));   // 64-char hex
$appKey = base64_encode(random_bytes(32)); // Base64 encoded
```

---

## Configuration

### Environment Variables

#### Required Variables
```env
# Database Configuration
DB_HOST=db                    # Database hostname
DB_NAME=door_access          # Database name
DB_USER=admin                # Database username
DB_PASSWORD=securepassword   # Database password

# Application Security
API_KEY=auto_generated       # API key for ESP32 (if needed)
JWT_SECRET=auto_generated    # JWT signing secret
APP_KEY=auto_generated       # Application encryption key

# Application Settings
APP_ENV=development          # Environment: development|production
APP_DEBUG=true               # Enable debug mode
```

#### Optional Variables
```env
# Logging
LOG_LEVEL=info              # Logging level
LOG_FILE=/var/log/app.log   # Log file path

# CORS
CORS_ORIGINS=*              # Allowed origins for CORS
CORS_METHODS=GET,POST,PUT,DELETE # Allowed HTTP methods

# Rate Limiting
RATE_LIMIT_REQUESTS=100     # Requests per minute
RATE_LIMIT_WINDOW=60        # Time window in seconds
```

### Docker Configuration

#### docker-compose.yml
```yaml
version: '3.8'

services:
  db:
    image: postgres:13-alpine
    environment:
      POSTGRES_DB: door_access
      POSTGRES_USER: admin
      POSTGRES_PASSWORD: securepassword
    volumes:
      - postgres_data:/var/lib/postgresql/data
    ports:
      - "5432:5432"
    restart: always

  backend:
    build: 
      context: .
      dockerfile: Dockerfile
    volumes:
      - ./:/var/www/html
    ports:
      - "80:80"
    depends_on:
      - db
    env_file:
      - ./.env
    environment:
      - DB_HOST=db
    restart: always

  adminer:
    image: adminer
    restart: always
    ports:
      - "8080:8080"
    depends_on:
      - db

volumes:
  postgres_data:
```

#### Dockerfile
```dockerfile
FROM php:8.0-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpq-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_pgsql mbstring exif pcntl bcmath gd

# Enable Apache modules
RUN a2enmod rewrite headers

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html

# Set permissions
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

# Initialize system on container start
CMD ["sh", "-c", "php init.php && apache2-foreground"]
```

---

## Deployment

### Development Deployment

#### Local Setup
```bash
# Clone repository
git clone <repo-url>
cd door_lock_iot

# Start services
docker-compose up -d

# Check status
docker-compose ps

# View logs
docker-compose logs -f backend
```

#### Testing
```bash
# Run API tests
docker-compose exec backend php test_api.php

# Test specific endpoint
curl http://localhost/api/health
```

### Production Deployment

#### Pre-deployment Checklist
- [ ] Update default admin credentials
- [ ] Configure HTTPS/SSL certificates
- [ ] Set production environment variables
- [ ] Configure firewall rules
- [ ] Set up database backups
- [ ] Configure monitoring
- [ ] Test all functionality

#### Environment Setup
```bash
# Production environment variables
APP_ENV=production
APP_DEBUG=false
DB_PASSWORD=secure_production_password

# Generate new secrets
API_KEY=$(openssl rand -hex 32)
JWT_SECRET=$(openssl rand -hex 32)
APP_KEY=$(openssl rand -base64 32)
```

#### Database Backup
```bash
# Create backup
docker-compose exec db pg_dump -U admin door_access > backup.sql

# Restore backup
docker-compose exec -T db psql -U admin door_access < backup.sql
```

#### SSL Configuration
For production, configure SSL termination:
- Use reverse proxy (nginx/Apache)
- Configure Let's Encrypt certificates
- Redirect HTTP to HTTPS
- Set secure headers

#### Monitoring Setup
Recommended monitoring tools:
- **Application**: New Relic, DataDog
- **Infrastructure**: Prometheus + Grafana
- **Logs**: ELK Stack, Fluentd
- **Uptime**: Pingdom, UptimeRobot

---

## Testing

### Test Suite

#### Automated Testing
The `test_api.php` script provides comprehensive API testing:

```bash
# Run full test suite
docker-compose exec backend php test_api.php
```

**Test Coverage:**
1. Health check endpoint
2. Admin authentication
3. User management (CRUD)
4. Card management
5. Access verification
6. Access logs retrieval

#### Manual Testing

##### Health Check
```bash
curl http://localhost/api/health
# Expected: {"status":"healthy","timestamp":"..."}
```

##### Authentication Flow
```bash
# 1. Login
TOKEN=$(curl -s -X POST -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"admin123"}' \
  http://localhost/api/login | jq -r '.data.token')

# 2. Use token for authenticated request
curl -H "Authorization: Bearer $TOKEN" \
  http://localhost/api/users
```

##### Access Verification
```bash
# Test unregistered card
curl -X POST -H "Content-Type: application/json" \
  -d '{"card_uid":"UNKNOWN123"}' \
  http://localhost/api/verify-access

# Expected: {"access_granted":false,"reason":"Card not registered"}
```

### Load Testing

#### Apache Bench
```bash
# Test concurrent access verification requests
ab -n 1000 -c 10 -p card_data.json -T application/json \
  http://localhost/api/verify-access
```

#### card_data.json
```json
{"card_uid":"1234567890"}
```

### Performance Benchmarks

#### Expected Performance
- **Access Verification**: < 100ms response time
- **User Authentication**: < 200ms response time
- **Database Queries**: < 50ms average
- **Concurrent Users**: 100+ simultaneous connections

---

## Troubleshooting

### Common Issues

#### 1. Database Connection Failed
**Symptoms:**
- "Database connection failed" errors
- 500 Internal Server Error

**Solutions:**
```bash
# Check database status
docker-compose ps

# Restart database
docker-compose restart db

# Check database logs
docker-compose logs db

# Verify environment variables
docker-compose exec backend env | grep DB_
```

#### 2. JWT Token Invalid
**Symptoms:**
- "Invalid token" errors
- 401 Unauthorized responses

**Solutions:**
```bash
# Check JWT secret
docker-compose exec backend env | grep JWT_SECRET

# Regenerate secrets
docker-compose exec backend php init.php

# Test new login
curl -X POST -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"admin123"}' \
  http://localhost/api/login
```

#### 3. Migration Errors
**Symptoms:**
- SQL errors during startup
- Missing tables or columns

**Solutions:**
```bash
# Check migration status
docker-compose exec backend ls -la database/migrations/

# Run migrations manually
docker-compose exec backend php database/run_migrations.php

# Reset database (WARNING: destroys data)
docker-compose down -v
docker-compose up -d
```

#### 4. CORS Issues
**Symptoms:**
- Frontend cannot access API
- "Access-Control-Allow-Origin" errors

**Solutions:**
Check CORS headers in `public/index.php`:
```php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
```

#### 5. ESP32 Connection Issues
**Symptoms:**
- ESP32 cannot reach API
- Network timeout errors

**Solutions:**
```bash
# Test from ESP32 network
curl -X POST -H "Content-Type: application/json" \
  -d '{"card_uid":"test123"}' \
  http://YOUR_SERVER_IP/api/verify-access

# Check firewall rules
sudo ufw status

# Verify Docker port binding
docker-compose ps
```

### Debug Mode

#### Enable Debug Logging
```env
APP_DEBUG=true
LOG_LEVEL=debug
```

#### View Logs
```bash
# Application logs
docker-compose logs -f backend

# Database logs
docker-compose logs -f db

# Apache access logs
docker-compose exec backend tail -f /var/log/apache2/access.log

# Apache error logs
docker-compose exec backend tail -f /var/log/apache2/error.log
```

### Performance Issues

#### Database Optimization
```sql
-- Check slow queries
SELECT query, mean_time, calls 
FROM pg_stat_statements 
ORDER BY mean_time DESC LIMIT 10;

-- Analyze table statistics
ANALYZE users;
ANALYZE rfid_cards;
ANALYZE access_logs;

-- Check index usage
SELECT schemaname, tablename, attname, n_distinct, correlation 
FROM pg_stats 
WHERE tablename IN ('users', 'rfid_cards', 'access_logs');
```

#### PHP Optimization
```bash
# Enable OPcache
docker-compose exec backend php -m | grep OPcache

# Check memory usage
docker-compose exec backend php -i | grep memory_limit

# Monitor processes
docker-compose exec backend ps aux
```

---

## Development Guide

### Adding New Features

#### 1. Database Changes
Create new migration file:
```bash
# Create new migration
touch database/migrations/004_add_new_feature.sql
```

Example migration:
```sql
-- Add new column to users table
ALTER TABLE users ADD COLUMN phone VARCHAR(20);

-- Create new table
CREATE TABLE user_sessions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    session_token VARCHAR(255) UNIQUE NOT NULL,
    expires_at TIMESTAMP WITH TIME ZONE NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Create index
CREATE INDEX idx_user_sessions_token ON user_sessions(session_token);
CREATE INDEX idx_user_sessions_expires ON user_sessions(expires_at);
```

#### 2. API Endpoints
Create new handler:
```bash
touch api/handlers/sessions.php
```

Example handler:
```php
<?php

function handleGetUserSessions($params) {
    $userId = $params['user_id'] ?? null;
    
    if (!$userId) {
        Response::error('User ID is required', 400);
    }
    
    $db = Database::getInstance()->getConnection();
    
    try {
        $stmt = $db->prepare("
            SELECT id, session_token, expires_at, created_at
            FROM user_sessions 
            WHERE user_id = ? AND expires_at > NOW()
            ORDER BY created_at DESC
        ");
        $stmt->execute([$userId]);
        $sessions = $stmt->fetchAll();
        
        Response::json(['sessions' => $sessions]);
        
    } catch (Exception $e) {
        error_log("Failed to fetch sessions: " . $e->getMessage());
        Response::error('Failed to fetch sessions', 500);
    }
}
```

#### 3. Update Routing
Add routes to `public/index.php`:
```php
// Include new handler
require_once __DIR__ . '/../api/handlers/sessions.php';

// Add routing cases
case $endpoint === 'users' && isset($uri[2]) && $uri[3] === 'sessions' && $requestMethod === 'GET':
    handleGetUserSessions(['user_id' => $uri[2]]);
    break;
```

#### 4. Testing
Add tests to `test_api.php`:
```php
// Test sessions endpoint
echo "Testing user sessions...\n";
$result = makeRequest('GET', "/api/users/{$testUserId}/sessions", $headers);
if ($result['status'] === 200) {
    echo "✓ Sessions retrieved successfully\n";
} else {
    echo "✗ Failed to get sessions: " . json_encode($result) . "\n";
}
```
