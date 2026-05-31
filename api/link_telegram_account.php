<?php
// api/link_telegram_account.php
// API для привязки Telegram аккаунта к существующему пользователю

header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';
require_once '../config/telegram_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Метод не разрешен']);
    exit();
}

// Инициализация TelegramAuth
$telegramAuth = new TelegramAuth($pdo);

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Не все поля заполнены']);
        exit();
    }
    
    // Получаем данные Telegram пользователя из сессии
    session_start();
    $telegramUserData = $telegramAuth->getStoredTelegramData();
    
    if (!$telegramUserData) {
        echo json_encode(['success' => false, 'message' => 'Данные Telegram не найдены. Обновите страницу.']);
        exit();
    }
    
    // Проверяем логин и пароль
    $sql = "SELECT * FROM users WHERE username = :username AND status = 'active'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Неверный логин или пароль']);
        exit();
    }
    
    // Проверяем, что аккаунт еще не привязан к Telegram
    if ($user['telegram_id']) {
        echo json_encode(['success' => false, 'message' => 'Этот аккаунт уже привязан к Telegram']);
        exit();
    }
    
    // Привязываем Telegram ID к аккаунту
    $success = $telegramAuth->linkTelegramToAccount(
        $username, 
        $telegramUserData['id'], 
        $telegramUserData['username'] ?? null
    );
    
    if ($success) {
        // Выполняем автоматический вход
        $_SESSION['authenticated'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['client_name'] = $user['client_name'];
        $_SESSION['telegram_username'] = $telegramUserData['username'] ?? null;
        $_SESSION['telegram_id'] = $telegramUserData['id'];
        $_SESSION['group'] = $user['group'] ?? null;
        
        // Очищаем временные данные
        $telegramAuth->clearStoredTelegramData();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Аккаунт успешно привязан к Telegram!',
            'user' => [
                'username' => $user['username'],
                'role' => $user['role'],
                'client_name' => $user['client_name'],
                'group' => $user['group']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ошибка привязки аккаунта. Возможно, этот Telegram ID уже используется.']);
    }
    
} catch (Exception $e) {
    error_log("Ошибка привязки Telegram аккаунта: " . $e->getMessage());
    
    echo json_encode(['success' => false, 'message' => 'Ошибка сервера']);
}
?>
