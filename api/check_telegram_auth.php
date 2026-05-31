<?php
// api/check_telegram_auth.php
// API для проверки Telegram Web App авторизации

header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';
require_once '../config/telegram_auth.php';
require_once '../config/blocked_telegram_ids.php';

// Инициализация TelegramAuth
$telegramAuth = new TelegramAuth($pdo);

try {
    // Получаем данные из запроса
    $input = json_decode(file_get_contents('php://input'), true);
    $initData = $input['initData'] ?? null;
    $initDataUnsafe = $input['initDataUnsafe'] ?? null;
    $userData = $input['user'] ?? null;
    
    // Определяем источник данных пользователя
    $telegramUserData = null;
    
    if ($userData) {
        // Данные пользователя переданы напрямую
        $telegramUserData = $userData;
    } elseif ($initDataUnsafe && isset($initDataUnsafe['user'])) {
        // Данные пользователя из initDataUnsafe
        $telegramUserData = $initDataUnsafe['user'];
    } elseif ($initData) {
        // Парсим initData
        parse_str($initData, $data);
        if (isset($data['user'])) {
            $telegramUserData = json_decode($data['user'], true);
        }
    }
    
    if (!$telegramUserData || !isset($telegramUserData['id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Данные пользователя не найдены',
            'autoLogin' => false,
            'needLink' => false,
            'debug' => [
                'hasInitData' => !empty($initData),
                'hasInitDataUnsafe' => !empty($initDataUnsafe),
                'hasUserData' => !empty($userData),
                'inputKeys' => array_keys($input ?? [])
            ]
        ]);
        exit();
    }
    
    $telegramId = $telegramUserData['id'];
    
    // Проверяем, не заблокирован ли пользователь
    if (isTelegramIdBlocked($telegramId)) {
        echo json_encode([
            'success' => false,
            'message' => 'Доступ запрещен',
            'autoLogin' => false,
            'needLink' => false
        ]);
        exit();
    }
    
    // Проверяем, есть ли пользователь с таким telegram_id
    $sql = "SELECT id, username, role, client_name, telegram_username, `group` FROM users WHERE telegram_id = :telegram_id AND status = 'active'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['telegram_id' => $telegramId]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Пользователь найден - выполняем автоматический вход
        session_start();
        
        $_SESSION['authenticated'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['client_name'] = $user['client_name'];
        $_SESSION['telegram_username'] = $user['telegram_username'];
        $_SESSION['telegram_id'] = $telegramId;
        $_SESSION['group'] = $user['group'];
        
        echo json_encode([
            'success' => true,
            'message' => 'Вход выполнен. Пожалуйста подождите, это может занять некоторое время.',
            'autoLogin' => true,
            'needLink' => false,
            'user' => [
                'username' => $user['username'],
                'role' => $user['role'],
                'client_name' => $user['client_name'],
                'group' => $user['group']
            ]
        ]);
    } else {
        // Пользователь не найден - нужна привязка аккаунта
        session_start();
        $_SESSION['telegram_user_data'] = $telegramUserData;
        
        echo json_encode([
            'success' => true,
            'message' => 'Требуется привязка аккаунта',
            'autoLogin' => false,
            'needLink' => true,
            'telegramUser' => [
                'id' => $telegramUserData['id'],
                'first_name' => $telegramUserData['first_name'] ?? '',
                'last_name' => $telegramUserData['last_name'] ?? '',
                'username' => $telegramUserData['username'] ?? ''
            ]
        ]);
    }
    
} catch (Exception $e) {
    error_log("Ошибка проверки Telegram авторизации: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка сервера',
        'autoLogin' => false,
        'needLink' => false
    ]);
}
?>
