<?php
/**
 * API endpoint для получения расписания преподавателя
 * Используется iOS приложением
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/ScheduleManager.php';

try {
    $teacher = $_GET['teacher'] ?? '';
    $week = isset($_GET['week']) ? (int)$_GET['week'] : 1;
    $day = isset($_GET['day']) ? (int)$_GET['day'] : 0; // 0 = все дни недели
    
    if (empty($teacher)) {
        echo json_encode([
            'success' => false,
            'error' => 'Не указан преподаватель'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $scheduleManager = new ScheduleManager($pdo);
    
    if ($day > 0 && $day <= 6) {
        // Получаем расписание для одного дня
        $schedule = $scheduleManager->getTeacherSchedule($teacher, $week, $day);
        echo json_encode([
            'success' => true,
            'schedule' => $schedule,
            'day' => $day,
            'week' => $week
        ], JSON_UNESCAPED_UNICODE);
    } else {
        // Получаем расписание на всю неделю
        $weekSchedule = [];
        for ($d = 1; $d <= 6; $d++) {
            $weekSchedule[$d] = $scheduleManager->getTeacherSchedule($teacher, $week, $d);
        }
        
        // Получаем текущую и следующую пару
        $currentLesson = $scheduleManager->getTeacherCurrentLesson($teacher);
        $nextLesson = $scheduleManager->getTeacherNextLesson($teacher);
        
        echo json_encode([
            'success' => true,
            'week' => $weekSchedule,
            'currentWeek' => $week,
            'currentLesson' => $currentLesson,
            'nextLesson' => $nextLesson
        ], JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

