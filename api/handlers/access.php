<?php

function handleVerifyAccess() {
    // Get request body
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate input
    if (empty($input['card_uid'])) {
        Response::error('Card UID is required', 400);
    }
    
    $cardUid = $input['card_uid'];
    
    $db = Database::getInstance()->getConnection();
    
    try {
        // Start transaction
        $db->beginTransaction();
        
        // Check if card exists and is active
        $stmt = $db->prepare("
            SELECT u.id as user_id, u.name, u.status, u.role, 
                   rc.id as card_id, rc.is_active as card_active
            FROM rfid_cards rc
            JOIN users u ON rc.user_id = u.id
            WHERE rc.card_uid = ?
        ");
        $stmt->execute([$cardUid]);
        $card = $stmt->fetch();
        
        $accessGranted = false;
        $reason = '';
        
        // Check access conditions
        if (!$card) {
            $reason = 'Card not registered';
        } elseif (!$card['card_active']) {
            $reason = 'Card is inactive';
        } elseif ($card['status'] !== 'active') {
            $reason = 'User account is ' . $card['status'];
        } else {
            // Access granted - no time restrictions
            $accessGranted = true;
        }
        
        // Log the access attempt
        $stmt = $db->prepare("
            INSERT INTO access_logs 
            (user_id, card_uid, access_granted, failure_reason)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $card['user_id'] ?? null,
            $cardUid,
            $accessGranted ? 1 : 0,
            $accessGranted ? null : $reason
        ]);
        
        // Update last_used_at for the card if access was granted
        if ($accessGranted) {
            $stmt = $db->prepare("
                UPDATE rfid_cards 
                SET last_used_at = CURRENT_TIMESTAMP 
                WHERE card_uid = ?
            ");
            $stmt->execute([$cardUid]);
        }
        
        $db->commit();
        
        // Return response
        Response::json([
            'access_granted' => $accessGranted,
            'user' => $accessGranted ? [
                'id' => $card['user_id'],
                'name' => $card['name'],
                'role' => $card['role']
            ] : null,
            'reason' => $accessGranted ? 'Access granted' : $reason,
            'timestamp' => date('c')
        ]);
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        
        error_log("Access verification failed: " . $e->getMessage());
        Response::error('Failed to verify access', 500);
    }
}
