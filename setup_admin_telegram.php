<?php
require_once 'config/database.php';

// ID администратора в Telegram
$adminTelegramId = 939863015;

try {
    // Проверяем, есть ли пользователь с таким telegram_id
    $stmt = $pdo->prepare("SELECT id, client_name, role FROM users WHERE telegram_id = ?");
    $stmt->execute([$adminTelegramId]);
    $existingUser = $stmt->fetch();
    
    if ($existingUser) {
        echo "Пользователь с Telegram ID $adminTelegramId уже существует:\n";
        echo "- ID: " . $existingUser['id'] . "\n";
        echo "- Имя: " . $existingUser['client_name'] . "\n";
        echo "- Роль: " . $existingUser['role'] . "\n\n";
        
        // Обновляем роль на admin, если она не admin
        if ($existingUser['role'] !== 'admin') {
            $stmt = $pdo->prepare("UPDATE users SET role = 'admin' WHERE telegram_id = ?");
            $stmt->execute([$adminTelegramId]);
            echo "✅ Роль обновлена на 'admin'\n";
        } else {
            echo "✅ Роль уже установлена как 'admin'\n";
        }
    } else {
        echo "Пользователь с Telegram ID $adminTelegramId не найден.\n";
        echo "Создаем нового администратора...\n";
        
        // Создаем нового пользователя-администратора
        $stmt = $pdo->prepare("
            INSERT INTO users (client_name, email, phone, telegram_id, role, created_at) 
            VALUES (?, ?, ?, ?, 'admin', NOW())
        ");
        
        $stmt->execute([
            'Администратор',
            'admin@imsit.shop',
            '+7 (000) 000-00-00',
            $adminTelegramId
        ]);
        
        $newUserId = $pdo->lastInsertId();
        echo "✅ Создан новый администратор с ID: $newUserId\n";
    }
    
    // Проверяем финальный результат
    $stmt = $pdo->prepare("SELECT id, client_name, email, telegram_id, role FROM users WHERE telegram_id = ?");
    $stmt->execute([$adminTelegramId]);
    $finalUser = $stmt->fetch();
    
    echo "\n📋 Финальная информация:\n";
    echo "- ID в базе: " . $finalUser['id'] . "\n";
    echo "- Имя: " . $finalUser['client_name'] . "\n";
    echo "- Email: " . $finalUser['email'] . "\n";
    echo "- Telegram ID: " . $finalUser['telegram_id'] . "\n";
    echo "- Роль: " . $finalUser['role'] . "\n";
    
    echo "\n🎉 Настройка завершена! Теперь вы можете использовать команду /setadmin в боте.\n";
    
} catch (PDOException $e) {
    echo "❌ Ошибка базы данных: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}
?>
