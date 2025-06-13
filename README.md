# IoT Door Access Control System

A secure, lightweight door access control system using RFID cards with a PHP backend and PostgreSQL database. This system provides simple and reliable access control for buildings, offices, or any secured area.

## 🚀 Features

- **Secure Authentication**: JWT-based admin authentication
- **RFID Card Management**: Register, activate/deactivate, and manage RFID cards
- **Simple Access Control**: Card-based access without time restrictions
- **Real-time Logging**: Track all access attempts with detailed logs
- **User Management**: Admin dashboard for managing users and their cards
- **RESTful API**: Easy integration with ESP32 and other IoT devices
- **Docker Support**: Containerized for easy deployment and scaling

## 🏗️ System Architecture

```
┌─────────────┐    ┌─────────────┐    ┌─────────────┐
│   ESP32     │    │  PHP/Apache │    │ PostgreSQL  │
│ RFID Reader │◄──►│   Backend   │◄──►│  Database   │
└─────────────┘    └─────────────┘    └─────────────┘
       │                    │                 │
       │            ┌─────────────┐           │
       └───────────►│ React Frontend      │◄──┘
                    │ (Optional)          │
                    └─────────────┘
```

## 📋 Prerequisites

- **Docker** and **Docker Compose** 
- **curl** or **Postman** (for API testing)

## 🚀 Quick Start

### 1. Clone and Setup

```bash
git clone <repository-url>
cd door_lock_iot
```

### 2. Start the System

```bash
# Start all services
docker-compose up -d

# Check status
docker-compose ps
```



This starts:
- **PostgreSQL** database on port `5432`
- **PHP/Apache** backend on port `80`
- **Adminer** database UI on port `8080`

### 3. Initialize System

The system automatically:
- Generates secure environment variables
- Runs database migrations
- Creates admin user with default credentials


frontend :
cd frontend 
npm install 
npm start

go to http://localhost:3000

### 4. Test the System

```bash
# Test API health
curl http://localhost/api/health

# Test admin login
curl -X POST -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"admin123"}' \
  http://localhost/api/login
```

## 🔑 Default Credentials

**Admin User:**
- Email: `admin@example.com`
- Password: `admin123`


## 📡 API Usage

### Authentication

```bash
# Login to get JWT token
curl -X POST -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"admin123"}' \
  http://localhost/api/login
```

### Access Verification (ESP32)

```bash
# Test card access
curl -X POST -H "Content-Type: application/json" \
  -d '{"card_uid":"1234567890"}' \
  http://localhost/api/verify-access
```

### User Management

```bash
# Get all users (requires JWT token)
curl -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  http://localhost/api/users

# Create new user
curl -X POST -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"John Doe","email":"john@example.com","password":"password123","role":"student","card_uid":"ABCD1234"}' \
  http://localhost/api/users
```

## 🗄️ Database Access

**Adminer Web Interface:**
- URL: http://localhost:8080
- System: PostgreSQL
- Server: `db`
- Username: `admin`
- Password: `securepassword`
- Database: `door_access`

## 📁 Project Structure

```
door_lock_iot/
├── 📂 api/                    # PHP Backend
│   ├── 📂 config/             # Database & Response classes
│   ├── 📂 handlers/           # API route handlers
│   ├── 📂 models/             # Data models (future use)
│   └── 📂 utils/              # Utilities (Auth, etc.)
├── 📂 database/               # Database files
│   └── 📂 migrations/         # SQL migration files
├── 📂 frontend/               # React frontend (optional)
├── 📂 public/                 # Web server root
│   └── 📄 index.php           # Main API entry point
├── 📄 .env                    # Environment variables
├── 📄 docker-compose.yml      # Docker services
├── 📄 Dockerfile              # PHP/Apache container
├── 📄 init.php                # System initialization
└── 📄 test_api.php            # API testing script
```

## 🔧 Configuration

### Environment Variables

The system automatically generates secure values in `.env`:

```env
# Database Configuration
DB_HOST=db
DB_NAME=door_access
DB_USER=admin
DB_PASSWORD=securepassword

# Application Security
API_KEY=auto_generated_secure_key
JWT_SECRET=auto_generated_jwt_secret
APP_KEY=auto_generated_app_key

# Application Settings
APP_ENV=development
APP_DEBUG=true
```

### Access Control Logic

Access is granted when:
1. ✅ Card exists in database
2. ✅ Card is active (`is_active = true`)
3. ✅ User account is active (`status = 'active'`)

**No time restrictions** - Access works 24/7 for valid cards.

## 🧪 Testing

### Automated Testing

```bash
# Run complete API test suite
docker-compose exec backend php test_api.php
```

### Manual Testing

```bash
# Test unregistered card (should be denied)
curl -X POST -H "Content-Type: application/json" \
  -d '{"card_uid":"UNKNOWN123"}' \
  http://localhost/api/verify-access

# Expected response:
# {"access_granted":false,"user":null,"reason":"Card not registered","timestamp":"..."}

# Test egistered card (should be denied)
curl -X POST -H "Content-Type: application/json" \
  -d '{"card_uid":"9876543210"}' \
  http://localhost/api/verify-access

```

## 🔒 Security Features

- **JWT Authentication**: Secure admin session management
- **Input Validation**: All inputs are validated and sanitized
- **SQL Injection Protection**: Prepared statements throughout
- **Password Hashing**: Secure bcrypt password storage
- **Environment Variables**: Sensitive data stored securely
- **CORS Headers**: Configurable cross-origin access


### Debugging

```bash
# View application logs
docker-compose logs backend

# View database logs
docker-compose logs db

# Access container shell
docker-compose exec backend bash
```
