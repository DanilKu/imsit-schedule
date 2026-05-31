<?php
// API для получения списка пользователей бота
error_reporting(0);
ini_set('display_errors', 0);

try {
    require_once dirname(__DIR__) . '/config/database.php';
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Database connection failed']);
    exit;
}

header('Content-Type: application/json');

try {
    $search = $_GET['search'] ?? '';
    
    $sql = "SELECT chat_id, username, first_name, last_name, created_at 
            FROM bot_users 
            WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $sql .= " AND (username LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR chat_id LIKE ?)";
        $searchTerm = "%{$search}%";
        $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Форматируем данные для удобства
    $formattedUsers = array_map(function($user) {
        $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        if (empty($name)) {
            $name = $user['username'] ? '@' . $user['username'] : "ID:{$user['chat_id']}";
        }
        
        return [
            'chat_id' => (int)$user['chat_id'],
            'name' => $name,
            'username' => $user['username'] ? '@' . $user['username'] : null,
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'created_at' => $user['created_at']
        ];
    }, $users);
    
    echo json_encode(['ok' => true, 'users' => $formattedUsers]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
?>
