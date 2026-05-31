<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once '../includes/ScheduleManager.php';

try {
    // Получаем группу из параметров
    $group = $_GET['group'] ?? '';
    
    if (empty($group)) {
        echo json_encode([
            'success' => false,
            'message' => 'Не указана группа'
        ]);
        exit;
    }
    
    // Инициализируем ScheduleManager
    $scheduleManager = new ScheduleManager($pdo);
    
    // Получаем текущую пару
    $currentLesson = $scheduleManager->getCurrentLesson($group);
    
    // Получаем следующую пару
    $nextLesson = $scheduleManager->getNextLesson($group);
    
    if ($currentLesson) {
        // Вычисляем прогресс пары
        $progress = round($scheduleManager->getLessonProgress($currentLesson));
        
        echo json_encode([
            'success' => true,
            'currentLesson' => $currentLesson,
            'nextLesson' => $nextLesson,
            'progress' => $progress
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => true,
            'currentLesson' => null,
            'nextLesson' => $nextLesson,
            'progress' => 0
        ], JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка получения данных: ' . $e->getMessage()
    ]);
}
?>
