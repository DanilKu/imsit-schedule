<?php
// Webhook для получения обновлений от Telegram
require_once dirname(__DIR__) . '/config/database.php';
require_once __DIR__ . '/telegram_notifications.php';

// Получаем данные от Telegram
$input = file_get_contents('php://input');
$update = json_decode($input, true);

if (!$update) {
    http_response_code(400);
    exit('Invalid JSON');
}

// Обрабатываем сообщения
if (isset($update['message'])) {
    $message = $update['message'];
    $chatId = $message['chat']['id'];
    $text = $message['text'] ?? '';
    $from = $message['from'];
    
    $telegram = new TelegramNotifications();
    
    // Команда /start
    if ($text === '/start') {
        $response = "👋 <b>Привет!</b>\n\n";
        $response .= "Я бот для уведомлений о новых заказах.\n\n";
        $response .= "📋 <b>Доступные команды:</b>\n";
        $response .= "/start - Начать работу\n";
        $response .= "/help - Помощь\n";
        $response .= "/setadmin - Установить как админский чат\n";
        $response .= "/test - Тестовое сообщение\n\n";
        $response .= "💡 <b>Совет:</b> Используйте /setadmin чтобы получать уведомления о новых заказах.";
        
        $telegram->sendMessage($response, $chatId);
    }
    
    // Команда /help
    elseif ($text === '/help') {
        $response = "📚 <b>Помощь по боту</b>\n\n";
        $response .= "🤖 <b>Что делает бот:</b>\n";
        $response .= "• Уведомляет о новых заказах\n";
        $response .= "• Сообщает об изменении статусов\n";
        $response .= "• Информирует о новых сообщениях от клиентов\n\n";
        $response .= "⚙️ <b>Настройка:</b>\n";
        $response .= "1. Используйте /setadmin для активации уведомлений\n";
        $response .= "2. Уведомления будут приходить в этот чат\n\n";
        $response .= "🔧 <b>Команды:</b>\n";
        $response .= "/start - Начать работу\n";
        $response .= "/help - Эта справка\n";
        $response .= "/setadmin - Активировать уведомления\n";
        $response .= "/test - Проверить работу бота";
        
        $telegram->sendMessage($response, $chatId);
    }
    
    // Команда /setadmin
    elseif ($text === '/setadmin') {
        // Проверяем, есть ли пользователь с таким telegram_id в базе
        try {
            $stmt = $pdo->prepare("SELECT id, role FROM users WHERE telegram_id = ?");
            $stmt->execute([$chatId]);
            $user = $stmt->fetch();
            
            if ($user) {
                if ($user['role'] === 'admin') {
                    // Устанавливаем этот чат как админский
                    $telegram->setAdminChatId($chatId);
                    
                    $response = "✅ <b>Отлично!</b>\n\n";
                    $response .= "Этот чат теперь настроен для получения уведомлений о новых заказах.\n\n";
                    $response .= "🔔 <b>Вы будете получать:</b>\n";
                    $response .= "• Уведомления о новых заказах\n";
                    $response .= "• Изменения статусов заказов\n";
                    $response .= "• Новые сообщения от клиентов\n\n";
                    $response .= "💡 Используйте /test для проверки работы бота.";
                } else {
                    $response = "❌ <b>Ошибка</b>\n\n";
                    $response .= "У вас нет прав администратора для настройки уведомлений.";
                }
            } else {
                $response = "❌ <b>Ошибка</b>\n\n";
                $response .= "Пользователь с таким Telegram ID не найден в системе.\n\n";
                $response .= "🆔 <b>Ваш Telegram ID:</b> $chatId\n\n";
                $response .= "💡 <b>Решение:</b>\n";
                $response .= "1. Обратитесь к администратору для настройки\n";
                $response .= "2. Или выполните настройку через админку сайта\n";
                $response .= "3. Попробуйте команду /setadmin снова";
            }
        } catch (PDOException $e) {
            error_log("Database error in webhook: " . $e->getMessage());
            $response = "❌ <b>Ошибка базы данных</b>\n\n";
            $response .= "Попробуйте позже или обратитесь к разработчику.";
        }
        
        $telegram->sendMessage($response, $chatId);
    }
    
    // Команда /test
    elseif ($text === '/test') {
        $response = "🧪 <b>Тестовое сообщение</b>\n\n";
        $response .= "✅ Бот работает корректно!\n";
        $response .= "🕐 Время: " . date('d.m.Y H:i:s') . "\n";
        $response .= "💬 Чат ID: $chatId\n\n";
        $response .= "🔔 Если вы видите это сообщение, уведомления настроены правильно!";
        
        $telegram->sendMessage($response, $chatId);
    }
    
    // Неизвестная команда
    else {
        $response = "❓ <b>Неизвестная команда</b>\n\n";
        $response .= "Используйте /help для просмотра доступных команд.";
        
        $telegram->sendMessage($response, $chatId);
    }
}

// Логируем обновления для отладки
error_log("Telegram webhook received: " . $input);

http_response_code(200);
echo 'OK';
?>
