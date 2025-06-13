<?php

function handleGetUsers() {
    $user = Auth::authenticate();
    
    // Only admin can list all users
    if ($user['role'] !== 'admin') {
        Response::error('Unauthorized', 403);
    }
    
    $db = Database::getInstance()->getConnection();
    
    try {
        // First query to get users
        $stmt = $db->query("
            SELECT 
                u.id, 
                u.name, 
                u.email, 
                u.role, 
                u.status, 
                u.created_at, 
                u.updated_at,
                (SELECT COUNT(*) FROM rfid_cards WHERE user_id = u.id) as card_count,
                (SELECT MAX(access_time) FROM access_logs WHERE user_id = u.id) as last_access
            FROM users u
            ORDER BY u.name
        ");
        
        $users = $stmt->fetchAll();
        
        Response::json($users);
        
    } catch (PDOException $e) {
        error_log("Failed to fetch users: " . $e->getMessage());
        Response::error('Failed to fetch users', 500);
    }
}

function handleCreateUser() {
    $user = Auth::authenticate();
    
    // Only admin can create users
    if ($user['role'] !== 'admin') {
        Response::error('Unauthorized', 403);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate input
    $errors = [];
    if (empty($input['name'])) {
        $errors[] = 'Name is required';
    }
    if (empty($input['email']) || !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required';
    }
    if (empty($input['password']) || strlen($input['password']) < 8) {
        $errors[] = 'Password must be at least 8 characters';
    }
    
    if (!empty($errors)) {
        Response::error('Validation failed', 400, $errors);
    }
    
    $db = Database::getInstance()->getConnection();
    
    try {
        // Start transaction
        $db->beginTransaction();
        
        // Check if email already exists
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$input['email']]);
        if ($stmt->fetch()) {
            $db->rollBack();
            Response::error('Email already in use', 409);
        }
        
        // Check if RFID card UID already exists (if provided)
        if (!empty($input['card_uid'])) {
            $stmt = $db->prepare("SELECT id FROM rfid_cards WHERE card_uid = ?");
            $stmt->execute([$input['card_uid']]);
            if ($stmt->fetch()) {
                $db->rollBack();
                Response::error('RFID card already registered', 409);
            }
        }
        
        // Hash password
        $passwordHash = password_hash($input['password'], PASSWORD_DEFAULT);
        
        // Insert user
        $stmt = $db->prepare("
            INSERT INTO users (name, email, password_hash, role, status)
            VALUES (?, ?, ?, ?, ?)
            RETURNING id, name, email, role, status, created_at
        ");
        
        $role = $input['role'] ?? 'student';
        $status = $input['status'] ?? 'active';
        
        $stmt->execute([
            $input['name'],
            $input['email'],
            $passwordHash,
            $role,
            $status
        ]);
        
        $newUser = $stmt->fetch();
        
        // Create RFID card if provided
        if (!empty($input['card_uid'])) {
            $stmt = $db->prepare("
                INSERT INTO rfid_cards (user_id, card_uid, is_active)
                VALUES (?, ?, TRUE)
            ");
            $stmt->execute([$newUser['id'], $input['card_uid']]);
        }
        
        $db->commit();
        Response::json($newUser, 201);
        
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Failed to create user: " . $e->getMessage());
        Response::error('Failed to create user', 500);
    }
}

function handleUpdateUser($userId) {
    $user = Auth::authenticate();
    
    // Only admin can update users, or users can update their own profile
    if ($user['role'] !== 'admin' && $user['user_id'] != $userId) {
        Response::error('Unauthorized', 403);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $db = Database::getInstance()->getConnection();
    
    try {
        // Check if user exists
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $existingUser = $stmt->fetch();
        
        if (!$existingUser) {
            Response::error('User not found', 404);
        }
        
        // Build update query
        $updates = [];
        $params = [];
        
        if (!empty($input['name'])) {
            $updates[] = 'name = ?';
            $params[] = $input['name'];
        }
        
        if (!empty($input['email']) && $input['email'] !== $existingUser['email']) {
            // Check if new email is already in use
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$input['email'], $userId]);
            if ($stmt->fetch()) {
                Response::error('Email already in use', 409);
            }
            $updates[] = 'email = ?';
            $params[] = $input['email'];
        }
        
        if (!empty($input['password'])) {
            if (strlen($input['password']) < 8) {
                Response::error('Password must be at least 8 characters', 400);
            }
            $updates[] = 'password_hash = ?';
            $params[] = password_hash($input['password'], PASSWORD_DEFAULT);
        }
        
        // Only admin can update role and status
        if ($user['role'] === 'admin') {
            if (isset($input['role'])) {
                $updates[] = 'role = ?';
                $params[] = $input['role'];
            }
            if (isset($input['status'])) {
                $updates[] = 'status = ?';
                $params[] = $input['status'];
            }
        }
        
        if (empty($updates)) {
            Response::error('No updates provided', 400);
        }
        
        $updates[] = 'updated_at = CURRENT_TIMESTAMP';
        $params[] = $userId;
        
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        // Fetch updated user
        $stmt = $db->prepare("SELECT id, name, email, role, status, created_at, updated_at FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $updatedUser = $stmt->fetch();
        
        Response::json($updatedUser);
        
    } catch (PDOException $e) {
        error_log("Failed to update user: " . $e->getMessage());
        Response::error('Failed to update user', 500);
    }
}

function handleDeleteUser($userId) {
    $user = Auth::authenticate();
    
    // Only admin can delete users
    if ($user['role'] !== 'admin') {
        Response::error('Unauthorized', 403);
    }
    
    // Prevent self-deletion
    if ($user['user_id'] == $userId) {
        Response::error('Cannot delete your own account', 400);
    }
    
    $db = Database::getInstance()->getConnection();
    
    try {
        // Check if user exists
        $stmt = $db->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        if (!$stmt->fetch()) {
            Response::error('User not found', 404);
        }
        
        // Delete user (cascade will handle related records)
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        
        if ($stmt->rowCount() > 0) {
            Response::success('User deleted successfully');
        } else {
            Response::error('Failed to delete user', 500);
        }
        
    } catch (PDOException $e) {
        error_log("Failed to delete user: " . $e->getMessage());
        Response::error('Failed to delete user', 500);
    }
}

function handleGetUserCards($userId) {
    $user = Auth::authenticate();
    
    // Only admin can view cards
    if ($user['role'] !== 'admin') {
        Response::error('Unauthorized', 403);
    }
    
    $db = Database::getInstance()->getConnection();
    
    try {
        $stmt = $db->prepare("
            SELECT id, card_uid, is_active, registered_at, last_used_at, notes
            FROM rfid_cards 
            WHERE user_id = ?
            ORDER BY registered_at DESC
        ");
        $stmt->execute([$userId]);
        $cards = $stmt->fetchAll();
        
        Response::json($cards);
        
    } catch (PDOException $e) {
        error_log("Failed to fetch user cards: " . $e->getMessage());
        Response::error('Failed to fetch user cards', 500);
    }
}

function handleAddUserCard($userId) {
    $user = Auth::authenticate();
    
    // Only admin can add cards
    if ($user['role'] !== 'admin') {
        Response::error('Unauthorized', 403);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate input
    if (empty($input['card_uid'])) {
        Response::error('Card UID is required', 400);
    }
    
    $db = Database::getInstance()->getConnection();
    
    try {
        // Check if user exists
        $stmt = $db->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        if (!$stmt->fetch()) {
            Response::error('User not found', 404);
        }
        
        // Check if card already exists
        $stmt = $db->prepare("SELECT id FROM rfid_cards WHERE card_uid = ?");
        $stmt->execute([$input['card_uid']]);
        if ($stmt->fetch()) {
            Response::error('Card UID already registered', 409);
        }
        
        // Insert new card
        $stmt = $db->prepare("
            INSERT INTO rfid_cards (user_id, card_uid, is_active, notes)
            VALUES (?, ?, ?, ?)
            RETURNING id, card_uid, is_active, registered_at, notes
        ");
        
        $isActive = $input['is_active'] ?? true;
        $notes = $input['notes'] ?? '';
        
        $stmt->execute([$userId, $input['card_uid'], $isActive, $notes]);
        $newCard = $stmt->fetch();
        
        Response::json($newCard, 201);
        
    } catch (PDOException $e) {
        error_log("Failed to add user card: " . $e->getMessage());
        Response::error('Failed to add user card', 500);
    }
}

function handleUpdateUserCard($userId, $cardId) {
    $user = Auth::authenticate();
    
    // Only admin can update cards
    if ($user['role'] !== 'admin') {
        Response::error('Unauthorized', 403);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $db = Database::getInstance()->getConnection();
    
    try {
        // Check if card exists and belongs to user
        $stmt = $db->prepare("SELECT * FROM rfid_cards WHERE id = ? AND user_id = ?");
        $stmt->execute([$cardId, $userId]);
        $existingCard = $stmt->fetch();
        
        if (!$existingCard) {
            Response::error('Card not found', 404);
        }
        
        // Build update query
        $updates = [];
        $params = [];
        
        if (!empty($input['card_uid']) && $input['card_uid'] !== $existingCard['card_uid']) {
            // Check if new card UID is already in use
            $stmt = $db->prepare("SELECT id FROM rfid_cards WHERE card_uid = ? AND id != ?");
            $stmt->execute([$input['card_uid'], $cardId]);
            if ($stmt->fetch()) {
                Response::error('Card UID already registered', 409);
            }
            $updates[] = 'card_uid = ?';
            $params[] = $input['card_uid'];
        }
        
        if (isset($input['is_active'])) {
            $updates[] = 'is_active = ?';
            $params[] = $input['is_active'];
        }
        
        if (isset($input['notes'])) {
            $updates[] = 'notes = ?';
            $params[] = $input['notes'];
        }
        
        if (empty($updates)) {
            Response::error('No updates provided', 400);
        }
        
        $params[] = $cardId;
        
        $sql = "UPDATE rfid_cards SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        // Fetch updated card
        $stmt = $db->prepare("SELECT id, card_uid, is_active, registered_at, last_used_at, notes FROM rfid_cards WHERE id = ?");
        $stmt->execute([$cardId]);
        $updatedCard = $stmt->fetch();
        
        Response::json($updatedCard);
        
    } catch (PDOException $e) {
        error_log("Failed to update user card: " . $e->getMessage());
        Response::error('Failed to update user card', 500);
    }
}

function handleDeleteUserCard($userId, $cardId) {
    $user = Auth::authenticate();
    
    // Only admin can delete cards
    if ($user['role'] !== 'admin') {
        Response::error('Unauthorized', 403);
    }
    
    $db = Database::getInstance()->getConnection();
    
    try {
        // Check if card exists and belongs to user
        $stmt = $db->prepare("SELECT id FROM rfid_cards WHERE id = ? AND user_id = ?");
        $stmt->execute([$cardId, $userId]);
        if (!$stmt->fetch()) {
            Response::error('Card not found', 404);
        }
        
        // Delete card
        $stmt = $db->prepare("DELETE FROM rfid_cards WHERE id = ?");
        $stmt->execute([$cardId]);
        
        if ($stmt->rowCount() > 0) {
            Response::success('Card deleted successfully');
        } else {
            Response::error('Failed to delete card', 500);
        }
        
    } catch (PDOException $e) {
        error_log("Failed to delete user card: " . $e->getMessage());
        Response::error('Failed to delete user card', 500);
    }
}
