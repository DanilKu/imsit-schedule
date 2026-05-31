<?php
// check_database_structure.php
// Проверка структуры базы данных для telegram_id

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

$response = ['success' => false, 'message' => '', 'data' => []];

try {
    // 1. Проверяем структуру таблицы users
    $sql = "DESCRIBE users";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $columns = $stmt->fetchAll();
    
    $response['data']['table_structure'] = $columns;
    
    // 2. Проверяем конкретно поле telegram_id
    $sql = "SHOW COLUMNS FROM users LIKE 'telegram_id'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $telegramIdColumn = $stmt->fetch();
    
    $response['data']['telegram_id_column'] = $telegramIdColumn;
    
    // 3. Проверяем индексы
    $sql = "SHOW INDEX FROM users WHERE Column_name = 'telegram_id'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $indexes = $stmt->fetchAll();
    
    $response['data']['telegram_id_indexes'] = $indexes;
    
    // 4. Проверяем количество пользователей
    $sql = "SELECT COUNT(*) as total FROM users";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $totalUsers = $stmt->fetch()['total'];
    
    $response['data']['total_users'] = $totalUsers;
    
    // 5. Проверяем пользователей с telegram_id
    $sql = "SELECT COUNT(*) as linked FROM users WHERE telegram_id IS NOT NULL";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $linkedUsers = $stmt->fetch()['linked'];
    
    $response['data']['linked_users'] = $linkedUsers;
    
    // 6. Показываем примеры пользователей
    $sql = "SELECT id, username, telegram_id, telegram_username, status FROM users LIMIT 5";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $sampleUsers = $stmt->fetchAll();
    
    $response['data']['sample_users'] = $sampleUsers;
    
    // 7. Проверяем, есть ли пользователи с telegram_id
    $sql = "SELECT id, username, telegram_id, telegram_username FROM users WHERE telegram_id IS NOT NULL";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $usersWithTelegram = $stmt->fetchAll();
    
    $response['data']['users_with_telegram'] = $usersWithTelegram;
    
    // 8. Тест INSERT/UPDATE для telegram_id
    $testTelegramId = 999999999;
    $testUsername = 'test_telegram_user';
    
    // Создаем тестового пользователя
    $sql = "INSERT INTO users (username, password, client_name, role, status) VALUES (:username, :password, :client_name, 'client', 'active') ON DUPLICATE KEY UPDATE password = VALUES(password)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'username' => $testUsername,
        'password' => password_hash('test123', PASSWORD_DEFAULT),
        'client_name' => 'Тестовый пользователь'
    ]);
    
    // Пробуем обновить telegram_id
    $sql = "UPDATE users SET telegram_id = :telegram_id WHERE username = :username";
    $stmt = $pdo->prepare($sql);
    $updateResult = $stmt->execute([
        'telegram_id' => $testTelegramId,
        'username' => $testUsername
    ]);
    
    $response['data']['test_update_result'] = $updateResult;
    $response['data']['test_update_affected_rows'] = $stmt->rowCount();
    
    // Проверяем, что данные сохранились
    $sql = "SELECT telegram_id FROM users WHERE username = :username";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['username' => $testUsername]);
    $savedTelegramId = $stmt->fetch()['telegram_id'];
    
    $response['data']['test_saved_telegram_id'] = $savedTelegramId;
    $response['data']['test_success'] = ($savedTelegramId == $testTelegramId);
    
    // Удаляем тестового пользователя
    $sql = "DELETE FROM users WHERE username = :username";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['username' => $testUsername]);
    
    $response['success'] = true;
    $response['message'] = 'Проверка структуры базы данных завершена';
    
} catch (Exception $e) {
    $response['message'] = 'Ошибка: ' . $e->getMessage();
    $response['data']['error'] = $e->getTraceAsString();
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
