<?php
// Альтернативная генерация расписания через HTML
require_once 'config/database.php';

function generateScheduleHTML($schedule, $groupName) {
    $dayNames = [
        1 => 'Понедельник',
        2 => 'Вторник',
        3 => 'Среда',
        4 => 'Четверг',
        5 => 'Пятница',
        6 => 'Суббота'
    ];
    
    // Группируем по неделям и дням
    $weekSchedule = [];
    foreach ($schedule as $lesson) {
        $weekSchedule[$lesson['week_number']][$lesson['day_of_week']][] = $lesson;
    }
    
    $html = '<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Расписание группы ' . htmlspecialchars($groupName) . '</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #3b82f6;
            font-size: 28px;
            margin: 0;
        }
        .week-section {
            margin-bottom: 40px;
        }
        .week-title {
            color: #22c55e;
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 20px;
            text-align: center;
        }
        .schedule-table {
            width: 100%;
            border-collapse: collapse;
            background: #1e293b;
            border-radius: 8px;
            overflow: hidden;
        }
        .schedule-table th {
            background: #334155;
            color: #f1f5f9;
            padding: 15px 10px;
            text-align: center;
            font-weight: bold;
            border: 1px solid #475569;
        }
        .schedule-table td {
            padding: 12px 8px;
            border: 1px solid #475569;
            vertical-align: top;
            min-height: 60px;
        }
        .lesson {
            background: #334155;
            border-radius: 6px;
            padding: 8px;
            margin-bottom: 8px;
            border-left: 4px solid #3b82f6;
        }
        .lesson-number {
            font-weight: bold;
            color: #60a5fa;
            font-size: 14px;
        }
        .lesson-subject {
            color: #e2e8f0;
            font-size: 13px;
            margin: 4px 0;
            line-height: 1.3;
        }
        .lesson-room {
            color: #94a3b8;
            font-size: 12px;
        }
        .lesson-teacher {
            color: #cbd5e1;
            font-size: 11px;
            margin-top: 2px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📅 Расписание группы ' . htmlspecialchars($groupName) . '</h1>
    </div>';
    
    // Генерируем расписание для каждой недели
    for ($week = 1; $week <= 2; $week++) {
        if (isset($weekSchedule[$week])) {
            $html .= '<div class="week-section">
                <div class="week-title">📅 ' . $week . ' неделя</div>
                <table class="schedule-table">
                    <thead>
                        <tr>';
            
            // Заголовки дней
            for ($day = 1; $day <= 6; $day++) {
                $html .= '<th>' . $dayNames[$day] . '</th>';
            }
            
            $html .= '</tr>
                    </thead>
                    <tbody>
                        <tr>';
            
            // Содержимое дней
            for ($day = 1; $day <= 6; $day++) {
                $html .= '<td>';
                
                if (isset($weekSchedule[$week][$day])) {
                    foreach ($weekSchedule[$week][$day] as $lesson) {
                        $html .= '<div class="lesson">
                            <div class="lesson-number">' . $lesson['lesson_number'] . ' пара</div>
                            <div class="lesson-subject">' . htmlspecialchars($lesson['subject_name']) . '</div>
                            <div class="lesson-room">🏢 ' . htmlspecialchars($lesson['room_number']) . '</div>
                            <div class="lesson-teacher">👨‍🏫 ' . htmlspecialchars($lesson['teacher_name']) . '</div>
                        </div>';
                    }
                } else {
                    $html .= '<div style="color: #64748b; text-align: center; padding: 20px;">Нет пар</div>';
                }
                
                $html .= '</td>';
            }
            
            $html .= '</tr>
                    </tbody>
                </table>
            </div>';
        }
    }
    
    $html .= '</body>
</html>';
    
    return $html;
}

// Если вызван напрямую - показываем результат
if (isset($_GET['group'])) {
    $groupName = $_GET['group'];
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM schedule WHERE group_name = ? ORDER BY week_number, day_of_week, lesson_number");
        $stmt->execute([$groupName]);
        $schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($schedule)) {
            echo "Расписание для группы $groupName не найдено.";
        } else {
            echo generateScheduleHTML($schedule, $groupName);
        }
    } catch (Exception $e) {
        echo "Ошибка: " . $e->getMessage();
    }
} else {
    echo "Использование: generate_schedule_html.php?group=Исип-06";
}
?>
