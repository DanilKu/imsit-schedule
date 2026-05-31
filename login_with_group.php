<?php
// Альтернативная версия регистрации с поддержкой группы
// Используйте этот код, если колонка 'group' уже добавлена в таблицу

// Создаём запрос на регистрацию с группой
$sql = "INSERT INTO registration_requests (first_name, last_name, telegram_username, `group`, status, created_at) 
        VALUES (:first_name, '', :telegram_username, :group, 'pending', NOW())";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    'first_name' => $client_name,
    'telegram_username' => $telegram_username,
    'group' => $group
]);
?>
