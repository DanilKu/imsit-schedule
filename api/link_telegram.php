<?php
// API для привязки Telegram аккаунта к пользователю
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/blocked_telegram_ids.php';

$response = ['success' => false, 'message' => ''];

try {
    if (!isAuthenticated()) {
        $response['message'] = 'Необходима авторизация';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $currentUser = getCurrentUser();
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'link_telegram':
            $telegramId = (int)($_POST['telegram_id'] ?? 0);
            
            if ($telegramId <= 0) {
                throw new Exception('Неверный Telegram ID');
            }
            
            // Проверяем, не заблокирован ли пользователь
            if (isTelegramIdBlocked($telegramId)) {
                throw new Exception('Доступ запрещен');
            }
            
            // Проверяем, не привязан ли уже этот Telegram ID к другому пользователю
            $stmt = $pdo->prepare("SELECT id, client_name FROM users WHERE telegram_id = ? AND id != ?");
            $stmt->execute([$telegramId, $currentUser['id']]);
            $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingUser) {
                throw new Exception('Этот Telegram аккаунт уже привязан к пользователю: ' . $existingUser['client_name']);
            }
            
            // Привязываем Telegram ID к текущему пользователю
            $stmt = $pdo->prepare("UPDATE users SET telegram_id = ? WHERE id = ?");
            $stmt->execute([$telegramId, $currentUser['id']]);
            
            $response['success'] = true;
            $response['message'] = 'Telegram аккаунт успешно привязан!';
            break;
            
        case 'unlink_telegram':
            // Отвязываем Telegram ID
            $stmt = $pdo->prepare("UPDATE users SET telegram_id = NULL WHERE id = ?");
            $stmt->execute([$currentUser['id']]);
            
            $response['success'] = true;
            $response['message'] = 'Telegram аккаунт отвязан';
            break;
            
        case 'get_telegram_status':
            // Получаем статус привязки
            $stmt = $pdo->prepare("SELECT telegram_id FROM users WHERE id = ?");
            $stmt->execute([$currentUser['id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $response['success'] = true;
            $response['telegram_linked'] = !empty($user['telegram_id']);
            $response['telegram_id'] = $user['telegram_id'] ?? null;
            break;
            
        default:
            throw new Exception('Неизвестное действие');
    }
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
