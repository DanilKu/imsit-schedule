<?php
// Simple webhook for deprecated bot: informs users to migrate to the new bot

require_once __DIR__ . '/../config/database.php'; // keep consistency if needed; safe to include

// Config: new bot username and link
$NEW_BOT_USERNAME = 'imsitID_bot';
$NEW_BOT_LINK = 'https://t.me/imsitID_bot';

// Old bot token (optionally via env); do not hardcode in repo in production
$OLD_BOT_TOKEN = getenv('IMSIT_OLD_BOT_TOKEN');
if (!$OLD_BOT_TOKEN) {
    // fallback if not set via env; you can remove if you prefer env-only
    $OLD_BOT_TOKEN = '8470730297:AAGh7S8gDlNdtE8aMIIcQ1wrT2eDe7AZj0U';
}

function tg_api($method, $params = []){
    global $OLD_BOT_TOKEN;
    $url = "https://api.telegram.org/bot{$OLD_BOT_TOKEN}/{$method}";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}

// Health check and simple GET support
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(200);
    echo 'OK';
    exit;
}

$input = file_get_contents('php://input');
if (!$input) {
    http_response_code(200);
    echo 'OK';
    exit;
}

$update = json_decode($input, true);
if (!$update) {
    http_response_code(200);
    echo 'OK';
    exit;
}

// Extract chat/message
$chatId = null;
$messageText = '';
if (isset($update['message'])) {
    $chatId = $update['message']['chat']['id'] ?? null;
    $messageText = trim((string)($update['message']['text'] ?? ''));
} elseif (isset($update['callback_query'])) {
    $chatId = $update['callback_query']['message']['chat']['id'] ?? null;
}

if ($chatId) {
    $text = "Этот бот больше не используется. Пожалуйста, перейдите в нового бота: \n".
            "👉 {$NEW_BOT_LINK} (@{$NEW_BOT_USERNAME})\n\n".
            "Сохраните ссылку и пользуйтесь всеми актуальными функциями там.";
    tg_api('sendMessage', [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true
    ]);
}

http_response_code(200);
echo 'OK';
?>


