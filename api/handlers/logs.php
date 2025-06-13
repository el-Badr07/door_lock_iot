<?php

function handleGetAccessLogs() {
    $user = Auth::authenticate();
    
    // Only admin can view all access logs
    if ($user['role'] !== 'admin') {
        Response::error('Unauthorized', 403);
    }
    
    // Get query parameters
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(100, max(1, intval($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    
    $filters = [];
    $params = [];
    
    // Apply filters
    if (!empty($_GET['user_id'])) {
        $filters[] = 'al.user_id = ?';
        $params[] = $_GET['user_id'];
    }
    
    if (!empty($_GET['card_uid'])) {
        $filters[] = 'al.card_uid = ?';
        $params[] = $_GET['card_uid'];
    }
    
    if (isset($_GET['access_granted'])) {
        $filters[] = 'al.access_granted = ?';
        $params[] = $_GET['access_granted'] === 'true' ? 1 : 0;
    }
    

    
    if (!empty($_GET['start_date'])) {
        $filters[] = 'al.access_time >= ?';
        $params[] = $_GET['start_date'];
    }
    
    if (!empty($_GET['end_date'])) {
        $filters[] = 'al.access_time <= ?';
        $params[] = $_GET['end_date'] . ' 23:59:59';
    }
    
    $whereClause = !empty($filters) ? 'WHERE ' . implode(' AND ', $filters) : '';
    
    $db = Database::getInstance()->getConnection();
    
    try {
        // Get total count for pagination
        $countStmt = $db->prepare("
            SELECT COUNT(*) as total 
            FROM access_logs al
            $whereClause
        ");
        $countStmt->execute($params);
        $total = $countStmt->fetch()['total'];
        
        // Get paginated logs
        $logsStmt = $db->prepare("
            SELECT 
                al.id, al.access_time, al.access_granted, al.failure_reason,
                al.card_uid,
                u.id as user_id, u.name as user_name, u.email as user_email
            FROM access_logs al
            LEFT JOIN users u ON al.user_id = u.id
            $whereClause
            ORDER BY al.access_time DESC
            LIMIT ? OFFSET ?
        ");
        
        $logsParams = array_merge($params, [$limit, $offset]);
        $logsStmt->execute($logsParams);
        $logs = $logsStmt->fetchAll();
        
        // Format response
        $response = [
            'data' => $logs,
            'pagination' => [
                'total' => (int)$total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($total / $limit)
            ]
        ];
        
        Response::json($response);
        
    } catch (PDOException $e) {
        error_log("Failed to fetch access logs: " . $e->getMessage());
        Response::error('Failed to fetch access logs', 500);
    }
}

// Additional reporting endpoints can be added here, for example:
// - Access statistics by day/week/month
// - Most active users
// - Access denied reasons
// - Door usage patterns
