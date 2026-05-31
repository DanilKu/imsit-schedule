<?php
// api/auto_login_telegram.php
// API для автоматического входа по telegram_id

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
    
    // Ищем пользователя с таким telegram_id
    $sql = "SELECT id, username, role, client_name, telegram_username, `group`, status FROM users WHERE telegram_id = :telegram_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['telegram_id' => (int)$telegramId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $response['message'] = 'Пользователь с таким Telegram ID не найден';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    if ($user['status'] !== 'active') {
        $response['message'] = 'Аккаунт заблокирован';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    // Создаем сессию
    session_start();
    
    $_SESSION['authenticated'] = true;
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['client_name'] = $user['client_name'];
    $_SESSION['telegram_username'] = $user['telegram_username'];
    $_SESSION['telegram_id'] = $telegramId;
    $_SESSION['group'] = $user['group'];
    
    // Успешный вход
    $response['success'] = true;
    $response['message'] = 'Вход выполнен. Пожалуйста подождите, это может занять некоторое время.';
    $response['data'] = [
        'user_id' => $user['id'],
        'username' => $user['username'],
        'role' => $user['role'],
        'client_name' => $user['client_name'],
        'group' => $user['group'],
        'redirect_url' => $user['role'] === 'admin' ? 'admin' : 'schedule-new'
    ];
    
} catch (Exception $e) {
    $response['message'] = 'Ошибка сервера: ' . $e->getMessage();
    error_log("Ошибка автоматического входа: " . $e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
