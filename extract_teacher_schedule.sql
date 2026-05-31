-- SQL скрипт для извлечения расписания преподавателей из schedule_all
-- Заполняет таблицу teacher_schedule на основе данных из schedule_all

-- Убедитесь, что таблица teacher_schedule существует
CREATE TABLE IF NOT EXISTS `teacher_schedule` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `teacher_id` int(11) NOT NULL COMMENT 'ID преподавателя из таблицы teachers',
    `week_number` tinyint(1) NOT NULL COMMENT '1 или 2 неделя',
    `day_of_week` tinyint(1) NOT NULL COMMENT '1-7 (Пн-Вс)',
    `lesson_number` tinyint(1) NOT NULL COMMENT '1-7 пара',
    `subject_name` varchar(255) NOT NULL COMMENT 'Название предмета',
    `room_number` varchar(50) NOT NULL COMMENT 'Номер аудитории',
    `group_name` varchar(100) NOT NULL COMMENT 'Название группы',
    `start_time` time NOT NULL COMMENT 'Время начала пары',
    `end_time` time NOT NULL COMMENT 'Время окончания пары',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_teacher_week_day` (`teacher_id`, `week_number`, `day_of_week`),
    KEY `idx_teacher_lesson` (`teacher_id`, `week_number`, `day_of_week`, `lesson_number`),
    UNIQUE KEY `unique_teacher_lesson` (`teacher_id`, `week_number`, `day_of_week`, `lesson_number`, `group_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Очистка существующих данных (опционально)
-- DELETE FROM `teacher_schedule`;

-- Извлечение расписания преподавателей из schedule_all
-- Сопоставление по полному имени преподавателя
INSERT INTO `teacher_schedule` (
    `teacher_id`,
    `week_number`,
    `day_of_week`,
    `lesson_number`,
    `subject_name`,
    `room_number`,
    `group_name`,
    `start_time`,
    `end_time`,
    `created_at`,
    `updated_at`
)
SELECT 
    t.id AS teacher_id,
    s.week_number,
    s.day_of_week,
    s.lesson_number,
    s.subject_name,
    s.room_number,
    s.group_name,
    s.start_time AS start_time,
    s.end_time,
    NOW() AS created_at,
    NOW() AS updated_at
FROM schedule_all s
INNER JOIN teachers t ON s.teacher_name COLLATE utf8mb4_unicode_ci = t.full_name COLLATE utf8mb4_unicode_ci
WHERE s.teacher_name IS NOT NULL 
  AND s.teacher_name != ''
  AND t.full_name IS NOT NULL
ON DUPLICATE KEY UPDATE
    `subject_name` = VALUES(`subject_name`),
    `room_number` = VALUES(`room_number`),
    `group_name` = VALUES(`group_name`),
    `start_time` = VALUES(`start_time`),
    `end_time` = VALUES(`end_time`),
    `updated_at` = NOW();

-- Проверка результатов
SELECT 
    COUNT(*) as total_records,
    COUNT(DISTINCT teacher_id) as unique_teachers,
    COUNT(DISTINCT group_name) as unique_groups
FROM teacher_schedule;

