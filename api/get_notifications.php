<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/auth.php';
require_once '../config/database.php';
require_once '../includes/NotificationManager.php';

// Проверка авторизации
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$notificationManager = new NotificationManager($pdo);
$user = getCurrentUser();

try {
    $context = $_GET['context'] ?? 'dashboard';
    $notifications = $notificationManager->getActiveNotifications(
        $user['id'], 
        $user['role'], 
        $context
    );
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
?>
