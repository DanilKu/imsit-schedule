<?php
// Скрипт для удаления вебхука из Telegram бота

// Токен вашего бота
$botToken = '8470730297:AAGh7S8gDlNdtE8aMIIcQ1wrT2eDe7AZj0U';

echo "<h2>Удаление вебхука из Telegram бота</h2>";

// URL для удаления вебхука
$url = "https://api.telegram.org/bot{$botToken}/deleteWebhook";

// Отправляем запрос
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, '');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Проверяем результат
$result = json_decode($response, true);

echo "<p><strong>HTTP код:</strong> {$httpCode}</p>";
echo "<p><strong>Ответ API:</strong></p>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";

if ($result && $result['ok']) {
    echo "<p style='color: green;'><strong>✅ Вебхук успешно удален!</strong></p>";
    echo "<p>Теперь бот не будет получать обновления через вебхук.</p>";
} else {
    echo "<p style='color: red;'><strong>❌ Ошибка при удалении вебхука:</strong></p>";
    if ($result && isset($result['description'])) {
        echo "<p>{$result['description']}</p>";
    }
}

// Дополнительно проверим информацию о вебхуке
echo "<hr>";
echo "<h3>Проверка статуса вебхука:</h3>";

$webhookUrl = "https://api.telegram.org/bot{$botToken}/getWebhookInfo";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $webhookUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$webhookResponse = curl_exec($ch);
curl_close($ch);

$webhookResult = json_decode($webhookResponse, true);

echo "<pre>" . htmlspecialchars($webhookResponse) . "</pre>";

if ($webhookResult && $webhookResult['ok']) {
    $webhookInfo = $webhookResult['result'];
    if (empty($webhookInfo['url'])) {
        echo "<p style='color: green;'><strong>✅ Вебхук полностью удален!</strong></p>";
    } else {
        echo "<p style='color: orange;'><strong>⚠️ Вебхук все еще установлен:</strong> {$webhookInfo['url']}</p>";
    }
}
?>