<?php
// Скрипт для настройки вебхука Brawl Stars Telegram бота

// --- CONFIG ---
// Токен вашего Telegram бота (замените на реальный)
$botToken = '8244381929:AAHkGBXY4R55jox_cNWBXf3x4xD6pXMNs_w';

// API ключ Brawl Stars (получите на https://developer.brawlstars.com/)
$brawlStarsApiKey = 'YOUR_API_KEY_HERE';

// Тег клуба (без символа #, например: ABC123)
$clubTag = 'YOUR_CLUB_TAG_HERE';

// URL вашего вебхука (замените на ваш домен)
$webhookUrl = 'https://yourdomain.com/api/brawlstars_bot_webhook.php';

echo "<!DOCTYPE html>
<html lang='ru'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Настройка Brawl Stars бота</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h2 {
            color: #333;
            border-bottom: 3px solid #4CAF50;
            padding-bottom: 10px;
        }
        h3 {
            color: #555;
            margin-top: 25px;
        }
        .success {
            color: #4CAF50;
            font-weight: bold;
        }
        .error {
            color: #f44336;
            font-weight: bold;
        }
        .warning {
            color: #ff9800;
            font-weight: bold;
        }
        .info-box {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #2196F3;
            margin: 15px 0;
        }
        .code {
            background: #f5f5f5;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }
        pre {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
<div class='container'>";

echo "<h2>🤖 Настройка Brawl Stars Telegram бота</h2>";

// Проверка конфигурации
if ($botToken === 'YOUR_BOT_TOKEN_HERE' || $brawlStarsApiKey === 'YOUR_API_KEY_HERE' || $clubTag === 'YOUR_CLUB_TAG_HERE') {
    echo "<div class='info-box'>";
    echo "<strong>⚠️ Внимание!</strong><br><br>";
    echo "Перед запуском скрипта необходимо настроить следующие параметры в начале файла:<br><br>";
    echo "1. <strong>\$botToken</strong> - токен вашего Telegram бота<br>";
    echo "2. <strong>\$brawlStarsApiKey</strong> - API ключ Brawl Stars<br>";
    echo "3. <strong>\$clubTag</strong> - тег клуба (без символа #)<br>";
    echo "4. <strong>\$webhookUrl</strong> - URL вашего вебхука<br><br>";
    echo "<strong>Как получить:</strong><br>";
    echo "• <strong>Токен бота:</strong> Найдите @BotFather в Telegram, создайте бота командой /newbot<br>";
    echo "• <strong>API ключ:</strong> Зарегистрируйтесь на <a href='https://developer.brawlstars.com/' target='_blank'>developer.brawlstars.com</a><br>";
    echo "• <strong>Тег клуба:</strong> Откройте игру Brawl Stars, зайдите в клуб и скопируйте тег (без #)<br>";
    echo "</div>";
    exit;
}

// 1. Получение информации о боте
echo "<h3>1. Информация о боте</h3>";
$botInfoUrl = "https://api.telegram.org/bot{$botToken}/getMe";
$ch = curl_init($botInfoUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$botInfo = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$botData = json_decode($botInfo, true);

if ($botData && $botData['ok']) {
    echo "<span class='success'>✅ Бот найден:</span><br>";
    echo "👤 <strong>Имя:</strong> " . htmlspecialchars($botData['result']['first_name']) . "<br>";
    echo "🆔 <strong>Username:</strong> @" . htmlspecialchars($botData['result']['username']) . "<br>";
    echo "🆔 <strong>ID:</strong> " . htmlspecialchars($botData['result']['id']) . "<br><br>";
} else {
    echo "<span class='error'>❌ Ошибка:</span> Не удалось получить информацию о боте. Проверьте токен.<br>";
    echo "HTTP код: {$httpCode}<br>";
    echo "Ответ: " . htmlspecialchars(substr($botInfo ?? '', 0, 200)) . "<br><br>";
    exit;
}

// 2. Проверка API ключа Brawl Stars
echo "<h3>2. Проверка API ключа Brawl Stars</h3>";
$testUrl = "https://api.brawlstars.com/v1/clubs/%23{$clubTag}";
$ch = curl_init($testUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $brawlStarsApiKey,
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$testResponse = curl_exec($ch);
$testHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($testHttpCode === 200) {
    $testData = json_decode($testResponse, true);
    if ($testData && isset($testData['name'])) {
        echo "<span class='success'>✅ API ключ работает!</span><br>";
        echo "🏆 <strong>Клуб:</strong> " . htmlspecialchars($testData['name']) . "<br>";
        echo "🏷️ <strong>Тег:</strong> #" . htmlspecialchars($testData['tag']) . "<br>";
        echo "💎 <strong>Трофеи:</strong> " . number_format($testData['trophies'] ?? 0, 0, ',', ' ') . "<br><br>";
    } else {
        echo "<span class='warning'>⚠️ API ключ работает, но данные клуба не получены</span><br><br>";
    }
} else {
    echo "<span class='error'>❌ Ошибка API:</span> HTTP {$testHttpCode}<br>";
    if ($testHttpCode === 403) {
        echo "Неверный API ключ или недостаточно прав.<br>";
    } elseif ($testHttpCode === 404) {
        echo "Клуб с таким тегом не найден. Проверьте тег клуба.<br>";
    } else {
        echo "Ответ: " . htmlspecialchars(substr($testResponse ?? '', 0, 200)) . "<br>";
    }
    echo "<br>";
}

// 3. Удаление старого вебхука
echo "<h3>3. Удаление старого вебхука</h3>";
$deleteWebhookUrl = "https://api.telegram.org/bot{$botToken}/deleteWebhook";
$ch = curl_init($deleteWebhookUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$deleteResult = curl_exec($ch);
curl_close($ch);
$deleteData = json_decode($deleteResult, true);

if ($deleteData && $deleteData['ok']) {
    echo "<span class='success'>✅ Старый вебхук удален</span><br><br>";
} else {
    echo "<span class='warning'>⚠️ Не удалось удалить старый вебхук (возможно, его не было)</span><br><br>";
}

// 4. Установка нового вебхука
echo "<h3>4. Установка нового вебхука</h3>";
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
    echo "<span class='success'>✅ Вебхук установлен успешно!</span><br>";
    echo "🔗 <strong>URL:</strong> " . htmlspecialchars($webhookUrl) . "<br><br>";
} else {
    echo "<span class='error'>❌ Ошибка установки вебхука:</span><br>";
    echo "HTTP код: {$httpCode}<br>";
    echo "Ответ: " . htmlspecialchars(substr($setResult ?? '', 0, 300)) . "<br><br>";
}

// 5. Проверка вебхука
echo "<h3>5. Проверка вебхука</h3>";
$getWebhookUrl = "https://api.telegram.org/bot{$botToken}/getWebhookInfo";
$ch = curl_init($getWebhookUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$getResult = curl_exec($ch);
curl_close($ch);
$getData = json_decode($getResult, true);

if ($getData && $getData['ok']) {
    $webhookInfo = $getData['result'];
    echo "<span class='success'>✅ Информация о вебхуке:</span><br>";
    echo "🔗 <strong>URL:</strong> " . htmlspecialchars($webhookInfo['url']) . "<br>";
    echo "📊 <strong>Ожидающих обновлений:</strong> " . ($webhookInfo['pending_update_count'] ?? 0) . "<br>";
    
    if (isset($webhookInfo['last_error_date'])) {
        echo "<span class='warning'>⚠️ Последняя ошибка:</span> " . date('Y-m-d H:i:s', $webhookInfo['last_error_date']) . "<br>";
        echo "<span class='error'>❌ Текст ошибки:</span> " . htmlspecialchars($webhookInfo['last_error_message'] ?? 'Неизвестно') . "<br>";
    } else {
        echo "<span class='success'>✅ Ошибок нет</span><br>";
    }
    echo "<br>";
} else {
    echo "<span class='error'>❌ Не удалось получить информацию о вебхуке</span><br><br>";
}

// 6. Настройка токенов в вебхуке
echo "<h3>6. Настройка токенов в вебхуке</h3>";
echo "<div class='info-box'>";
echo "<strong>📋 Важно!</strong><br><br>";
echo "Убедитесь, что в файле <code>api/brawlstars_bot_webhook.php</code> установлены правильные значения:<br><br>";
echo "<pre>";
echo "\$BOT_TOKEN = '{$botToken}';\n";
echo "\$BRAWL_STARS_API_KEY = '{$brawlStarsApiKey}';\n";
echo "\$CLUB_TAG = '{$clubTag}';";
echo "</pre>";
echo "</div>";

// 7. Инструкции по использованию
echo "<h3>7. Инструкции по использованию</h3>";
echo "<div class='info-box'>";
echo "<strong>📋 Что нужно сделать:</strong><br><br>";
echo "1. <strong>Обновите токены</strong> в файле <code>api/brawlstars_bot_webhook.php</code><br>";
echo "2. <strong>Протестируйте бота</strong> - отправьте /start вашему боту в Telegram<br>";
echo "3. <strong>Проверьте работу</strong> - используйте команду /club для получения статистики<br><br>";
echo "<strong>🔧 Доступные команды:</strong><br>";
echo "• /start - Начать работу<br>";
echo "• /club или /stats - Полная статистика клуба<br>";
echo "• /short - Краткая статистика<br>";
echo "• /help - Помощь<br><br>";
echo "<strong>💡 Совет:</strong> Бот полностью независим от основного проекта и не влияет на него.";
echo "</div>";

echo "<br><strong style='color: #4CAF50; font-size: 1.2em;'>🎉 Настройка завершена!</strong><br>";
echo "Теперь ваш бот готов к работе.";

echo "</div></body></html>";
?>

