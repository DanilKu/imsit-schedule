<?php
require_once 'config/database.php';
require_once 'includes/ScheduleManager.php';

echo "<h2>Отладка данных преподавателя</h2>";

try {
    $scheduleManager = new ScheduleManager($pdo);
    
    // Получаем первого активного преподавателя
    $stmt = $pdo->query("SELECT id, full_name FROM teachers WHERE is_active = 1 LIMIT 1");
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($teacher) {
        echo "<h3>Тестируем преподавателя: " . htmlspecialchars($teacher['full_name']) . " (ID: " . $teacher['id'] . ")</h3>";
        
        // Проверяем структуру таблицы teacher_schedule
        echo "<h4>Структура таблицы teacher_schedule:</h4>";
        $stmt = $pdo->query("DESCRIBE teacher_schedule");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>";
        print_r($columns);
        echo "</pre>";
        
        // Проверяем есть ли данные в таблице
        echo "<h4>Данные в таблице teacher_schedule для этого преподавателя:</h4>";
        $stmt = $pdo->prepare("SELECT * FROM teacher_schedule WHERE teacher_id = ? LIMIT 3");
        $stmt->execute([$teacher['id']]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>";
        print_r($data);
        echo "</pre>";
        
        // Тестируем getTeacherSchedule
        echo "<h4>Результат getTeacherSchedule (понедельник, 1 неделя):</h4>";
        $schedule = $scheduleManager->getTeacherSchedule($teacher['id'], 1, 1);
        echo "<pre>";
        print_r($schedule);
        echo "</pre>";
        
        // Тестируем getTeacherCurrentLesson
        echo "<h4>Результат getTeacherCurrentLesson:</h4>";
        $currentLesson = $scheduleManager->getTeacherCurrentLesson($teacher['id']);
        echo "<pre>";
        print_r($currentLesson);
        echo "</pre>";
        
        // Тестируем getTeacherNextLesson
        echo "<h4>Результат getTeacherNextLesson:</h4>";
        $nextLesson = $scheduleManager->getTeacherNextLesson($teacher['id']);
        echo "<pre>";
        print_r($nextLesson);
        echo "</pre>";
        
    } else {
        echo "<p>Нет активных преподавателей в базе данных</p>";
    }
    
} catch (Exception $e) {
    echo "<p>Ошибка: " . $e->getMessage() . "</p>";
}
?>
