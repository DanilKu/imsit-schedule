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

// Проверка метода запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$notificationManager = new NotificationManager($pdo);
$user = getCurrentUser();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $notificationId = $input['notification_id'] ?? null;
    
    if (!$notificationId) {
        http_response_code(400);
        echo json_encode(['error' => 'Notification ID is required']);
        exit;
    }
    
    $success = $notificationManager->markAsShown($notificationId, $user['id']);
    
    if ($success) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to mark notification as shown']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
?>
