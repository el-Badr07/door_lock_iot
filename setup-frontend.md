# Frontend Setup Guide

## Quick Start

1. **Install Dependencies**:
   ```bash
   cd frontend
   npm install
   ```

2. **Start Development Server**:
   ```bash
   npm start
   ```

3. **Open in Browser**:
   - Frontend: http://localhost:3000
   - Login: admin@example.com / admin123

## Backend Connection

The frontend is configured to connect to your PHP backend at `http://localhost`. Make sure your Docker backend is running:

```bash
# In the main door_lock_iot directory
docker-compose up -d
```

## Features Ready to Test

### ✅ Dashboard
- Real-time statistics (users, access attempts)
- Recent access logs with live updates
- Auto-refresh every 30 seconds
- Quick action buttons

### ✅ User Management  
- ➕ Add users with RFID cards
- ✏️ Edit user details (name, email, role, status)
- 🗑️ Delete users (with confirmation)
- 💳 View user's RFID cards
- 🔄 Real-time updates

### ✅ Access Logs
- 📊 Statistics (total, granted, denied attempts)
- 🔍 Search by card UID, user name, or reason
- 🎯 Filter by access status (all/granted/denied)
- 📄 Pagination for large datasets
- 🔄 Real-time refresh

### ✅ Authentication
- 🔐 Secure JWT-based login
- 🛡️ Protected routes
- 💾 Session persistence
- 🚪 Secure logout

## API Endpoints Used

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/login` | POST | Admin authentication |
| `/api/users` | GET | List all users |
| `/api/users` | POST | Create new user |
| `/api/users/{id}` | PUT | Update user |
| `/api/users/{id}` | DELETE | Delete user |
| `/api/users/{id}/cards` | GET | Get user's RFID cards |
| `/api/access-logs` | GET | Fetch access logs |
| `/api/health` | GET | System health check |

## Testing the Complete System

### 1. Test User Management
```bash
# Add a new user through the frontend
# This should create both user and RFID card in database
```

### 2. Test RFID Access
```bash
# Simulate ESP32 request
curl -X POST -H "Content-Type: application/json" \
  -d '{"card_uid": "YOUR_CARD_UID"}' \
  http://localhost/api/verify-access
```

### 3. Check Dashboard Updates
- Add users → Should see user count increase
- Test RFID access → Should see attempts in recent logs
- All updates happen in real-time

## Common Issues & Solutions

### Frontend won't start
```bash
# Clear npm cache and reinstall
npm cache clean --force
rm -rf node_modules package-lock.json
npm install
```

### Backend connection errors
- Ensure Docker backend is running: `docker-compose ps`
- Check backend health: `curl http://localhost/api/health`
- Verify CORS headers are enabled

### Login issues
- Default credentials: admin@example.com / admin123
- Check backend logs: `docker-compose logs backend`
- Verify JWT_SECRET is set in backend

## Production Deployment

### Build Frontend
```bash
npm run build
```

### Serve Static Files
The `build/` folder contains optimized static files that can be served by:
- Nginx
- Apache
- Any static file server

### Environment Variables
Create `.env` in frontend directory:
```
REACT_APP_API_URL=https://your-backend-domain.com
```

## Next Steps

1. **Install frontend dependencies**: `cd frontend && npm install`
2. **Start frontend**: `npm start`
3. **Test all features** in the browser
4. **Simulate ESP32 requests** with curl
5. **Monitor real-time updates** in dashboard

The frontend is now fully connected to your backend and ready for production use! 