<?php
// api/save_telegram_id.php
// API для сохранения telegram_id в базу данных через JavaScript

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';
require_once '../config/blocked_telegram_ids.php';

$response = ['success' => false, 'message' => '', 'data' => []];

try {
    // Получаем данные из запроса
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        $response['message'] = 'Нет данных в запросе';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    $telegramId = $input['telegram_id'] ?? null;
    $telegramUsername = $input['telegram_username'] ?? null;
    $username = $input['username'] ?? null;
    $password = $input['password'] ?? null;
    
    // Валидация данных
    if (!$telegramId) {
        $response['message'] = 'Telegram ID не указан';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    // Проверяем, не заблокирован ли пользователь
    if (isTelegramIdBlocked($telegramId)) {
        $response['message'] = 'Доступ запрещен';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    if (!$username || !$password) {
        $response['message'] = 'Логин и пароль обязательны';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    // Проверяем существование пользователя
    $sql = "SELECT id, username, password, status FROM users WHERE username = :username";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $response['message'] = 'Пользователь не найден';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    // Проверяем пароль
    if (!password_verify($password, $user['password'])) {
        $response['message'] = 'Неверный пароль';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    // Проверяем статус пользователя
    if ($user['status'] !== 'active') {
        $response['message'] = 'Аккаунт заблокирован';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    // Проверяем, не привязан ли уже этот Telegram ID к другому аккаунту
    $sql = "SELECT id, username FROM users WHERE telegram_id = :telegram_id AND id != :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'telegram_id' => (int)$telegramId,
        'user_id' => $user['id']
    ]);
    $existingUser = $stmt->fetch();
    
    if ($existingUser) {
        $response['message'] = 'Этот Telegram ID уже привязан к аккаунту ' . $existingUser['username'];
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    // Сохраняем telegram_id в базу данных
    $sql = "UPDATE users SET telegram_id = :telegram_id, telegram_username = :telegram_username WHERE id = :user_id";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        'telegram_id' => (int)$telegramId,
        'telegram_username' => $telegramUsername,
        'user_id' => $user['id']
    ]);
    
    if (!$result) {
        $response['message'] = 'Ошибка сохранения в базу данных';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    // Проверяем, что данные сохранились
    $sql = "SELECT telegram_id, telegram_username FROM users WHERE id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $user['id']]);
    $updatedUser = $stmt->fetch();
    
    if (!$updatedUser || $updatedUser['telegram_id'] != (int)$telegramId) {
        $response['message'] = 'Ошибка: данные не сохранились в базу данных';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    // Успешное сохранение
    $response['success'] = true;
    $response['message'] = 'Telegram ID успешно сохранен в базу данных!';
    $response['data'] = [
        'user_id' => $user['id'],
        'username' => $user['username'],
        'telegram_id' => $updatedUser['telegram_id'],
        'telegram_username' => $updatedUser['telegram_username']
    ];
    
} catch (Exception $e) {
    $response['message'] = 'Ошибка сервера: ' . $e->getMessage();
    error_log("Ошибка сохранения telegram_id: " . $e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
