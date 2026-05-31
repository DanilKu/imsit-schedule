<?php
// Отдельные функции для тестирования генерации изображения
require_once 'config/database.php';

// Функция для переноса текста на две строки
function wrapText($text, $fontPath, $fontSize, $maxWidth) {
    $lines = [];
    $words = explode(' ', $text);
    $currentLine = '';
    
    foreach ($words as $word) {
        $testLine = $currentLine === '' ? $word : $currentLine . ' ' . $word;
        $bbox = imageftbbox($fontSize, 0, $fontPath, $testLine);
        $testWidth = $bbox[2] - $bbox[0];
        
        if ($testWidth <= $maxWidth) {
            $currentLine = $testLine;
        } else {
            $lines[] = $currentLine;
            $currentLine = $word;
            if (count($lines) >= 2) { // Ограничиваем до 2 строк
                $currentLine = trim($currentLine);
                if (!empty($currentLine)) {
                    $lines[1] = (isset($lines[1]) ? $lines[1] : '') . '...';
                }
                break;
            }
        }
    }
    if (!empty($currentLine)) {
        $lines[] = $currentLine;
    }
    return array_slice($lines, 0, 2); // Максимум 2 строки
}

// Функция генерации изображения расписания
function generateScheduleImage($schedule_all, $groupName) {
    error_log("Generating schedule image for group: $groupName, lessons: " . count($schedule));
    
    // Путь к шрифту с поддержкой кириллицы
    $fontPath = __DIR__ . '/assets/fonts/DejaVuSans.ttf';
    error_log("Checking font path: " . $fontPath);
    error_log("Current directory: " . __DIR__);
    error_log("Font file exists: " . (file_exists($fontPath) ? 'YES' : 'NO'));
    
    if (!file_exists($fontPath)) {
        error_log("Font file not found: " . $fontPath . ". Falling back to text schedule.");
        return false; // Fallback to text schedule
    }
    
    // Названия дней на русском
    $dayNames = [
        1 => 'Понедельник',
        2 => 'Вторник', 
        3 => 'Среда',
        4 => 'Четверг',
        5 => 'Пятница',
        6 => 'Суббота'
    ];
    
    // Группируем расписание по неделям и дням
    $weekSchedule = [];
    foreach ($schedule as $lesson) {
        $weekSchedule[$lesson['week_number']][$lesson['day_of_week']][] = $lesson;
    }
    
    // Вычисляем динамическую высоту на основе контента
    $headerHeight = 100;
    $dayHeaderHeight = 70;
    $weekHeaderHeight = 70;
    $lessonBlockHeight = 120; // Высота блока для каждой пары
    
    $totalWeeks = count($weekSchedule);
    if ($totalWeeks === 0) $totalWeeks = 1;
    
    $maxLessonsPerDay = 0;
    foreach ($weekSchedule as $weekNum => $days) {
        foreach ($days as $dayNum => $lessons) {
            $maxLessonsPerDay = max($maxLessonsPerDay, count($lessons));
        }
    }
    
    // Вычисляем общую высоту
    $contentHeight = 0;
    if ($maxLessonsPerDay > 0) {
        $contentHeight = $maxLessonsPerDay * $lessonBlockHeight * $totalWeeks;
    } else {
        $contentHeight = $lessonBlockHeight * $totalWeeks;
    }
    
    $height = $headerHeight + ($dayHeaderHeight * 2) + ($weekHeaderHeight * $totalWeeks) + $contentHeight + 50;
    $width = 1600; // Увеличиваем ширину для лучшей читаемости
    $height = max(800, $height);
    
    $image = imagecreate($width, $height);
    
    if (!$image) {
        error_log("Failed to create image");
        return false;
    }
    
    // Цвета
    $bgColor = imagecolorallocate($image, 15, 23, 42); // Темно-синий фон
    $textColor = imagecolorallocate($image, 226, 232, 240); // Светло-серый текст
    $headerColor = imagecolorallocate($image, 59, 130, 246); // Синий заголовок
    $borderColor = imagecolorallocate($image, 71, 85, 105); // Серые границы
    $weekColor = imagecolorallocate($image, 34, 197, 94); // Зеленый для недель
    $lessonTimeColor = imagecolorallocate($image, 156, 163, 175); // Серый для времени
    $groupTeacherColor = imagecolorallocate($image, 96, 165, 250); // Голубой для группы/преподавателя
    
    // Заливаем фон
    imagefill($image, 0, 0, $bgColor);
    
    // Заголовок
    $headerText = "Расписание группы " . $groupName;
    $fontSizeHeader = 32;
    $bboxHeader = imageftbbox($fontSizeHeader, 0, $fontPath, $headerText);
    $headerTextWidth = $bboxHeader[2] - $bboxHeader[0];
    imagefttext($image, $fontSizeHeader, 0, ($width - $headerTextWidth) / 2, 60, $headerColor, $fontPath, $headerText);
    
    // Макет таблицы
    $startX = 50;
    $startY = $headerHeight;
    $cellWidth = ($width - $startX * 2) / 6; // 6 дней
    
    $fontSizeDay = 22;
    $fontSizeLessonNum = 20;
    $fontSizeSubject = 18;
    $fontSizeRoom = 16;
    $fontSizeTime = 14;
    
    $currentY = $startY;
    
    // Рисуем заголовки дней
    for ($i = 1; $i <= 6; $i++) {
        $dayText = $dayNames[$i];
        $bboxDay = imageftbbox($fontSizeDay, 0, $fontPath, $dayText);
        $dayTextWidth = $bboxDay[2] - $bboxDay[0];
        imagefttext($image, $fontSizeDay, 0, $startX + ($i - 1) * $cellWidth + ($cellWidth - $dayTextWidth) / 2, $currentY + 40, $headerColor, $fontPath, $dayText);
    }
    imageline($image, $startX, $currentY + 60, $width - $startX, $currentY + 60, $borderColor);
    $currentY += $dayHeaderHeight;
    
    // Рисуем расписание для каждой недели
    for ($week = 1; $week <= 2; $week++) {
        $weekHeaderText = "Неделя " . $week;
        imagefttext($image, $fontSizeDay, 0, $startX, $currentY + 40, $weekColor, $fontPath, $weekHeaderText);
        imageline($image, $startX, $currentY + 60, $width - $startX, $currentY + 60, $borderColor);
        $currentY += $weekHeaderHeight;
        
        // Находим максимальное количество пар для этой недели
        $currentWeekMaxLessons = 0;
        if (isset($weekSchedule[$week])) {
            foreach ($weekSchedule[$week] as $dayNum => $lessons) {
                $currentWeekMaxLessons = max($currentWeekMaxLessons, count($lessons));
            }
        }
        $currentWeekMaxLessons = max(1, $currentWeekMaxLessons);
        
        for ($day = 1; $day <= 6; $day++) {
            $lessons = $weekSchedule[$week][$day] ?? [];
            $dayX = $startX + ($day - 1) * $cellWidth;
            $lessonY = $currentY;
            
            foreach ($lessons as $lesson) {
                $lessonNumText = "Пара " . $lesson['lesson_number'];
                $lessonTimeText = "({$lesson['start_time']}-{$lesson['end_time']})";
                $subjectText = $lesson['subject_name'];
                $roomText = "Ауд: " . $lesson['room_number'];
                $teacherText = $lesson['teacher_name'] ? "Преп: " . $lesson['teacher_name'] : '';
                $groupLessonText = $lesson['group_name'] ? "Группа: " . $lesson['group_name'] : '';
                
                $textOffset = 10;
                $currentLessonTextY = $lessonY + 25;
                
                // Номер пары и время
                imagefttext($image, $fontSizeLessonNum, 0, $dayX + $textOffset, $currentLessonTextY, $textColor, $fontPath, $lessonNumText);
                $bboxLessonNum = imageftbbox($fontSizeLessonNum, 0, $fontPath, $lessonNumText);
                imagefttext($image, $fontSizeTime, 0, $dayX + $textOffset + $bboxLessonNum[2], $currentLessonTextY, $lessonTimeColor, $fontPath, $lessonTimeText);
                
                $currentLessonTextY += $fontSizeLessonNum * 1.2;
                
                // Название предмета (перенос на 2 строки)
                $subjectLines = wrapText($subjectText, $fontPath, $fontSizeSubject, $cellWidth - ($textOffset * 2));
                $lineHeightSubject = $fontSizeSubject * 1.2;
                imagefttext($image, $fontSizeSubject, 0, $dayX + $textOffset, $currentLessonTextY, $textColor, $fontPath, $subjectLines[0]);
                if (isset($subjectLines[1])) {
                    $currentLessonTextY += $lineHeightSubject;
                    imagefttext($image, $fontSizeSubject, 0, $dayX + $textOffset, $currentLessonTextY, $textColor, $fontPath, $subjectLines[1]);
                }
                
                $currentLessonTextY += $lineHeightSubject;
                
                // Аудитория
                imagefttext($image, $fontSizeRoom, 0, $dayX + $textOffset, $currentLessonTextY, $textColor, $fontPath, $roomText);
                
                $currentLessonTextY += $fontSizeRoom * 1.2;
                
                // Преподаватель (если есть)
                if ($teacherText) {
                    imagefttext($image, $fontSizeRoom, 0, $dayX + $textOffset, $currentLessonTextY, $groupTeacherColor, $fontPath, $teacherText);
                    $currentLessonTextY += $fontSizeRoom * 1.2;
                }
                // Группа (если есть для расписания преподавателя)
                if ($groupLessonText) {
                    imagefttext($image, $fontSizeRoom, 0, $dayX + $textOffset, $currentLessonTextY, $groupTeacherColor, $fontPath, $groupLessonText);
                    $currentLessonTextY += $fontSizeRoom * 1.2;
                }
                
                $lessonY += $lessonBlockHeight;
            }
            // Рисуем вертикальную линию для разделения дней
            imageline($image, $dayX + $cellWidth, $currentY, $dayX + $cellWidth, $currentY + ($currentWeekMaxLessons * $lessonBlockHeight), $borderColor);
        }
        $currentY += ($currentWeekMaxLessons * $lessonBlockHeight);
    }
    
    // Сохраняем изображение
    $filename = "temp_schedule_" . time() . ".png";
    $filepath = dirname(__DIR__) . "/temp/" . $filename;
    
    // Создаем папку temp если её нет
    if (!file_exists(dirname($filepath))) {
        if (!mkdir(dirname($filepath), 0755, true)) {
            error_log("Failed to create temp directory: " . dirname($filepath));
            imagedestroy($image);
            return false;
        }
    }
    
    if (!imagepng($image, $filepath)) {
        error_log("Failed to save image to: " . $filepath);
        imagedestroy($image);
        return false;
    }
    
    imagedestroy($image);
    error_log("Image saved successfully: " . $filepath);
    
    return $filepath;
}
?>
