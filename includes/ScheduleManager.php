<?php

class ScheduleManager {
    private $pdo;
    
    // Стандартное время пар
    private $lessonTimes = [
        1 => ['08:00:00', '08:30:00'],
        2 => ['09:40:00', '11:10:00'],
        3 => ['11:30:00', '13:00:00'],
        4 => ['13:10:00', '14:40:00'],
        5 => ['14:50:00', '16:20:00'],
        6 => ['16:30:00', '18:00:00'],
        7 => ['18:10:00', '19:40:00']
    ];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->ensureSchema();
    }
    
    private function ensureSchema(): void {
        // Создаем таблицы если их нет
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS schedule_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            current_week TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 или 2 неделя',
            current_day TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1-7 (Пн-Вс)',
            `current_time` TIME NOT NULL DEFAULT '08:00:00',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS schedule (
            id INT AUTO_INCREMENT PRIMARY KEY,
            group_name ENUM('Исип-05', 'Исип-06') NOT NULL,
            week_number TINYINT(1) NOT NULL COMMENT '1 или 2 неделя',
            day_of_week TINYINT(1) NOT NULL COMMENT '1-7 (Пн-Вс)',
            lesson_number TINYINT(1) NOT NULL COMMENT '1-7 пара',
            subject_name VARCHAR(255) NOT NULL,
            room_number VARCHAR(50) NOT NULL,
            teacher_name VARCHAR(100) NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            INDEX idx_group_week_day (group_name, week_number, day_of_week),
            INDEX idx_current_lesson (group_name, week_number, day_of_week, lesson_number),
            UNIQUE KEY unique_lesson (group_name, week_number, day_of_week, lesson_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Проверяем есть ли настройки
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM schedule_settings");
        if ($stmt->fetchColumn() == 0) {
            $this->pdo->exec("INSERT INTO schedule_settings (current_week, current_day, `current_time`) VALUES (1, 1, '08:00:00')");
        }
    }
    
    // Получение настроек расписания
    public function getSettings(): array {
        $stmt = $this->pdo->query("SELECT * FROM schedule_settings ORDER BY id DESC LIMIT 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$settings) {
            // Если настроек нет, создаем с текущим временем по МСК
            $this->updateSettingsWithCurrentTime();
            $stmt = $this->pdo->query("SELECT * FROM schedule_settings ORDER BY id DESC LIMIT 1");
            $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        return $settings ?: [
            'current_week' => 1,
            'current_day' => 1,
            'current_time' => '08:00:00'
        ];
    }
    
    // Автоматическое обновление времени по МСК
    public function updateSettingsWithCurrentTime(): bool {
        // Получаем текущее время по МСК
        $moscowTime = new DateTime('now', new DateTimeZone('Europe/Moscow'));
        $currentTime = $moscowTime->format('H:i:s');
        
        // Определяем день недели (1 = Понедельник, 7 = Воскресенье)
        $dayOfWeek = $moscowTime->format('N');
        
        // Определяем неделю (можно настроить логику)
        $weekOfYear = $moscowTime->format('W');
        $currentWeek = ($weekOfYear % 2 == 0) ? 1 : 2; // Инвертированная логика: четные недели = 1, нечетные = 2
        
        return $this->updateSettings($currentWeek, $dayOfWeek, $currentTime);
    }
    
    // Обновление настроек
    public function updateSettings(int $week, int $day, string $time): bool {
        $stmt = $this->pdo->prepare("UPDATE schedule_settings SET current_week = ?, current_day = ?, `current_time` = ? WHERE id = (SELECT id FROM (SELECT id FROM schedule_settings ORDER BY id DESC LIMIT 1) as temp)");
        return $stmt->execute([$week, $day, $time]);
    }
    
    // Получение расписания для группы
    public function getSchedule(string $groupName, int $week, int $day): array {
        $stmt = $this->pdo->prepare("
            SELECT * FROM schedule_all 
            WHERE group_name = ? AND week_number = ? AND day_of_week = ? 
            ORDER BY lesson_number
        ");
        $stmt->execute([$groupName, $week, $day]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Получение текущей пары
    public function getCurrentLesson(string $groupName): ?array {
        $settings = $this->getSettings();
        $currentTime = $settings['current_time'];

        $stmt = $this->pdo->prepare("
            SELECT * FROM schedule_all
            WHERE group_name = ? AND week_number = ? AND day_of_week = ?
            AND start_time <= ? AND end_time > ?
            ORDER BY lesson_number
            LIMIT 1
        ");
        $stmt->execute([$groupName, $settings['current_week'], $settings['current_day'], $currentTime, $currentTime]);

        $current = $stmt->fetch(PDO::FETCH_ASSOC);

        return $current ?: null;
    }
    
    // Получение следующей пары
    public function getNextLesson(string $groupName): ?array {
        $settings = $this->getSettings();
        $currentTime = $settings['current_time'];
        $currentWeek = $settings['current_week'];
        $currentDay = $settings['current_day'];
        
        // Сначала ищем в текущем дне
        $stmt = $this->pdo->prepare("
            SELECT * FROM schedule_all 
            WHERE group_name = ? AND week_number = ? AND day_of_week = ? 
            AND start_time > ?
            ORDER BY start_time 
            LIMIT 1
        ");
        $stmt->execute([$groupName, $currentWeek, $currentDay, $currentTime]);
        $next = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($next) {
            return $next;
        }
        
        // Если нет в текущем дне, ищем в следующих днях текущей недели
        $stmt = $this->pdo->prepare("
            SELECT * FROM schedule_all 
            WHERE group_name = ? AND week_number = ? AND day_of_week > ? 
            ORDER BY day_of_week, start_time 
            LIMIT 1
        ");
        $stmt->execute([$groupName, $currentWeek, $currentDay]);
        $next = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($next) {
            return $next;
        }
        
        // Если нет в текущей неделе, ищем в следующей неделе
        $nextWeek = $currentWeek == 1 ? 2 : 1;
        $stmt = $this->pdo->prepare("
            SELECT * FROM schedule_all 
            WHERE group_name = ? AND week_number = ? 
            ORDER BY day_of_week, start_time 
            LIMIT 1
        ");
        $stmt->execute([$groupName, $nextWeek]);
        $next = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $next ?: null;
    }
    
    // Получение текущей или следующей пары
    public function getCurrentOrNextLesson(string $groupName): ?array {
        $current = $this->getCurrentLesson($groupName);
        if ($current) {
            return $current;
        }
        
        return $this->getNextLesson($groupName);
    }
    
    // Получение прогресса текущей пары
    public function getLessonProgress(array $lesson): float {
        $settings = $this->getSettings();
        $currentTime = strtotime($settings['current_time']);
        $startTime = strtotime($lesson['start_time']);
        $endTime = strtotime($lesson['end_time']);
        
        if ($currentTime < $startTime) {
            return 0;
        }
        
        if ($currentTime >= $endTime) {
            return 100;
        }
        
        $totalDuration = $endTime - $startTime;
        $elapsed = $currentTime - $startTime;
        
        return ($elapsed / $totalDuration) * 100;
    }
    
    // Добавление пары
    public function addLesson(array $data): bool {
        $stmt = $this->pdo->prepare("
            INSERT INTO schedule (group_name, week_number, day_of_week, lesson_number, subject_name, room_number, teacher_name, start_time, end_time)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $data['group_name'],
            $data['week_number'],
            $data['day_of_week'],
            $data['lesson_number'],
            $data['subject_name'],
            $data['room_number'],
            $data['teacher_name'],
            $data['start_time'],
            $data['end_time']
        ]);
    }
    
    // Обновление пары
    public function updateLesson(int $id, array $data): bool {
        $stmt = $this->pdo->prepare("
            UPDATE schedule 
            SET group_name = ?, week_number = ?, day_of_week = ?, lesson_number = ?, 
                subject_name = ?, room_number = ?, teacher_name = ?, start_time = ?, end_time = ?
            WHERE id = ?
        ");
        
        return $stmt->execute([
            $data['group_name'],
            $data['week_number'],
            $data['day_of_week'],
            $data['lesson_number'],
            $data['subject_name'],
            $data['room_number'],
            $data['teacher_name'],
            $data['start_time'],
            $data['end_time'],
            $id
        ]);
    }
    
    // Удаление пары
    public function deleteLesson(int $id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM schedule_all WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    // Получение пары по ID
    public function getLessonById(int $id): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM schedule_all WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    // Получение времени пар
    public function getLessonTimes(): array {
        return $this->lessonTimes;
    }
    
    // Получение названий дней недели
    public function getDayNames(): array {
        return [
            1 => 'Понедельник',
            2 => 'Вторник', 
            3 => 'Среда',
            4 => 'Четверг',
            5 => 'Пятница',
            6 => 'Суббота',
            7 => 'Воскресенье'
        ];
    }
    
    // Получение сокращенных названий дней
    public function getDayShortNames(): array {
        return [
            1 => 'Пн',
            2 => 'Вт',
            3 => 'Ср', 
            4 => 'Чт',
            5 => 'Пт',
            6 => 'Сб',
            7 => 'Вс'
        ];
    }
    
    // Экспорт расписания в CSV
    public function exportToCSV(string $groupName, int $week): string {
        $schedule = [];
        for ($day = 1; $day <= 7; $day++) {
            $daySchedule = $this->getSchedule($groupName, $week, $day);
            $schedule[$day] = $daySchedule;
        }
        
        $csv = "Группа,Неделя,День,Пара,Предмет,Аудитория,Преподаватель,Время начала,Время окончания\n";
        
        foreach ($schedule as $day => $lessons) {
            foreach ($lessons as $lesson) {
                $csv .= sprintf(
                    '"%s","%s","%s","%s","%s","%s","%s","%s","%s"' . "\n",
                    $lesson['group_name'],
                    $lesson['week_number'],
                    $this->getDayNames()[$lesson['day_of_week']],
                    $lesson['lesson_number'],
                    $lesson['subject_name'],
                    $lesson['room_number'],
                    $lesson['teacher_name'],
                    $lesson['start_time'],
                    $lesson['end_time']
                );
            }
        }
        
        return $csv;
    }
    
    public function getTeacherSchedule($teacherName, $week, $day) {
        // Получаем расписание преподавателя из schedule_all
        $stmt = $this->pdo->prepare("
            SELECT * FROM schedule_all
            WHERE teacher_name = ? 
            AND week_number = ? 
            AND day_of_week = ?
            ORDER BY lesson_number ASC, start_time ASC
        ");
        
        $stmt->execute([$teacherName, $week, $day]);
        $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Группируем пары по ключевым параметрам
        $groupedLessons = [];
        
        foreach ($lessons as $lesson) {
            // Создаем ключ для группировки: номер пары + время + аудитория + предмет
            $groupKey = $lesson['lesson_number'] . '_' . $lesson['start_time'] . '_' . $lesson['end_time'] . '_' . $lesson['room_number'] . '_' . $lesson['subject_name'];
            
            if (!isset($groupedLessons[$groupKey])) {
                // Создаем новую группу
                $groupedLessons[$groupKey] = [
                    'id' => $lesson['id'],
                    'group_name' => $lesson['group_name'],
                    'week_number' => $lesson['week_number'],
                    'day_of_week' => $lesson['day_of_week'],
                    'lesson_number' => $lesson['lesson_number'],
                    'subject_name' => $lesson['subject_name'],
                    'room_number' => $lesson['room_number'],
                    'teacher_name' => $lesson['teacher_name'],
                    'start_time' => $lesson['start_time'],
                    'end_time' => $lesson['end_time'],
                    'created_at' => $lesson['created_at'],
                    'updated_at' => $lesson['updated_at'],
                    'groups' => [$lesson['group_name']] // Массив групп для этой пары
                ];
            } else {
                // Добавляем группу к существующей паре
                if (!in_array($lesson['group_name'], $groupedLessons[$groupKey]['groups'])) {
                    $groupedLessons[$groupKey]['groups'][] = $lesson['group_name'];
                }
            }
        }
        
        // Преобразуем обратно в массив и сортируем
        $result = array_values($groupedLessons);
        usort($result, function($a, $b) {
            return $a['lesson_number'] <=> $b['lesson_number'];
        });
        
        return $result;
    }
    
    public function getTeacherCurrentLesson($teacherName) {
        $settings = $this->getSettings();
        $currentTime = $settings['current_time'];
        $currentDay = $settings['current_day'];
        $currentWeek = $settings['current_week'];
        
        // Получаем все текущие пары преподавателя из schedule_all
        $stmt = $this->pdo->prepare("
            SELECT * FROM schedule_all
            WHERE teacher_name = ? 
            AND week_number = ? 
            AND day_of_week = ? 
            AND start_time <= ? 
            AND end_time >= ?
            ORDER BY start_time ASC
        ");
        
        $stmt->execute([$teacherName, $currentWeek, $currentDay, $currentTime, $currentTime]);
        $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($lessons)) {
            return null;
        }
        
        // Группируем пары (аналогично getTeacherSchedule)
        $groupedLessons = [];
        
        foreach ($lessons as $lesson) {
            $groupKey = $lesson['lesson_number'] . '_' . $lesson['start_time'] . '_' . $lesson['end_time'] . '_' . $lesson['room_number'] . '_' . $lesson['subject_name'];
            
            if (!isset($groupedLessons[$groupKey])) {
                $groupedLessons[$groupKey] = [
                    'id' => $lesson['id'],
                    'group_name' => $lesson['group_name'],
                    'week_number' => $lesson['week_number'],
                    'day_of_week' => $lesson['day_of_week'],
                    'lesson_number' => $lesson['lesson_number'],
                    'subject_name' => $lesson['subject_name'],
                    'room_number' => $lesson['room_number'],
                    'teacher_name' => $lesson['teacher_name'],
                    'start_time' => $lesson['start_time'],
                    'end_time' => $lesson['end_time'],
                    'created_at' => $lesson['created_at'],
                    'updated_at' => $lesson['updated_at'],
                    'groups' => [$lesson['group_name']]
                ];
            } else {
                if (!in_array($lesson['group_name'], $groupedLessons[$groupKey]['groups'])) {
                    $groupedLessons[$groupKey]['groups'][] = $lesson['group_name'];
                }
            }
        }
        
        // Возвращаем первую сгруппированную пару
        return array_values($groupedLessons)[0];
    }
    
    public function getTeacherNextLesson($teacherName) {
        $settings = $this->getSettings();
        $currentTime = $settings['current_time'];
        $currentDay = $settings['current_day'];
        $currentWeek = $settings['current_week'];
        
        // Получаем все следующие пары преподавателя из schedule_all
        $stmt = $this->pdo->prepare("
            SELECT * FROM schedule_all
            WHERE teacher_name = ? 
            AND week_number = ? 
            AND day_of_week = ? 
            AND start_time > ?
            ORDER BY start_time ASC
        ");
        
        $stmt->execute([$teacherName, $currentWeek, $currentDay, $currentTime]);
        $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($lessons)) {
            return null;
        }
        
        // Группируем пары (аналогично getTeacherSchedule)
        $groupedLessons = [];
        
        foreach ($lessons as $lesson) {
            $groupKey = $lesson['lesson_number'] . '_' . $lesson['start_time'] . '_' . $lesson['end_time'] . '_' . $lesson['room_number'] . '_' . $lesson['subject_name'];
            
            if (!isset($groupedLessons[$groupKey])) {
                $groupedLessons[$groupKey] = [
                    'id' => $lesson['id'],
                    'group_name' => $lesson['group_name'],
                    'week_number' => $lesson['week_number'],
                    'day_of_week' => $lesson['day_of_week'],
                    'lesson_number' => $lesson['lesson_number'],
                    'subject_name' => $lesson['subject_name'],
                    'room_number' => $lesson['room_number'],
                    'teacher_name' => $lesson['teacher_name'],
                    'start_time' => $lesson['start_time'],
                    'end_time' => $lesson['end_time'],
                    'created_at' => $lesson['created_at'],
                    'updated_at' => $lesson['updated_at'],
                    'groups' => [$lesson['group_name']]
                ];
            } else {
                if (!in_array($lesson['group_name'], $groupedLessons[$groupKey]['groups'])) {
                    $groupedLessons[$groupKey]['groups'][] = $lesson['group_name'];
                }
            }
        }
        
        // Возвращаем первую сгруппированную пару
        return array_values($groupedLessons)[0];
    }
}
