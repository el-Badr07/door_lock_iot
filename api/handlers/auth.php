<?php

function handleLogin() {
    // Only allow POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::error('Method not allowed', 405);
    }
    
    // Get and validate input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['email']) || empty($input['password'])) {
        Response::error('Email and password are required', 400);
    }
    
    try {
        // Attempt to authenticate user
        $result = Auth::login($input['email'], $input['password']);
        
        if ($result === false) {
            Response::error('Invalid email or password', 401);
        }
        
        // Return token and user info
        Response::json([
            'token' => $result['token'],
            'user' => $result['user']
        ]);
        
    } catch (Exception $e) {
        error_log("Login failed: " . $e->getMessage());
        Response::error('Login failed', 500);
    }
}

// Middleware to verify JWT token and set user in request
function requireAuth() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    
    if (empty($authHeader)) {
        Response::error('Authorization header is missing', 401);
    }
    
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $token = $matches[1];
        $user = Auth::validateToken($token);
        
        if (!$user) {
            Response::error('Invalid or expired token', 401);
        }
        
        // Add user to request
        $_SERVER['CURRENT_USER'] = $user;
        return $user;
    }
    
    Response::error('Invalid authorization header format', 401);
}

// Middleware to require admin role
function requireAdmin() {
    $user = requireAuth();
    
    if ($user['role'] !== 'admin') {
        Response::error('Admin access required', 403);
    }
    
    return $user;
}
