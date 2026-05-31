<?php
header('Content-Type: application/json; charset=utf-8');

try {
    require_once dirname(__DIR__) . '/config/database.php';
    require_once dirname(__DIR__) . '/includes/ScheduleManager.php';
    
    $teacherName = trim($_GET['teacher_name'] ?? '');
    
    if (empty($teacherName)) {
        throw new Exception('Не указано имя преподавателя');
    }
    
    $scheduleManager = new ScheduleManager($pdo);
    $currentLesson = $scheduleManager->getTeacherCurrentLesson($teacherName);
    
    // Получаем следующую пару
    $nextLesson = $scheduleManager->getTeacherNextLesson($teacherName);
    
    if ($currentLesson) {
        $progress = $scheduleManager->getLessonProgress($currentLesson);
        
        echo json_encode([
            'success' => true,
            'currentLesson' => $currentLesson,
            'nextLesson' => $nextLesson,
            'progress' => round($progress)
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
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
