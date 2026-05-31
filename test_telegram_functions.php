<?php
// Тест функций Telegram бота
require_once 'config/database.php';

// Подключаем функции из webhook
require_once 'api/user_telegram_webhook.php';

echo "<h1>Тест функций Telegram бота</h1>";

// Тест 1: Получение пользователя
echo "<h2>1. Тест getUserByTelegramId</h2>";
$user = getUserByTelegramId(939863015);
if ($user) {
    echo "✅ Пользователь найден: " . $user['client_name'] . "<br>";
    echo "Группа: " . ($user['group'] ?? 'не указана') . "<br>";
} else {
    echo "❌ Пользователь не найден<br>";
}

// Тест 2: Получение расписания
echo "<h2>2. Тест getGroupSchedule</h2>";
$schedule = getGroupSchedule('Исип-06');
echo "Найдено пар: " . count($schedule) . "<br>";

if (!empty($schedule)) {
    echo "Первые 3 пары:<br>";
    for ($i = 0; $i < min(3, count($schedule)); $i++) {
        $lesson = $schedule[$i];
        echo "- {$lesson['lesson_number']} пара: {$lesson['subject_name']} в {$lesson['room_number']}<br>";
    }
}

// Тест 3: Генерация текстового расписания
echo "<h2>3. Тест generateTextSchedule</h2>";
if (!empty($schedule)) {
    $textSchedule = generateTextSchedule($schedule, 'Исип-06');
    echo "Длина текста: " . strlen($textSchedule) . " символов<br>";
    echo "<pre>" . htmlspecialchars(substr($textSchedule, 0, 500)) . "...</pre>";
} else {
    echo "❌ Нет расписания для тестирования<br>";
}

// Тест 4: Проверка таблицы schedule
echo "<h2>4. Проверка таблицы schedule</h2>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM schedule");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Всего записей в таблице schedule: " . $result['count'] . "<br>";
    
    $stmt = $pdo->query("SELECT DISTINCT group_name FROM schedule LIMIT 5");
    $groups = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Группы в расписании: " . implode(', ', $groups) . "<br>";
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "<br>";
}

echo "<h2>Тест завершен</h2>";
?>
