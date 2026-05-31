<?php
/**
 * Конвертер CSV файла в SQL INSERT statements
 * 
 * Использование:
 * php csv_to_sql.php input.csv output.sql
 */

if ($argc < 2) {
    echo "Использование: php csv_to_sql.php <input.csv> [output.sql]\n";
    echo "\n";
    echo "Формат CSV:\n";
    echo "group_name,week_number,day_of_week,lesson_number,subject_name,room_number,teacher_name,start_time,end_time\n";
    exit(1);
}

$inputFile = $argv[1];
$outputFile = $argv[2] ?? 'schedule_insert.sql';

if (!file_exists($inputFile)) {
    die("Ошибка: Файл не найден: $inputFile\n");
}

// Читаем CSV
$scheduleData = [];
if (($handle = fopen($inputFile, "r")) !== FALSE) {
    // Пропускаем заголовок
    $header = fgetcsv($handle);
    
    while (($data = fgetcsv($handle)) !== FALSE) {
        if (count($data) < 9) continue;
        
        $scheduleData[] = [
            'group_name' => trim($data[0]),
            'week_number' => (int)$data[1],
            'day_of_week' => (int)$data[2],
            'lesson_number' => (int)$data[3],
            'subject_name' => trim($data[4]),
            'room_number' => trim($data[5]),
            'teacher_name' => trim($data[6]),
            'start_time' => trim($data[7]),
            'end_time' => trim($data[8])
        ];
    }
    fclose($handle);
}

if (empty($scheduleData)) {
    die("Ошибка: CSV файл пуст или имеет неправильный формат\n");
}

// Генерируем SQL
$sql = "-- SQL для заполнения таблицы schedule_all\n";
$sql .= "-- Сгенерировано: " . date('Y-m-d H:i:s') . "\n";
$sql .= "-- Из файла: $inputFile\n\n";

$sql .= "CREATE TABLE IF NOT EXISTS `schedule_all` (\n";
$sql .= "    `id` int(11) NOT NULL AUTO_INCREMENT,\n";
$sql .= "    `group_name` varchar(100) NOT NULL COMMENT 'Название группы',\n";
$sql .= "    `week_number` tinyint(1) NOT NULL COMMENT '1 или 2 неделя',\n";
$sql .= "    `day_of_week` tinyint(1) NOT NULL COMMENT '1-7 (Пн-Вс)',\n";
$sql .= "    `lesson_number` tinyint(1) NOT NULL COMMENT '1-7 пара',\n";
$sql .= "    `subject_name` varchar(255) NOT NULL COMMENT 'Название предмета',\n";
$sql .= "    `room_number` varchar(50) NOT NULL COMMENT 'Номер аудитории',\n";
$sql .= "    `teacher_name` varchar(100) NOT NULL COMMENT 'ФИО преподавателя',\n";
$sql .= "    `start_time` time NOT NULL COMMENT 'Время начала пары',\n";
$sql .= "    `end_time` time NOT NULL COMMENT 'Время окончания пары',\n";
$sql .= "    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n";
$sql .= "    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n";
$sql .= "    PRIMARY KEY (`id`),\n";
$sql .= "    KEY `idx_group_week_day` (`group_name`, `week_number`, `day_of_week`),\n";
$sql .= "    KEY `idx_lesson` (`group_name`, `week_number`, `day_of_week`, `lesson_number`),\n";
$sql .= "    UNIQUE KEY `unique_lesson` (`group_name`, `week_number`, `day_of_week`, `lesson_number`)\n";
$sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

$sql .= "-- Очистка существующих данных (раскомментируйте если нужно)\n";
$sql .= "-- DELETE FROM `schedule_all` WHERE group_name NOT LIKE '%СПО%';\n\n";

// Удаляем дубликаты
$unique = [];
foreach ($scheduleData as $item) {
    $key = $item['group_name'] . '|' . $item['week_number'] . '|' . 
           $item['day_of_week'] . '|' . $item['lesson_number'];
    if (!isset($unique[$key])) {
        $unique[$key] = $item;
    }
}

if (empty($unique)) {
    $sql .= "-- Нет данных для вставки\n";
    file_put_contents($outputFile, $sql);
    echo "SQL файл создан, но нет данных для вставки\n";
    exit(0);
}

$sql .= "INSERT INTO `schedule_all` (`group_name`, `week_number`, `day_of_week`, `lesson_number`, `subject_name`, `room_number`, `teacher_name`, `start_time`, `end_time`) VALUES\n";

$values = [];
foreach ($unique as $item) {
    $values[] = sprintf(
        "('%s', %d, %d, %d, '%s', '%s', '%s', '%s', '%s')",
        addslashes($item['group_name']),
        $item['week_number'],
        $item['day_of_week'],
        $item['lesson_number'],
        addslashes($item['subject_name']),
        addslashes($item['room_number']),
        addslashes($item['teacher_name']),
        $item['start_time'],
        $item['end_time']
    );
}

$sql .= implode(",\n", $values) . ";\n\n";

$sql .= "-- Статистика\n";
$sql .= "-- Всего записей: " . count($unique) . "\n";
$sql .= "-- Уникальных групп: " . count(array_unique(array_column($unique, 'group_name'))) . "\n";

// Сохраняем
file_put_contents($outputFile, $sql);

echo "✅ SQL файл создан: $outputFile\n";
echo "   Всего записей: " . count($unique) . "\n";
echo "   Уникальных групп: " . count(array_unique(array_column($unique, 'group_name'))) . "\n";


