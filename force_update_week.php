<?php
require_once 'config/database.php';
require_once 'includes/ScheduleManager.php';

$scheduleManager = new ScheduleManager($pdo);

echo "Принудительное обновление недели...<br>";

try {
    // Принудительно обновляем время и неделю
    $success = $scheduleManager->updateSettingsWithCurrentTime();
    
    if ($success) {
        $settings = $scheduleManager->getSettings();
        echo "Успешно обновлено!<br>";
        echo "Текущая неделя: " . $settings['current_week'] . "<br>";
        echo "Текущий день: " . $settings['current_day'] . "<br>";
        echo "Текущее время: " . $settings['current_time'] . "<br>";
    } else {
        echo "Ошибка обновления!<br>";
    }
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "<br>";
}

echo "<br><a href='schedule_management'>Вернуться к управлению расписанием</a>";
?>
