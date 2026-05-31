<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/auth.php';
require_once '../config/database.php';
require_once '../includes/ScheduleManager.php';

if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Не авторизован']);
    exit;
}

$scheduleManager = new ScheduleManager($pdo);
$user = getCurrentUser();
$userGroup = $user['group'] ?? 'Исип-05';

try {
    $currentLesson = $scheduleManager->getCurrentLesson($userGroup);
    $nextLesson = $scheduleManager->getNextLesson($userGroup);
    
    if ($currentLesson) {
        $progress = $scheduleManager->getLessonProgress($currentLesson);
        echo json_encode([
            'success' => true,
            'progress' => $progress,
            'lesson' => $currentLesson,
            'nextLesson' => $nextLesson,
            'hasCurrentLesson' => true,
            'current_time' => $scheduleManager->getSettings()['current_time'],
            'last_update' => date('H:i:s')
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'progress' => 0,
            'lesson' => null,
            'nextLesson' => $nextLesson,
            'hasCurrentLesson' => false,
            'current_time' => $scheduleManager->getSettings()['current_time'],
            'last_update' => date('H:i:s')
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
