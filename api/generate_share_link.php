<?php
// API для генерации ссылки на шаринг расписания через Telegram бота
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once dirname(__DIR__) . '/config/database.php';

$BOT_TOKEN = getenv('IMSIT_TELEGRAM_BOT_TOKEN') ?: '8371794642:AAEtU08o8r6qL-HB8qJGvRKik0gCvzd_b2M';

// Получаем информацию о боте для username
function getBotInfo() {
    global $BOT_TOKEN;
    $url = "https://api.telegram.org/bot{$BOT_TOKEN}/getMe";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $resp = curl_exec($ch);
    curl_close($ch);
    $data = $resp ? json_decode($resp, true) : null;
    return $data && $data['ok'] ? $data['result']['username'] : 'imsitid_bot';
}

// Кешируем username бота
static $botUsername = null;
if ($botUsername === null) {
    $botUsername = getBotInfo();
}

$type = $_GET['type'] ?? $_POST['type'] ?? ''; // 'group' или 'teacher'
$value = $_GET['value'] ?? $_POST['value'] ?? ''; // название группы или имя преподавателя

if (empty($type) || empty($value)) {
    echo json_encode(['error' => 'Не указаны параметры type и value']);
    exit;
}

if ($type !== 'group' && $type !== 'teacher') {
    echo json_encode(['error' => 'Неверный тип. Допустимые значения: group, teacher']);
    exit;
}

// Кодируем значение для передачи в параметре start
$encodedValue = base64_encode($value);
$encodedValue = rtrim(strtr($encodedValue, '+/', '-_'), '=');

// Формируем ссылку на бота
$shareParam = "share_{$type}_{$encodedValue}";
$botLink = "https://t.me/{$botUsername}?start={$shareParam}";

echo json_encode([
    'ok' => true,
    'link' => $botLink,
    'type' => $type,
    'value' => $value
]);

