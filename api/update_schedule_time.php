<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/auth.php';
require_once '../config/database.php';
require_once '../includes/ScheduleManager.php';
date_default_timezone_set('Europe/Moscow');

if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Не авторизован']);
    exit;
}

$scheduleManager = new ScheduleManager($pdo);

try {
    $success = $scheduleManager->updateSettingsWithCurrentTime();
    
    if ($success) {
        $settings = $scheduleManager->getSettings();
        echo json_encode([
            'success' => true,
            'settings' => $settings,
            'message' => 'Время обновлено автоматически'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Ошибка обновления времени'
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
