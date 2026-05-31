<?php
header('Content-Type: application/json; charset=utf-8');

try {
    require_once dirname(__DIR__) . '/config/database.php';
    require_once dirname(__DIR__) . '/includes/ScheduleManager.php';
    
    $teacherId = (int)($_GET['teacher_id'] ?? 0);
    $week = (int)($_GET['week'] ?? 1);
    $day = (int)($_GET['day'] ?? 1);
    
    if ($teacherId <= 0) {
        throw new Exception('Неверный ID преподавателя');
    }
    
    // Получаем информацию о преподавателе
    $stmt = $pdo->prepare("SELECT full_name, short_name FROM teachers WHERE id = ? AND is_active = 1");
    $stmt->execute([$teacherId]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$teacher) {
        throw new Exception('Преподаватель не найден');
    }
    
    // Получаем расписание преподавателя
    $scheduleManager = new ScheduleManager($pdo);
    $teacherSchedule = $scheduleManager->getTeacherSchedule($teacherId, $week, $day);
    
    echo json_encode([
        'success' => true,
        'teacher' => $teacher,
        'schedule' => $teacherSchedule,
        'week' => $week,
        'day' => $day
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
