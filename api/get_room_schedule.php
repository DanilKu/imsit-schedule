<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

date_default_timezone_set('Europe/Moscow');

function respond($data){ echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }

function normalize_room($room){
    $s = trim((string)$room);
    if ($s === '') return '';
    
    // Извлекаем цифры из начала строки и первую букву после них (если есть)
    // Работает с форматами: "114", "114a", "114а", "114-1", "114 а" и т.д.
    if (preg_match('/^(\d+)\s*([a-zа-яё]?)/iu', $s, $matches)) {
        $digits = $matches[1];
        $letter = isset($matches[2]) && $matches[2] !== '' ? mb_strtolower($matches[2], 'UTF-8') : '';
        // Преобразуем кириллические буквы в латинские для единообразия
        $cyrToLat = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 
            'е' => 'e', 'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i', 
            'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 
            'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 
            'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 
            'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '', 
            'э' => 'e', 'ю' => 'yu', 'я' => 'ya'
        ];
        if ($letter !== '' && isset($cyrToLat[$letter])) {
            $letter = $cyrToLat[$letter];
        }
        return $digits . $letter;
    }
    // Fallback: если не соответствует паттерну, возвращаем только цифры
    $digits = preg_replace('/\D+/', '', $s);
    return $digits !== '' ? $digits : $s;
}

function room_matches($candidate, $targetNormalized){
    if ($candidate === null || $candidate === '') return false;
    $candidateNorm = normalize_room($candidate);
    
    // Точное совпадение нормализованных номеров
    if ($candidateNorm === $targetNormalized) return true;
    
    // Извлекаем цифры и букву из обоих номеров
    $targetDigits = preg_replace('/[^0-9]/', '', $targetNormalized);
    $targetLetter = preg_replace('/[^a-z]/', '', strtolower($targetNormalized));
    $candidateDigits = preg_replace('/[^0-9]/', '', $candidateNorm);
    $candidateLetter = preg_replace('/[^a-z]/', '', strtolower($candidateNorm));
    
    // Если цифры не совпадают - не совпадают
    if ($targetDigits !== $candidateDigits || $targetDigits === '') {
        // Fallback: проверяем подстроку для сложных случаев
        if (stripos($candidate, $targetNormalized) !== false) return true;
        return false;
    }
    
    // Если оба номера без буквы - совпадают
    if ($targetLetter === '' && $candidateLetter === '') {
        return true;
    }
    
    // Если оба номера с буквой - должны совпадать буквы
    if ($targetLetter !== '' && $candidateLetter !== '') {
        return $targetLetter === $candidateLetter;
    }
    
    // Если один с буквой, а другой без - не совпадают (114 != 114a)
    return false;
}

try {
    $roomParam = $_GET['room'] ?? '';
    $weekParam = isset($_GET['week']) ? (int)$_GET['week'] : 0;
    if ($roomParam === '') respond(['error' => 'room is required']);

    $roomDigits = normalize_room($roomParam);
    $now = new DateTime('now', new DateTimeZone('Europe/Moscow'));
    $currentDay = (int)$now->format('N'); // 1..7
    if ($currentDay > 6) $currentDay = 6;

    $weekNumber = $weekParam ?: (((int)$now->format('W')) % 2 === 0 ? 1 : 2);

    $stmt = $pdo->prepare("SELECT subject_name, teacher_name, group_name, room_number, start_time, end_time, lesson_number, day_of_week
                            FROM schedule_all
                            WHERE week_number = :w
                            ORDER BY day_of_week, start_time");
    $stmt->execute([':w' => $weekNumber]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $slots = [];
    foreach ($rows as $row) {
        if (!room_matches($row['room_number'] ?? '', $roomDigits)) continue;
        $day = (int)($row['day_of_week'] ?? 0);
        if ($day < 1 || $day > 6) continue;
        $lesson = (int)($row['lesson_number'] ?? 0);
        $start = $row['start_time'] ?? '00:00:00';
        $end = $row['end_time'] ?? '00:00:00';
        $subject = $row['subject_name'] ?? '';
        $teacher = $row['teacher_name'] ?? '';

        $key = implode('|', [$day, $lesson, $start, $end, $subject, $teacher]);
        if (!isset($slots[$key])) {
            $slots[$key] = [
                'day' => $day,
                'lesson_number' => $lesson,
                'start_time' => $start,
                'end_time' => $end,
                'subject_name' => $subject,
                'teacher_name' => $teacher,
                'groups' => [],
            ];
        }
        if ($row['group_name'] && !in_array($row['group_name'], $slots[$key]['groups'], true)) {
            $slots[$key]['groups'][] = $row['group_name'];
        }
    }

    // формируем неделю по дням
    $week = [];
    foreach ($slots as $slot) {
        $day = $slot['day'];
        if (!isset($week[$day])) $week[$day] = [];
        $week[$day][] = [
            'day' => $day,
            'subject_name' => $slot['subject_name'],
            'teacher_name' => $slot['teacher_name'],
            'group_name' => implode(', ', $slot['groups']),
            'lesson_number' => $slot['lesson_number'],
            'time' => substr($slot['start_time'],0,5) . '–' . substr($slot['end_time'],0,5),
            'start_time' => $slot['start_time'],
            'end_time' => $slot['end_time'],
        ];
    }

    // сортировка внутри дня
    foreach ($week as $day => &$items) {
        usort($items, function($a, $b){
            if ($a['lesson_number'] === $b['lesson_number']) {
                return strcmp($a['start_time'], $b['start_time']);
            }
            return $a['lesson_number'] <=> $b['lesson_number'];
        });
    }
    unset($items);

    $timeToSeconds = static function($time){
        if (!$time) return 0;
        [$h, $m, $s] = array_pad(explode(':', $time), 3, 0);
        return ((int)$h) * 3600 + ((int)$m) * 60 + ((int)$s);
    };

    // определяем текущую и следующую пару внутри выбранной недели
    $current = null;
    $next = null;
    $currentIndex = null;
    $nowSeconds = $timeToSeconds($now->format('H:i:s'));
    $currentDayItems = $week[$currentDay] ?? [];

    foreach ($currentDayItems as $idx => $item) {
        $startSeconds = $timeToSeconds($item['start_time']);
        $endSeconds = $timeToSeconds($item['end_time']);
        if ($startSeconds <= $nowSeconds && $nowSeconds < $endSeconds) {
            $current = $item;
            $currentIndex = $idx;
            break;
        }
    }

    if ($current && isset($currentDayItems[$currentIndex + 1])) {
        $next = $currentDayItems[$currentIndex + 1];
    }

    if (!$current) {
        foreach ($currentDayItems as $item) {
            if ($timeToSeconds($item['start_time']) > $nowSeconds) {
                $next = $item;
                break;
            }
        }
    }

    if (!$next) {
        for ($day = $currentDay + 1; $day <= 6; $day++) {
            if (!empty($week[$day])) {
                $next = $week[$day][0];
                break;
            }
        }
    }

    respond([
        'room' => $roomDigits,
        'week_number' => $weekNumber,
        'current' => $current ? array_merge($current, ['day' => $currentDay]) : null,
        'next' => $next ? array_merge($next, ['day' => $next['day'] ?? ($next ? ($current ? $currentDay : $currentDay + 1) : $currentDay)]) : null,
        'week' => $week,
    ]);
} catch (Throwable $e) {
    respond(['error' => 'internal_error', 'message' => $e->getMessage()]);
}


