<?php
// Скрипт для настройки вебхука пользовательского Telegram бота

// Токен вашего пользовательского бота (замените на реальный)
$botToken = '8470730297:AAGh7S8gDlNdtE8aMIIcQ1wrT2eDe7AZj0U';

// URL вашего вебхука
$webhookUrl = 'https://imsit.shop/api/user_telegram_webhook.php';

echo "<h2>🤖 Настройка Telegram бота для пользователей</h2>";

// 1. Получение информации о боте
echo "<h3>1. Информация о боте</h3>";
$botInfoUrl = "https://api.telegram.org/bot{$botToken}/getMe";
$botInfo = file_get_contents($botInfoUrl);
$botData = json_decode($botInfo, true);

if ($botData && $botData['ok']) {
    echo "✅ <strong>Бот найден:</strong><br>";
    echo "👤 Имя: " . $botData['result']['first_name'] . "<br>";
    echo "🆔 Username: @" . $botData['result']['username'] . "<br>";
    echo "🆔 ID: " . $botData['result']['id'] . "<br><br>";
} else {
    echo "❌ <strong>Ошибка:</strong> Не удалось получить информацию о боте. Проверьте токен.<br><br>";
    exit;
}

// 2. Удаление старого вебхука
echo "<h3>2. Удаление старого вебхука</h3>";
$deleteWebhookUrl = "https://api.telegram.org/bot{$botToken}/deleteWebhook";
$deleteResult = file_get_contents($deleteWebhookUrl);
$deleteData = json_decode($deleteResult, true);

if ($deleteData && $deleteData['ok']) {
    echo "✅ Старый вебхук удален<br><br>";
} else {
    echo "⚠️ Не удалось удалить старый вебхук (возможно, его не было)<br><br>";
}

// 3. Установка нового вебхука
echo "<h3>3. Установка нового вебхука</h3>";
$setWebhookUrl = "https://api.telegram.org/bot{$botToken}/setWebhook";
$webhookData = [
    'url' => $webhookUrl,
    'allowed_updates' => ['message', 'callback_query']
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $setWebhookUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($webhookData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$setResult = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$setData = json_decode($setResult, true);

if ($setData && $setData['ok']) {
    echo "✅ <strong>Вебхук установлен успешно!</strong><br>";
    echo "🔗 URL: " . $webhookUrl . "<br><br>";
} else {
    echo "❌ <strong>Ошибка установки вебхука:</strong><br>";
    echo "HTTP код: " . $httpCode . "<br>";
    echo "Ответ: " . $setResult . "<br><br>";
}

// 4. Проверка вебхука
echo "<h3>4. Проверка вебхука</h3>";
$getWebhookUrl = "https://api.telegram.org/bot{$botToken}/getWebhookInfo";
$getResult = file_get_contents($getWebhookUrl);
$getData = json_decode($getResult, true);

if ($getData && $getData['ok']) {
    $webhookInfo = $getData['result'];
    echo "✅ <strong>Информация о вебхуке:</strong><br>";
    echo "🔗 URL: " . $webhookInfo['url'] . "<br>";
    echo "📊 Ожидающих обновлений: " . $webhookInfo['pending_update_count'] . "<br>";
    
    if (isset($webhookInfo['last_error_date'])) {
        echo "⚠️ Последняя ошибка: " . date('Y-m-d H:i:s', $webhookInfo['last_error_date']) . "<br>";
        echo "❌ Текст ошибки: " . $webhookInfo['last_error_message'] . "<br>";
    } else {
        echo "✅ Ошибок нет<br>";
    }
    echo "<br>";
} else {
    echo "❌ Не удалось получить информацию о вебхуке<br><br>";
}

// 5. Инструкции по использованию
echo "<h3>5. Инструкции по использованию</h3>";
echo "<div style='background: #f0f8ff; padding: 15px; border-radius: 5px; border-left: 4px solid #007bff;'>";
echo "<strong>📋 Что нужно сделать:</strong><br><br>";
echo "1. <strong>Замените токен бота</strong> в файле <code>api/user_telegram_webhook.php</code><br>";
echo "2. <strong>Создайте папку temp</strong> в корне сайта для временных файлов<br>";
echo "3. <strong>Настройте права доступа</strong> к папке temp (755)<br>";
echo "4. <strong>Протестируйте бота</strong> - отправьте /start вашему боту<br><br>";
echo "<strong>🔧 Дополнительные настройки:</strong><br>";
echo "• Измените ссылки на канал и техподдержку в коде<br>";
echo "• Настройте генерацию изображений расписания<br>";
echo "• Добавьте уведомления о статусе заказов<br>";
echo "</div>";

// 6. Создание папки temp
echo "<h3>6. Создание папки temp</h3>";
$tempDir = __DIR__ . '/temp';
if (!file_exists($tempDir)) {
    if (mkdir($tempDir, 0755, true)) {
        echo "✅ Папка temp создана<br>";
    } else {
        echo "❌ Не удалось создать папку temp<br>";
    }
} else {
    echo "✅ Папка temp уже существует<br>";
}

echo "<br><strong>🎉 Настройка завершена!</strong><br>";
echo "Теперь ваш бот готов к работе с пользователями.";
?>
