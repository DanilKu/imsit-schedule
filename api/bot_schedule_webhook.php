<?php
// Telegram webhook: расписание для всех студентов и преподавателей ИМСИТ
// Функции: выбор и запоминание группы/преподавателя, кнопки Сегодня/Завтра/Неделя, рассылка (см. bot_broadcast.php)

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/ScheduleManager.php';

// --- CONFIG ---
$BOT_TOKEN = getenv('IMSIT_TELEGRAM_BOT_TOKEN') ?: '8371794642:AAEtU08o8r6qL-HB8qJGvRKik0gCvzd_b2M';

// --- LOW LEVEL TELEGRAM ---
function tg_api($method, $payload) {
    global $BOT_TOKEN;
    $url = "https://api.telegram.org/bot{$BOT_TOKEN}/{$method}";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $resp = curl_exec($ch);
    curl_close($ch);
    return $resp ? json_decode($resp, true) : null;
}
function tg_send($chatId, $text, $kb = null) {
    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
    ];
    if ($kb) $data['reply_markup'] = json_encode($kb, JSON_UNESCAPED_UNICODE);
    return tg_api('sendMessage', $data);
}
function tg_edit($chatId, $messageId, $text, $kb = null) {
    $data = [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
    ];
    if ($kb) $data['reply_markup'] = json_encode($kb, JSON_UNESCAPED_UNICODE);
    return tg_api('editMessageText', $data);
}

// --- STORAGE ---
function ensureSchema($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS bot_users (
        chat_id BIGINT PRIMARY KEY,
        username VARCHAR(255) NULL,
        first_name VARCHAR(255) NULL,
        last_name VARCHAR(255) NULL,
        view_mode ENUM('group','teacher') NULL,
        selected_group VARCHAR(255) NULL,
        selected_teacher VARCHAR(255) NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function upsertUser($pdo, $chatId, $username, $firstName, $lastName) {
    $sql = "INSERT INTO bot_users (chat_id, username, first_name, last_name) VALUES (?,?,?,?)
            ON DUPLICATE KEY UPDATE username=VALUES(username), first_name=VALUES(first_name), last_name=VALUES(last_name)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$chatId, $username, $firstName, $lastName]);
}
function setPref($pdo, $chatId, $mode, $value) {
    if ($mode === 'group') {
        $stmt = $pdo->prepare("UPDATE bot_users SET view_mode='group', selected_group=?, selected_teacher=NULL WHERE chat_id=?");
        $stmt->execute([$value, $chatId]);
    } else {
        $stmt = $pdo->prepare("UPDATE bot_users SET view_mode='teacher', selected_teacher=?, selected_group=NULL WHERE chat_id=?");
        $stmt->execute([$value, $chatId]);
    }
}
function getPref($pdo, $chatId) {
    $stmt = $pdo->prepare("SELECT view_mode, selected_group, selected_teacher FROM bot_users WHERE chat_id=?");
    $stmt->execute([$chatId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// --- HELPERS ---
function b64e($s){ return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function b64d($s){ return base64_decode(strtr($s, '-_', '+/')); }

function addPendingColumns($pdo){
    // Надежно добавляем колонки pending_* для любой версии MySQL/MariaDB
    try {
        // Проверяем наличие столбца pending_type
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'bot_users' AND COLUMN_NAME = 'pending_type'");
        $stmt->execute([DB_NAME]);
        if ((int)$stmt->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE bot_users ADD COLUMN pending_type VARCHAR(16) NULL");
        }
        // Проверяем наличие столбца pending_expires
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'bot_users' AND COLUMN_NAME = 'pending_expires'");
        $stmt->execute([DB_NAME]);
        if ((int)$stmt->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE bot_users ADD COLUMN pending_expires TIMESTAMP NULL");
        }
        foreach (['pending_to_chat_id' => 'BIGINT NULL', 'pending_to_username' => 'VARCHAR(255) NULL'] as $col => $def) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'bot_users' AND COLUMN_NAME = ?");
            $stmt->execute([DB_NAME, $col]);
            if ((int)$stmt->fetchColumn() === 0) {
                $pdo->exec("ALTER TABLE bot_users ADD COLUMN {$col} {$def}");
            }
        }
    } catch (Exception $e) {
        // Игнорируем, но не падаем
    }
}

function getGroupsPage($pdo, $page = 1, $pageSize = 10, $query = null){
    $page = max(1, (int)$page); $pageSize = max(1, (int)$pageSize); $offset = ($page-1)*$pageSize;
    if ($query){
        $like = '%'.$query.'%';
        $stmt = $pdo->prepare("SELECT DISTINCT group_name FROM schedule_all WHERE group_name LIKE ? ORDER BY group_name LIMIT {$pageSize} OFFSET {$offset}");
        $stmt->execute([$like]);
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM (SELECT DISTINCT group_name FROM schedule_all WHERE group_name LIKE ?) t");
        $countStmt->execute([$like]);
    } else {
        $sql = "SELECT DISTINCT group_name FROM schedule_all ORDER BY group_name LIMIT {$pageSize} OFFSET {$offset}";
        $stmt = $pdo->query($sql);
        $countStmt = $pdo->query("SELECT COUNT(*) FROM (SELECT DISTINCT group_name FROM schedule_all) t");
    }
    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
    $total = $countStmt ? (int)$countStmt->fetchColumn() : 0;
    $pages = max(1, (int)ceil(($total ?: 1) / $pageSize));
    return [$rows, $pages];
}

function getTeachersPage($pdo, $page = 1, $pageSize = 10, $query = null){
    $page = max(1, (int)$page); $pageSize = max(1, (int)$pageSize); $offset = ($page-1)*$pageSize;
    $baseWhere = "teacher_name IS NOT NULL AND teacher_name != ''";
    if ($query){
        $like = '%'.$query.'%';
        $stmt = $pdo->prepare("SELECT DISTINCT teacher_name FROM schedule_all WHERE {$baseWhere} AND teacher_name LIKE ? ORDER BY teacher_name LIMIT {$pageSize} OFFSET {$offset}");
        $stmt->execute([$like]);
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM (SELECT DISTINCT teacher_name FROM schedule_all WHERE {$baseWhere} AND teacher_name LIKE ?) t");
        $countStmt->execute([$like]);
    } else {
        $sql = "SELECT DISTINCT teacher_name FROM schedule_all WHERE {$baseWhere} ORDER BY teacher_name LIMIT {$pageSize} OFFSET {$offset}";
        $stmt = $pdo->query($sql);
        $countStmt = $pdo->query("SELECT COUNT(*) FROM (SELECT DISTINCT teacher_name FROM schedule_all WHERE {$baseWhere}) t");
    }
    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
    $total = $countStmt ? (int)$countStmt->fetchColumn() : 0;
    $pages = max(1, (int)ceil(($total ?: 1) / $pageSize));
    return [$rows, $pages];
}
function todayWeekDay() {
    $week = (date('W') % 2 == 0) ? 1 : 2; // как в приложении
    $day = (int)date('N');
    if ($day === 7) tg_send($chatId, "Сегодня выходной"); // пишем, что сегодня выходной
    return [$week, $day];
}
function tomorrowWeekDay() {
    $week = (date('W') % 2 == 0) ? 1 : 2;
    $day = (int)date('N');
    $day++;
    if ($day > 6) { $day = 1; $week = $week == 1 ? 2 : 1; }
    return [$week, $day];
}
function renderLessons($lessons, $isTeacherMode) {
    if (empty($lessons)) return "—";
    $out = [];
    foreach ($lessons as $l) {
        $time = substr($l['start_time'],0,5) . '–' . substr($l['end_time'],0,5);
        $title = htmlspecialchars($l['subject_name'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $room = htmlspecialchars($l['room_number'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $meta = '';
        if ($isTeacherMode) {
            if (!empty($l['groups']) && is_array($l['groups'])) {
                $meta = htmlspecialchars(implode(', ', $l['groups']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            } elseif (!empty($l['group_name'])) {
                $meta = htmlspecialchars($l['group_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
        } else {
            $meta = htmlspecialchars($l['teacher_name'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        $out[] = "<b>{$l['lesson_number']} п.</b> {$time}\n📚 {$title}\n🏢 {$room} • " . ($isTeacherMode ? '👥 ' : '👨‍🏫 ') . $meta;
    }
    return implode("\n\n", $out);
}

// --- MAIN ---
ensureSchema($pdo);
$scheduleManager = new ScheduleManager($pdo);
$input = file_get_contents('php://input');
if ($_SERVER['REQUEST_METHOD'] === 'GET' || !$input) {
    http_response_code(200);
    echo 'OK';
    return;
}
$update = json_decode($input, true);
if (!$update) { http_response_code(200); echo 'OK'; return; }

// Unified handlers
if (isset($update['message'])) {
    $m = $update['message'];
    $chatId = $m['chat']['id'];
    $telegramId = $m['from']['id'] ?? null;
    $firstName = $m['from']['first_name'] ?? '';
    $lastName = $m['from']['last_name'] ?? '';
    $username = $m['from']['username'] ?? '';
    $text = trim($m['text'] ?? '');
    
    upsertUser($pdo, $chatId, $username, $firstName, $lastName);

    if ($text === '/start' || strpos($text, '/start ') === 0) {
        // Обработка параметров шаринга
        if (strpos($text, '/start ') === 0) {
            $param = trim(substr($text, 7)); // Убираем '/start '
            
            // Проверяем, является ли это параметром шаринга
            if (preg_match('/^share_(group|teacher)_(.+)$/', $param, $matches)) {
                $shareType = $matches[1]; // 'group' или 'teacher'
                $encodedValue = $matches[2];
                
                // Декодируем значение
                $decodedValue = b64d($encodedValue);
                
                if ($decodedValue) {
                    // Формируем URL для Web App
                    $webAppUrl = "https://imsit.shop/id.php";
                    if ($shareType === 'group') {
                        $webAppUrl .= '?group=' . urlencode($decodedValue);
                        $message = "📅 <b>Расписание группы {$decodedValue}</b>\n\nНажмите кнопку ниже, чтобы открыть расписание:";
                    } else {
                        $webAppUrl .= '?teacher=' . urlencode($decodedValue);
                        $message = "👨‍🏫 <b>Расписание преподавателя {$decodedValue}</b>\n\nНажмите кнопку ниже, чтобы открыть расписание:";
                    }
                    
                    $kb = ['inline_keyboard' => [
                        [ ['text'=>'📅 Открыть расписание','web_app'=>['url'=>$webAppUrl]] ]
                    ]];
                    
                    tg_send($chatId, $message, $kb);
                    exit;
                }
            }
        }
        
        // Обычное приветствие
        $kb = ['inline_keyboard' => [
            [ ['text'=>'📅 Сегодня','callback_data'=>'today'], ['text'=>'📆 Завтра','callback_data'=>'tomorrow'], ['text'=>'📊 Неделя','callback_data'=>'week'] ],
            [ ['text'=>'⚙️ Настройки','callback_data'=>'settings'] ],
            [ ['text'=>'🌐 Открыть приложение','web_app'=>['url'=>'https://imsit.shop/id.php']] ]
        ]];
        tg_send($chatId, "👋 Привет!\nВыберите действие:", $kb);
        exit;
    }

    // свободный ввод: ожидаем текст группы/преподавателя, username или текст валентинки
    addPendingColumns($pdo);
    $stmt = $pdo->prepare("SELECT pending_type, pending_expires, pending_to_chat_id, pending_to_username FROM bot_users WHERE chat_id=?");
    $stmt->execute([$chatId]);
    $pend = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($pend && $pend['pending_type'] && (!$pend['pending_expires'] || strtotime($pend['pending_expires']) > time())) {
        $q = $text;
        if ($pend['pending_type'] === 'group') {
            list($items,$pages) = getGroupsPage($pdo, 1, 10, $q);
            if (empty($items)) {
                tg_send($chatId, "❌ Ничего не найдено по запросу: <b>".htmlspecialchars($q,ENT_QUOTES)."</b>");
            } else {
                $rows = array_map(function($g){ return ['text'=>$g, 'callback_data'=>'confirm_group:'.b64e($g)]; }, $items);
                $kb = ['inline_keyboard' => array_map(function($r){ return [$r]; }, $rows) ];
                // Добавляем навигацию и возврат
                $kb['inline_keyboard'][] = [ ['text'=>'🔙 Назад','callback_data'=>'pick_group'] ];
                tg_send($chatId, "Найдено (группы):", $kb);
            }
        } elseif ($pend['pending_type'] === 'teacher') {
            list($items,$pages) = getTeachersPage($pdo, 1, 10, $q);
            if (empty($items)) {
                tg_send($chatId, "❌ Преподаватели не найдены по запросу: <b>".htmlspecialchars($q,ENT_QUOTES)."</b>");
            } else {
                $rows = array_map(function($t){ return ['text'=>$t, 'callback_data'=>'confirm_teacher:'.b64e($t)]; }, $items);
                $kb = ['inline_keyboard' => array_map(function($r){ return [$r]; }, $rows) ];
                $kb['inline_keyboard'][] = [ ['text'=>'🔙 Назад','callback_data'=>'pick_teacher'] ];
                tg_send($chatId, "Найдено (преподаватели):", $kb);
            }
        }
        // не сбрасываем pending, пока не подтвердит
        exit;
    }

    tg_send($chatId, "Используйте кнопки меню: /start");
    exit;
}

if (isset($update['callback_query'])) {
    $cb = $update['callback_query'];
    $chatId = $cb['message']['chat']['id'];
    $msgId = $cb['message']['message_id'];
    $data = $cb['data'];
    $telegramId = $cb['from']['id'] ?? null;
    $firstName = $cb['from']['first_name'] ?? '';
    $username = $cb['from']['username'] ?? '';
    
    upsertUser($pdo, $chatId, $username, $firstName, $cb['from']['last_name'] ?? '');

    // Выбор режимов
    if ($data === 'pick_group') {
        // Показ списка (первая страница) + поиск
        list($groups,$pages) = getGroupsPage($pdo, 1, 10, null);
        $rows = array_map(function($g){ return [['text'=>$g, 'callback_data'=>'confirm_group:'.b64e($g)]]; }, $groups);
        $nav = [];
        if ($pages > 1) $nav[] = ['text'=>'▶️', 'callback_data'=>'list_group:2'];
        $rows[] = $nav ? $nav : [['text'=>'—', 'callback_data'=>'noop']];
        $rows[] = [ ['text'=>'🔎 Поиск', 'callback_data'=>'search_group'], ['text'=>'🔙 Назад', 'callback_data'=>'settings'] ];
        tg_edit($chatId, $msgId, "👥 <b>Выберите группу</b>", ['inline_keyboard'=>$rows]);
        $pdo->prepare("UPDATE bot_users SET pending_type=NULL, pending_expires=NULL WHERE chat_id=?")->execute([$chatId]);
        exit;
    }
    if ($data === 'pick_teacher') {
        list($teachers,$pages) = getTeachersPage($pdo, 1, 10, null);
        $rows = array_map(function($t){ return [['text'=>$t, 'callback_data'=>'confirm_teacher:'.b64e($t)]]; }, $teachers);
        $nav = [];
        if ($pages > 1) $nav[] = ['text'=>'▶️', 'callback_data'=>'list_teacher:2'];
        $rows[] = $nav ? $nav : [['text'=>'—', 'callback_data'=>'noop']];
        $rows[] = [ ['text'=>'🔎 Поиск', 'callback_data'=>'search_teacher'], ['text'=>'🔙 Назад', 'callback_data'=>'settings'] ];
        tg_edit($chatId, $msgId, "👨‍🏫 <b>Выберите преподавателя</b>", ['inline_keyboard'=>$rows]);
        $pdo->prepare("UPDATE bot_users SET pending_type=NULL, pending_expires=NULL WHERE chat_id=?")->execute([$chatId]);
        exit;
    }
    if (strpos($data, 'list_group:') === 0) {
        $page = (int)substr($data, strlen('list_group:'));
        list($groups,$pages) = getGroupsPage($pdo, max(1,$page), 10, null);
        $rows = array_map(function($g){ return [['text'=>$g, 'callback_data'=>'confirm_group:'.b64e($g)]]; }, $groups);
        $nav = [];
        if ($page>1) $nav[] = ['text'=>'◀️','callback_data'=>'list_group:'.($page-1)];
        if ($page<$pages) $nav[] = ['text'=>'▶️','callback_data'=>'list_group:'.($page+1)];
        if ($nav) $rows[] = $nav;
        $rows[] = [ ['text'=>'🔎 Поиск', 'callback_data'=>'search_group'], ['text'=>'🔙 Назад', 'callback_data'=>'settings'] ];
        tg_edit($chatId, $msgId, "👥 <b>Выберите группу</b>", ['inline_keyboard'=>$rows]);
        exit;
    }
    if (strpos($data, 'list_teacher:') === 0) {
        $page = (int)substr($data, strlen('list_teacher:'));
        list($teachers,$pages) = getTeachersPage($pdo, max(1,$page), 10, null);
        $rows = array_map(function($t){ return [['text'=>$t, 'callback_data'=>'confirm_teacher:'.b64e($t)]]; }, $teachers);
        $nav = [];
        if ($page>1) $nav[] = ['text'=>'◀️','callback_data'=>'list_teacher:'.($page-1)];
        if ($page<$pages) $nav[] = ['text'=>'▶️','callback_data'=>'list_teacher:'.($page+1)];
        if ($nav) $rows[] = $nav;
        $rows[] = [ ['text'=>'🔎 Поиск', 'callback_data'=>'search_teacher'], ['text'=>'🔙 Назад', 'callback_data'=>'settings'] ];
        tg_edit($chatId, $msgId, "👨‍🏫 <b>Выберите преподавателя</b>", ['inline_keyboard'=>$rows]);
        exit;
    }
    if ($data === 'search_group') {
        addPendingColumns($pdo);
        $pdo->prepare("UPDATE bot_users SET pending_type='group', pending_expires=DATE_ADD(NOW(), INTERVAL 5 MINUTE) WHERE chat_id=?")->execute([$chatId]);
        tg_edit($chatId, $msgId, "🔎 Введите часть названия группы сообщением. Затем выберите из найденного списка.", ['inline_keyboard'=>[[['text'=>'🔙 Назад','callback_data'=>'pick_group']]]]);
        exit;
    }
    if ($data === 'search_teacher') {
        addPendingColumns($pdo);
        $pdo->prepare("UPDATE bot_users SET pending_type='teacher', pending_expires=DATE_ADD(NOW(), INTERVAL 5 MINUTE) WHERE chat_id=?")->execute([$chatId]);
        tg_edit($chatId, $msgId, "🔎 Введите часть ФИО преподавателя сообщением.", ['inline_keyboard'=>[[['text'=>'🔙 Назад','callback_data'=>'pick_teacher']]]]);
        exit;
    }
    if (strpos($data, 'confirm_group:') === 0) {
        $g = b64d(substr($data, strlen('confirm_group:')));
        $kb = ['inline_keyboard'=>[
            [ ['text'=>'✅ Подтвердить','callback_data'=>'set_group:'.b64e($g)] ],
            [ ['text'=>'🔙 Назад','callback_data'=>'pick_group'] ]
        ]];
        tg_edit($chatId, $msgId, "Выбрать группу: <b>".htmlspecialchars($g,ENT_QUOTES)."</b>?", $kb); exit;
    }
    if (strpos($data, 'confirm_teacher:') === 0) {
        $t = b64d(substr($data, strlen('confirm_teacher:')));
        $kb = ['inline_keyboard'=>[
            [ ['text'=>'✅ Подтвердить','callback_data'=>'set_teacher:'.b64e($t)] ],
            [ ['text'=>'🔙 Назад','callback_data'=>'pick_teacher'] ]
        ]];
        tg_edit($chatId, $msgId, "Выбрать преподавателя: <b>".htmlspecialchars($t,ENT_QUOTES)."</b>?", $kb); exit;
    }
    if ($data === 'settings') {
        $pref = getPref($pdo, $chatId) ?: ['view_mode'=>null,'selected_group'=>null,'selected_teacher'=>null];
        $text = "⚙️ <b>Настройки</b>\n\nРежим: <b>" . ($pref['view_mode'] ?: 'не выбран') . "</b>\nГруппа: <b>" . ($pref['selected_group'] ?: '—') . "</b>\nПреподаватель: <b>" . ($pref['selected_teacher'] ?: '—') . "</b>";
        $kb = ['inline_keyboard'=>[
            [ ['text'=>'👥 Выбрать группу','callback_data'=>'pick_group'], ['text'=>'👨‍🏫 Выбрать препода','callback_data'=>'pick_teacher'] ],
            [ ['text'=>'🗑 Сбросить выбор','callback_data'=>'reset_pref'] ],
            [ ['text'=>'🔙 В меню','callback_data'=>'menu'] ]
        ]];
        tg_edit($chatId, $msgId, $text, $kb); exit;
    }
    if ($data === 'reset_pref') {
        $stmt = $pdo->prepare("UPDATE bot_users SET view_mode=NULL, selected_group=NULL, selected_teacher=NULL WHERE chat_id=?");
        $stmt->execute([$chatId]);
        tg_edit($chatId, $msgId, "✅ Выбор сброшен.", ['inline_keyboard'=>[[['text'=>'🔙 Назад','callback_data'=>'settings']]]] );
        exit;
    }
    if (strpos($data, 'switch_week:') === 0) {
        $currentWeek = (int)substr($data, strlen('switch_week:'));
        $newWeek = ($currentWeek === 1) ? 2 : 1;
        $pref = getPref($pdo, $chatId);
        if (!$pref || (!$pref['selected_group'] && !$pref['selected_teacher'])) {
            tg_edit($chatId, $msgId, "Сначала выберите группу или преподавателя в ⚙️ Настройках.", ['inline_keyboard'=>[[['text'=>'⚙️ Настройки','callback_data'=>'settings']]]] );
            exit;
        }
        $isTeacher = ($pref['view_mode'] === 'teacher' && $pref['selected_teacher']);
        if ($isTeacher) {
            $txt = "📊 <b>Неделя {$newWeek}</b> — " . htmlspecialchars($pref['selected_teacher'], ENT_QUOTES) . "\n\n";
            for ($d=1; $d<=6; $d++) {
                $less = $scheduleManager->getTeacherSchedule($pref['selected_teacher'], $newWeek, $d);
                if (!empty($less)) {
                    $txt .= "<b>День {$d}</b>\n" . renderLessons($less, true) . "\n\n";
                }
            }
            $weekKb = ['inline_keyboard' => [
                [ ['text'=>'🔄 Сменить неделю','callback_data'=>'switch_week:'.$newWeek] ],
                [ ['text'=>'⬅️ В меню','callback_data'=>'menu'] ]
            ]];
            tg_edit($chatId, $msgId, rtrim($txt), $weekKb);
        } else {
            $txt = "📊 <b>Неделя {$newWeek}</b> — " . htmlspecialchars($pref['selected_group'], ENT_QUOTES) . "\n\n";
            for ($d=1;$d<=6;$d++) {
                $less = $scheduleManager->getSchedule($pref['selected_group'], $newWeek, $d);
                if (!empty($less)) {
                    $txt .= "<b>День {$d}</b>\n" . renderLessons($less, false) . "\n\n";
                }
            }
            $weekKb = ['inline_keyboard' => [
                [ ['text'=>'🔄 Сменить неделю','callback_data'=>'switch_week:'.$newWeek] ],
                [ ['text'=>'⬅️ В меню','callback_data'=>'menu'] ]
            ]];
            tg_edit($chatId, $msgId, rtrim($txt), $weekKb);
        }
        exit;
    }
    if (in_array($data, ['today','tomorrow','week','menu'], true)) {
        if ($data === 'menu') {
            $pdo->prepare("UPDATE bot_users SET pending_type=NULL, pending_expires=NULL, pending_to_chat_id=NULL, pending_to_username=NULL WHERE chat_id=?")->execute([$chatId]);
            $kb = ['inline_keyboard' => [
                [ ['text'=>'📅 Сегодня','callback_data'=>'today'], ['text'=>'📆 Завтра','callback_data'=>'tomorrow'], ['text'=>'📊 Неделя','callback_data'=>'week'] ],
                [ ['text'=>'⚙️ Настройки','callback_data'=>'settings'] ],
                [ ['text'=>'🌐 Открыть приложение','web_app'=>['url'=>'https://imsit.shop/id.php']] ]
            ]];
            tg_edit($chatId, $msgId, "👋 Привет!\nВыберите действие:", $kb); exit;
        }

        $pref = getPref($pdo, $chatId);
        if (!$pref || (!$pref['selected_group'] && !$pref['selected_teacher'])) {
            tg_edit($chatId, $msgId, "Сначала выберите группу или преподавателя в ⚙️ Настройках.", ['inline_keyboard'=>[[['text'=>'⚙️ Настройки','callback_data'=>'settings']]]] );
            exit;
        }

        if ($data === 'today') { list($week,$day) = todayWeekDay(); }
        elseif ($data === 'tomorrow') { list($week,$day) = tomorrowWeekDay(); }
        else { $week = null; $day = null; }

        $isTeacher = ($pref['view_mode'] === 'teacher' && $pref['selected_teacher']);
        $backKb = ['inline_keyboard' => [[ ['text'=>'⬅️ В меню','callback_data'=>'menu'] ]]];
        
        if ($isTeacher) {
            if ($data === 'week') {
                $w = (date('W')%2==0?1:2);
                $txt = "📊 <b>Неделя {$w}</b> — " . htmlspecialchars($pref['selected_teacher'], ENT_QUOTES) . "\n\n";
                for ($d=1; $d<=6; $d++) {
                    $less = $scheduleManager->getTeacherSchedule($pref['selected_teacher'], $w, $d);
                    if (!empty($less)) {
                        $txt .= "<b>День {$d}</b>\n" . renderLessons($less, true) . "\n\n";
                    }
                }
                $weekKb = ['inline_keyboard' => [
                    [ ['text'=>'🔄 Сменить неделю','callback_data'=>'switch_week:'.$w] ],
                    [ ['text'=>'⬅️ В меню','callback_data'=>'menu'] ]
                ]];
                tg_edit($chatId, $msgId, rtrim($txt), $weekKb);
            } else {
                $less = $scheduleManager->getTeacherSchedule($pref['selected_teacher'], $week, $day);
                $title = $data==='today' ? 'Сегодня>' : 'Завтра';
                tg_edit($chatId, $msgId, "📅 <b>{$title}</b> — " . htmlspecialchars($pref['selected_teacher'], ENT_QUOTES) . "\n\n" . renderLessons($less, true), $backKb);
            }
        } else { // group mode
            if ($data === 'week') {
                $w = (date('W')%2==0?1:2);
                $txt = "📊 <b>Неделя {$w}</b> — " . htmlspecialchars($pref['selected_group'], ENT_QUOTES) . "\n\n";
                for ($d=1;$d<=6;$d++) {
                    $less = $scheduleManager->getSchedule($pref['selected_group'], $w, $d);
                    if (!empty($less)) {
                        $txt .= "<b>День {$d}</b>\n" . renderLessons($less, false) . "\n\n";
                    }
                }
                $weekKb = ['inline_keyboard' => [
                    [ ['text'=>'🔄 Сменить неделю','callback_data'=>'switch_week:'.$w] ],
                    [ ['text'=>'⬅️ В меню','callback_data'=>'menu'] ]
                ]];
                tg_edit($chatId, $msgId, rtrim($txt), $weekKb);
            } else {
                $less = $scheduleManager->getSchedule($pref['selected_group'], $week, $day);
                $title = $data==='today' ? 'на сегодня' : 'на завтра';
                tg_edit($chatId, $msgId, "📅 <b>{$title}</b> — " . htmlspecialchars($pref['selected_group'], ENT_QUOTES) . "\n\n" . renderLessons($less, false), $backKb);
            }
        }
        exit;
    }

    // Если прилетел callback вида set_group:NAME или set_teacher:NAME
    if (strpos($data, 'set_group:') === 0) {
        $g = b64d(substr($data, strlen('set_group:')));
        setPref($pdo, $chatId, 'group', $g);
        tg_edit($chatId, $msgId, "✅ Группа сохранена: <b>".htmlspecialchars($g,ENT_QUOTES)."</b>", ['inline_keyboard'=>[[['text'=>'⬅️ В меню','callback_data'=>'menu']]]]);
        exit;
    }
    if (strpos($data, 'set_teacher:') === 0) {
        $t = b64d(substr($data, strlen('set_teacher:')));
        setPref($pdo, $chatId, 'teacher', $t);
        tg_edit($chatId, $msgId, "✅ Преподаватель сохранен: <b>".htmlspecialchars($t,ENT_QUOTES)."</b>", ['inline_keyboard'=>[[['text'=>'⬅️ В меню','callback_data'=>'menu']]]]);
        exit;
    }

    // fallback
    tg_edit($chatId, $msgId, 'Неизвестная команда. Нажмите /start');
    exit;
}

http_response_code(200);
echo 'OK';
?>


