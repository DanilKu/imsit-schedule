<?php
/*
                ██╗███╗   ███╗███████╗██╗████████╗    ██╗██████╗ 
                ██║████╗ ████║██╔════╝██║╚══██╔══╝    ██║██╔══██╗
                ██║██╔████╔██║███████╗██║   ██║       ██║██║  ██║
                ██║██║╚██╔╝██║╚════██║██║   ██║       ██║██║  ██║
                ██║██║ ╚═╝ ██║███████║██║   ██║       ██║██████╔╝
                ╚═╝╚═╝     ╚═╝╚══════╝╚═╝   ╚═╝       ╚═╝╚═════╝ 
     
    ╔══════════════════════════════════════════════════════════════════════════════╗
    ║                               Version 3.2 Beta                               ║
    ║                                                                              ║
    ║     Расписание для всех групп и преподавателей                               ║
    ║     Зимнее оформление                                                        ║
    ╚══════════════════════════════════════════════════════════════════════════════╝
*/

// Принудительно отключаем кеширование
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';
require_once 'includes/ScheduleManager.php';

date_default_timezone_set('Europe/Moscow');

// инфа о пользователе
$currentUser = null;

// Проверяем авторизацию
if (isset($_SESSION['user_id'])) {
    try {
        require_once 'config/auth.php';
        $currentUser = getCurrentUser();
    } catch (Exception $e) {
        $currentUser = null;
    }
}


$scheduleManager = new ScheduleManager($pdo);

$weekStartDate = (new DateTimeImmutable('monday this week', new DateTimeZone('Europe/Moscow')))->format('Y-m-d');

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS group_activity (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_name VARCHAR(255) NOT NULL,
        week_start DATE NOT NULL,
        views INT NOT NULL DEFAULT 0,
        UNIQUE KEY uniq_group_week (group_name, week_start)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {
    // игнорируем создание таблицы, если нет доступа
}

try {
    // обновляем тайм на сервере
    $scheduleManager->updateSettingsWithCurrentTime();

    // get settings расписания
    $settings = $scheduleManager->getSettings();

    // get data из url или settings по умолчанию
    $currentWeek = isset($_GET['week']) ? (int)$_GET['week'] : $settings['current_week'];
    $currentDay = isset($_GET['day']) ? (int)$_GET['day'] : $settings['current_day'];

    // определение группы или преподавателя
    $userGroup = null;
    $selectedTeacher = null;
    $viewMode = 'group'; // 'group' или 'teacher'
    
    // Загружаем все группы и преподавателей из schedule_all
    $availableGroups = [];
    $availableTeachers = [];
    try {
        $stmt = $pdo->query("SELECT DISTINCT group_name FROM schedule_all ORDER BY group_name");
        $availableGroups = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $stmt = $pdo->query("SELECT DISTINCT teacher_name FROM schedule_all WHERE teacher_name IS NOT NULL AND teacher_name != '' ORDER BY teacher_name");
        $availableTeachers = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        // игнорируем, список останется пустым
    }
    
    // 1. проверка get параметра группы
    if (isset($_GET['group']) && in_array($_GET['group'], $availableGroups)) {
        $userGroup = $_GET['group'];
        $viewMode = 'group';
        setcookie('selected_group', $userGroup, time() + (30 * 24 * 60 * 60), '/');
        setcookie('view_mode', 'group', time() + (30 * 24 * 60 * 60), '/');
        setcookie('selected_teacher', '', time() - 3600, '/'); // удаляем выбор преподавателя
        $_SESSION['selected_group'] = $userGroup;
    }
    // 2. проверка get параметра преподавателя
    elseif (isset($_GET['teacher']) && in_array($_GET['teacher'], $availableTeachers)) {
        $selectedTeacher = $_GET['teacher'];
        $viewMode = 'teacher';
        setcookie('selected_teacher', $selectedTeacher, time() + (30 * 24 * 60 * 60), '/');
        setcookie('view_mode', 'teacher', time() + (30 * 24 * 60 * 60), '/');
        setcookie('selected_group', '', time() - 3600, '/'); // удаляем выбор группы
        $_SESSION['selected_teacher'] = $selectedTeacher;
    }
    // 3. проверка куки группы
    elseif (isset($_COOKIE['selected_group']) && in_array($_COOKIE['selected_group'], $availableGroups)) {
        $userGroup = $_COOKIE['selected_group'];
        $viewMode = 'group';
        $_SESSION['selected_group'] = $userGroup;
    }
    // 4. проверка куки преподавателя
    elseif (isset($_COOKIE['selected_teacher']) && in_array($_COOKIE['selected_teacher'], $availableTeachers)) {
        $selectedTeacher = $_COOKIE['selected_teacher'];
        $viewMode = 'teacher';
        $_SESSION['selected_teacher'] = $selectedTeacher;
    }
    // 5. проверка группы авторизованного пользователя
    elseif ($currentUser && isset($currentUser['group']) && in_array($currentUser['group'], $availableGroups)) {
        $userGroup = $currentUser['group'];
        $viewMode = 'group';
        setcookie('selected_group', $userGroup, time() + (30 * 24 * 60 * 60), '/');
        $_SESSION['selected_group'] = $userGroup;
    }
    // 6. если ничего не выбрано - показываем модалку выбора
    else {
        $userGroup = null;
        $selectedTeacher = null;
    }

    $viewTrackerCookieName = 'group_view_tracker';
    $viewTracker = [];
    if (isset($_COOKIE[$viewTrackerCookieName])) {
        $decodedTracker = json_decode($_COOKIE[$viewTrackerCookieName], true);
        if (is_array($decodedTracker)) {
            $viewTracker = $decodedTracker;
        }
    }

    if ($viewMode === 'group' && $userGroup) {
        $trackerKey = strtolower($userGroup);
        $nowTs = time();
        $lastViewTs = isset($viewTracker[$trackerKey]) ? (int)$viewTracker[$trackerKey] : 0;
        $cooldownSeconds = 5 * 60;
        if (($nowTs - $lastViewTs) >= $cooldownSeconds) {
            try {
                $stmt = $pdo->prepare("INSERT INTO group_activity (group_name, week_start, views) VALUES (:group_name, :week_start, 1)
                    ON DUPLICATE KEY UPDATE views = views + 1");
                $stmt->execute([
                    'group_name' => $userGroup,
                    'week_start' => $weekStartDate,
                ]);
                $viewTracker[$trackerKey] = $nowTs;
                setcookie($viewTrackerCookieName, json_encode($viewTracker, JSON_UNESCAPED_UNICODE), time() + 60 * 60 * 24 * 365, '/');
            } catch (Exception $e) {
                // игнорируем
            }
        }
    }

    // валидация settings
    if ($currentWeek < 1 || $currentWeek > 2) $currentWeek = $settings['current_week'];
    if ($currentDay < 1 || $currentDay > 6) $currentDay = $settings['current_day'];

    // получение расписание на неделю
    $weekSchedule = [];
    $currentLesson = null;
    $nextLesson = null;
    $teacherInfo = null;
    
    if ($viewMode === 'group' && $userGroup) {
        for ($day = 1; $day <= 6; $day++) {
            $weekSchedule[$day] = $scheduleManager->getSchedule($userGroup, $currentWeek, $day);
        }
        $currentLesson = $scheduleManager->getCurrentLesson($userGroup);
        $nextLesson = $scheduleManager->getNextLesson($userGroup);
    } elseif ($viewMode === 'teacher' && $selectedTeacher) {
        // Используем имя преподавателя напрямую из schedule_all
        $teacherInfo = ['full_name' => $selectedTeacher, 'short_name' => $selectedTeacher];
        for ($day = 1; $day <= 6; $day++) {
            $weekSchedule[$day] = $scheduleManager->getTeacherSchedule($selectedTeacher, $currentWeek, $day);
        }
        $currentLesson = $scheduleManager->getTeacherCurrentLesson($selectedTeacher);
        $nextLesson = $scheduleManager->getTeacherNextLesson($selectedTeacher);
    }
} catch (Exception $e) {
    die('Ошибка получения данных: ' . $e->getMessage());
}

// сокращенные названия дней
$dayNames = $scheduleManager->getDayNames();
$dayShortNames = [ 1 => 'Пн', 2 => 'Вт', 3 => 'Ср', 4 => 'Чт', 5 => 'Пт', 6 => 'Сб', 7 => 'Вс' ];

// расписание текущего дня
$daySchedule = $weekSchedule[$currentDay] ?? [];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <!-- Yandex.Metrika counter -->
    <script type="text/javascript">
        (function(m,e,t,r,i,k,a){
            m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
            m[i].l=1*new Date();
            for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
            k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)
        })(window, document,'script','https://mc.yandex.ru/metrika/tag.js?id=104384579', 'ym');

        ym(104384579, 'init', {ssr:true, webvisor:true, clickmap:true, ecommerce:"dataLayer", accurateTrackBounce:true, trackLinks:true});
    </script>
    <noscript><div><img src="https://mc.yandex.ru/watch/104384579" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
    <!-- /Yandex.Metrika counter -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="assets/icons/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="assets/icons/favicon-32x32.png" sizes="32x32" type="image/png">
    <link rel="icon" href="assets/icons/favicon-16x16.png" sizes="16x16" type="image/png">
    <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
    <meta name="theme-color" content="#1f1147">
    <title>ImsitID - Расписание (Winter Edition)</title>
    <meta name="description" content="Расписание для всех студентов и преподавателей Академии ИМСИТ. ">
    <meta name="keywords" content="Расписание, Академия ИМСИТ, Расписание для всех студентов и преподавателей Академии ИМСИТ, imsitshop, imsitid, imsit.shop, imsit.shop/shedule2, imsit.shop/shedule2.php, imsitid.ru, imsitid.com, imsitid.net, imsitid.org, imsitid.ru/schedule, imsitid.com/schedule, imsitid.net/schedule, imsitid.org/schedule, imsit, id imsit, imsitid">
    <meta name="author" content="ImsitID">
    <meta name="robots" content="index, follow">
    <meta name="googlebot" content="index, follow">
    <meta name="bingbot" content="index, follow">
    <meta name="google" content="notranslate">
    <meta name="google" content="notranslate">
    <link rel="canonical" href="https://imsit.shop/">
    <link rel="preload" as="style" href="assets/css/schedule_style.css?v=<?php echo file_exists('cache_version.txt') ? file_get_contents('cache_version.txt') : time(); ?>"/>
    <link rel="stylesheet" href="assets/css/schedule_style.css?v=<?php echo file_exists('cache_version.txt') ? file_get_contents('cache_version.txt') : time(); ?>"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Зимнее оформление */
        .snowflake {
            position: fixed;
            top: -10px;
            z-index: 9999;
            color: #fff;
            font-size: 1em;
            opacity: 0.8;
            pointer-events: none;
            animation: snowfall linear infinite;
        }

        @keyframes snowfall {
            0% {
                transform: translateY(0) rotate(0deg);
            }
            100% {
                transform: translateY(100vh) rotate(360deg);
            }
        }

        .garland {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 30px;
            z-index: 10;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 20"><path d="M0 0 Q 25 20 50 0 T 100 0" fill="none" stroke="rgba(255,255,255,0.3)" stroke-width="2"/></svg>') repeat-x;
            background-size: 100px 20px;
            pointer-events: none;
        }

        .garland::after {
            content: '';
            position: absolute;
            top: 10px;
            left: 0;
            width: 100%;
            height: 10px;
            background-image: 
                radial-gradient(circle at 12px 0, #ff0000 3px, transparent 4px),
                radial-gradient(circle at 37px 5px, #00ff00 3px, transparent 4px),
                radial-gradient(circle at 62px 0, #0000ff 3px, transparent 4px),
                radial-gradient(circle at 87px 5px, #ffff00 3px, transparent 4px);
            background-size: 100px 20px;
            background-repeat: repeat-x;
            animation: blink 1s infinite alternate;
        }

        @keyframes blink {
            0% { opacity: 0.5; filter: brightness(1); }
            100% { opacity: 1; filter: brightness(1.5); }
        }
        
        /* Дополнительные зимние стили */
        body {
            background: radial-gradient(circle at 50% 0%, #1f2937 0%, #0f172a 100%) !important;
            background-attachment: fixed !important;
            color: #e2e8f0 !important;
        }
        
        .card {
            backdrop-filter: blur(10px);
            background: rgba(30, 41, 59, 0.7) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        .btn {
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
            background: rgba(255, 255, 255, 0.1) !important;
            color: #fff !important;
        }

        .btn:hover {
            background: rgba(255, 255, 255, 0.2) !important;
        }

        /* Скрываем старый фон */
        .page-bg {
            display: none !important;
        }
    </style>
</head>
<body>
    <div class="garland"></div>
    <div class="page-bg" aria-hidden="true">
        <div class="blob blob-a"></div>
        <div class="blob blob-b"></div>
        <div class="overlay"></div>
    </div>

    <header class="header px">
        <div class="container header__row">
            <div class="search-container">
                <div class="search-input-wrapper">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="globalSearch" placeholder="Найти..." autocomplete="off">
                    <button id="clearSearch" class="clear-search-btn" style="display: none;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="searchResults" class="search-results" style="display: none;">
                    <!-- Результаты поиска будут добавляться через JavaScript -->
                </div>
                <div id="searchHistory" class="search-history" style="display: none;">
                    <!-- История поиска будет добавляться через JavaScript -->
                </div>
            </div>
            <div class="header__row">
                <?php if ($currentUser): ?>
                    <a href="client_dashboard.php" class="btn">Профиль</a>
                <?php else: ?>
                    <a href="https://t.me/cowgivesmilk" target="_blank" class="btn"><i class="fas fa-question-circle"></i></a>
                <?php endif; ?>
                <button id="refreshBtn" class="btn"><i class="fas fa-sync-alt"></i></button>
            </div>
        </div>
    </header>

    <main class="px" style="padding-bottom: 6rem;">
        <section class="container space-y-6">
            <!-- imsitMaps promo -->
            <div class="imsitmaps-promo">
                <div class="row">
                    <div class="note"><i class="fa-solid fa-map-location-dot" style="margin-right:6px;color:#60a5fa"></i>Интерактивная карта корпусов</div>
                    <a class="btn" href="imsitmaps.html"><i class="fa-solid fa-location-dot" style="margin-right:6px; text-decoration: none"></i>imsitMaps</a>
                </div>
            </div>
            <div class="card">
                <div class="card__inner">
                    <div class="header__row">
                        <div class="header__row" style="gap:0.75rem;">
                            <div class="h1">
                                <?php if ($viewMode === 'teacher' && $teacherInfo): ?>
                                    <?php echo htmlspecialchars($teacherInfo['full_name']); ?>
                                <?php elseif ($viewMode === 'group' && $userGroup): ?>
                                    <?php echo htmlspecialchars($userGroup); ?>
                                <?php else: ?>
                                    Выберите группу или преподавателя
                                <?php endif; ?>
                            </div>
                            
                        </div>
                    </div>

                    <?php if ($userGroup || $selectedTeacher): ?>
                    <div class="mt-5 grid grid--two" data-cards>
                        <?php if ($currentLesson): ?>
                        <div id="nowCard" class="card card__inner">
                            <div class="header__row">
                                <span class="btn btn--emerald"><i class="fas fa-circle" style="margin-right: 4px;"></i>Сейчас</span>
                                <span id="nowTimeRange" class="small"><?php echo substr($currentLesson['start_time'], 0, 5); ?>–<?php echo substr($currentLesson['end_time'], 0, 5); ?></span>
                            </div>
                            <div class="mt-4">
                                <div id="nowTitle" class="h2 truncate"><?php echo htmlspecialchars($currentLesson['subject_name']); ?></div>
                                <div id="nowMeta" class="lesson-meta">
                                    <?php echo htmlspecialchars($currentLesson['room_number']); ?> • 
                                    <?php if ($viewMode === 'teacher' && isset($currentLesson['groups']) && is_array($currentLesson['groups']) && count($currentLesson['groups']) > 0): ?>
                                        <?php echo htmlspecialchars(implode(', ', $currentLesson['groups'])); ?>
                                    <?php elseif ($viewMode === 'teacher' && isset($currentLesson['group_name']) && !empty(trim($currentLesson['group_name']))): ?>
                                        <?php echo htmlspecialchars($currentLesson['group_name']); ?>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($currentLesson['teacher_name']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="progress"><div id="nowProgress" class="progress__bar" style="width: <?php echo round($scheduleManager->getLessonProgress($currentLesson)); ?>%;"></div></div>
                                <?php
                                $remainingLabel = '—';
                                if (isset($currentLesson['end_time'])) {
                                    $endStr = $currentLesson['end_time'];
                                    $endTs = strtotime($endStr);
                                    $nowTs = time();
                                    if ($endTs !== false) {
                                        $diff = $endTs - $nowTs;
                                        if ($diff <= 60) {
                                            $remainingLabel = 'меньше минуты';
                                        } else {
                                            $mins = (int)ceil($diff / 60);
                                            $rounded = (int)(ceil($mins / 5) * 5);
                                            $remainingLabel = '~' . $rounded . 'м';
                                        }
                                    }
                                }
                                ?>
                                <div class="progress__meta"><span id="nowProgressLabel"><i class="fas fa-clock" style="margin-right: 4px;"></i>до конца пары: <?php echo $remainingLabel; ?></span><span id="nowRemaining"></span></div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div id="nextCard" class="card card__inner">
                            <div class="header__row">
                                <span class="btn btn--sky"><i class="fas fa-arrow-right" style="margin-right: 4px;"></i>Следующая</span>
                                <span id="nextTimeRange" class="small">
                                    <?php if ($nextLesson): ?>
                                        <?php echo substr($nextLesson['start_time'], 0, 5); ?>–<?php echo substr($nextLesson['end_time'], 0, 5); ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="mt-4">
                                <div id="nextTitle" class="h2 truncate">
                                    <?php if ($nextLesson): ?>
                                        <?php echo htmlspecialchars($nextLesson['subject_name']); ?>
                                    <?php else: ?>
                                        Следующих пар нет
                                    <?php endif; ?>
                                </div>
                                <div id="nextMeta" class="lesson-meta">
                                    <?php if ($nextLesson): ?>
                                        <?php echo htmlspecialchars($nextLesson['room_number']); ?> • 
                                        <?php if ($viewMode === 'teacher' && isset($nextLesson['groups']) && is_array($nextLesson['groups']) && count($nextLesson['groups']) > 0): ?>
                                            <?php echo htmlspecialchars(implode(', ', $nextLesson['groups'])); ?>
                                        <?php elseif ($viewMode === 'teacher' && isset($nextLesson['group_name']) && !empty(trim($nextLesson['group_name']))): ?>
                                            <?php echo htmlspecialchars($nextLesson['group_name']); ?>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($nextLesson['teacher_name']); ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        Расписание на сегодня завершено
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="mt-5" style="text-align:center; display:flex; flex-direction:column; gap:1rem; align-items:center;">
                        <button onclick="showGroupSelectionModal()" class="btn" style="padding:0.75rem 1.5rem; font-size:1rem;"><i class="fas fa-users" style="margin-right: 8px;"></i>Выбрать группу</button>
                        <button onclick="showTeacherSelectionModal()" class="btn" style="padding:0.75rem 1.5rem; font-size:1rem; background: linear-gradient(135deg, #3b82f6, #1e40af);"><i class="fas fa-chalkboard-teacher" style="margin-right: 8px;"></i>Выбрать преподавателя</button>
                    </div>
                    <?php endif; ?>

                    <?php if ($userGroup || $selectedTeacher): ?>
                    <div class="mt-6" style="display:flex; flex-direction:column; gap:1rem; justify-content:center; align-items:center;">
                        <div class="segmented">
                            <button data-week="1" class="seg-week<?php echo $currentWeek == 1 ? ' active' : ''; ?>">1 неделя</button>
                            <button data-week="2" class="seg-week<?php echo $currentWeek == 2 ? ' active' : ''; ?>">2 неделя</button>
                        </div>
                        <div class="days">
                            <div class="days__row" id="daysRow"></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($userGroup || $selectedTeacher): ?>
            <section class="space-y-3" aria-labelledby="dayTitle">
                <div class="header__row">
                    <h2 id="dayTitle" class="h2"><?php echo $dayNames[$currentDay]; ?></h2>
                </div>

                <div id="list" class="grid">
                    <!-- DEBUG: daySchedule count=<?php echo count($daySchedule); ?>, currentDay=<?php echo $currentDay; ?> -->
                    <?php if (!empty($daySchedule)): ?>
                        <?php foreach ($daySchedule as $index => $lesson): ?>
                            <article class="card card--hover card__inner lesson-card" style=
                                "position: relative;
                                background: linear-gradient(135deg, rgba(255,255,255,0.06) 0%, rgba(255,255,255,0.04) 100%);
                                border: 1px solid rgba(255,255,255,0.12);
                                border-radius: 12px;
                                backdrop-filter: blur(10px);
                                -webkit-backdrop-filter: blur(10px);
                                box-shadow: 0 2px 12px rgba(0,0,0,0.1);
                                transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
                                overflow: hidden;
                                padding: 0.75rem !important;"
                                onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 20px rgba(0,0,0,0.15)'; this.style.borderColor='rgba(255,255,255,0.2)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 12px rgba(0,0,0,0.1)'; this.style.borderColor='rgba(255,255,255,0.12)'">
                                
                                <div style="min-width:0; padding-left: 8px; position: relative; z-index: 1;">
                                    <div class="small muted" style=
                                        "color: rgba(255,255,255,0.75);
                                        font-weight: 500;
                                        letter-spacing: 0.3px;
                                        text-transform: uppercase;
                                        font-size: 0.7rem;
                                        margin-bottom: 0.3rem;">
                                        <?php echo $lesson['lesson_number']; ?> пара • <?php echo substr($lesson['start_time'], 0, 5); ?>–<?php echo substr($lesson['end_time'], 0, 5); ?>
                                    </div>
                                    <h3 class="h2" style=
                                        "margin-top:0.3rem; 
                                        margin-bottom: 0.5rem;
                                        line-height:1.2; 
                                        display:-webkit-box; 
                                        -webkit-line-clamp:2; 
                                        -webkit-box-orient:vertical; 
                                        overflow:hidden;
                                        color: #fff;
                                        text-shadow: 0 1px 2px rgba(0,0,0,0.25);
                                        font-weight: 600;
                                        font-size: 1rem;">
                                        <?php echo htmlspecialchars($lesson['subject_name']); ?>
                                    </h3>
                                    <div class="lesson-meta" style=
                                        "margin-top: 0.5rem;
                                        display: flex;
                                        align-items: center;
                                        gap: 0.5rem;
                                        flex-wrap: wrap;">
                                        <span style=
                                            "background: rgba(255,255,255,0.15);
                                            padding: 0.2rem 0.5rem;
                                            border-radius: 12px;
                                            font-size: 0.7rem;
                                            font-weight: 500;
                                            color: #fff;
                                            border: 1px solid rgba(255,255,255,0.2);">
                                            <?php echo htmlspecialchars($lesson['room_number']); ?>
                                        </span>
                                        <span style=
                                            "background: rgba(255,255,255,0.1);
                                            padding: 0.2rem 0.5rem;
                                            border-radius: 12px;
                                            font-size: 0.7rem;
                                            font-weight: 500;
                                            color: rgba(255,255,255,0.9);
                                            border: 1px solid rgba(255,255,255,0.15);">
                                            <?php if ($viewMode === 'teacher' && isset($lesson['groups']) && is_array($lesson['groups']) && count($lesson['groups']) > 0): ?>
                                                <?php echo htmlspecialchars(implode(', ', $lesson['groups'])); ?>
                                            <?php elseif ($viewMode === 'teacher' && isset($lesson['group_name']) && !empty(trim($lesson['group_name']))): ?>
                                                <?php echo htmlspecialchars($lesson['group_name']); ?>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($lesson['teacher_name']); ?>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div id="emptyState" class="card card__inner" style=
                            "text-align:center;
                            background: linear-gradient(135deg, rgba(255,255,255,0.06) 0%, rgba(255,255,255,0.04) 100%);
                            border: 1px solid rgba(255,255,255,0.12);
                            border-radius: 16px;
                            backdrop-filter: blur(10px);
                            -webkit-backdrop-filter: blur(10px);
                            box-shadow: 0 4px 20px rgba(0,0,0,0.12);">
                            <div class="mb-3" style=
                                "margin-left:auto;
                                margin-right:auto;
                                width:3rem;
                                height:3rem;
                                border-radius:1rem;
                                display:grid;
                                place-items:center;
                                background:linear-gradient(135deg, rgba(255,255,255,0.15), rgba(255,255,255,0.08));
                                border: 1px solid rgba(255,255,255,0.2);
                                font-size: 1.5rem;">
                                <?php if ($currentDay == 7): ?>
                                    <i class="fas fa-coffee" style="color: #60a5fa;"></i>
                                <?php else: ?>
                                    ☕
                                <?php endif; ?>
                            </div>
                            <p class="h2" style="font-size:1.1rem; color: #fff; text-shadow: 0 1px 2px rgba(0,0,0,0.25);">
                                <?php if ($currentDay == 7): ?>
                                    На текущий день пар нет
                                <?php else: ?>
                                    Сегодня пар нет
                                <?php endif; ?>
                            </p>
                            <!-- DEBUG: currentDay=<?php echo $currentDay; ?>, dayNames=<?php echo json_encode($dayNames); ?> -->
                            <p class="small" style="color: rgba(255,255,255,0.75);">Отдохните или выберите другой день.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
            <?php endif; ?>
        </section>
    </main>

    <div class="switch-group">
        <button id="settingsBtn" class="btn" style="background: linear-gradient(135deg, #8b5cf6, #4338ca) !important; box-shadow: 0 8px 24px rgba(67, 56, 202, 0.35) !important; border: 2px solid rgba(255, 255, 255, 0.25) !important; color: #fff !important; font-weight: 600 !important;"><i class="fas fa-cog" style="margin-right: 8px;"></i>Настройки</button>
    </div>

    <!-- модалка выбора группы -->
    <div id="groupSelectionModal" style="display: none;">
        <div class="modal-card">
            <div style="text-align:center;" class="mb-4">
                <div style="width:4rem;height:4rem;margin:0 auto 1rem;border-radius:9999px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg, rgba(99,102,241,0.7), rgba(67,56,202,0.7));color:#fff;"><i class="fas fa-users" style="font-size: 24px;"></i></div>
                <h2 class="h2" style="margin:0 0 0.5rem;"><i class="fas fa-users" style="margin-right: 8px;"></i>Выберите группу</h2>
                <p class="small">Выбор сохраняется в течение 30 дней</p>
            </div>
            <div style="position: relative; margin-bottom: 0.5rem; display:flex; justify-content: space-between; gap:0.5rem; align-items:center;">
                <input type="text" id="groupSearch" placeholder="🔍 Поиск группы..." style="flex:1; padding: 0.75rem; border: 1px solid rgba(255,255,255,0.2); border-radius: 0.5rem; background: rgba(255,255,255,0.1); color: white; font-size: 0.875rem;" onkeyup="filterGroups()">
                <button type="button" class="btn" style="padding:0.6rem 0.8rem; font-size:0.8rem; white-space:nowrap;" onclick="switchToTeacherFromGroup()"><i class="fas fa-chalkboard-teacher" style="margin-right: 4px;"></i>Препод</button>
            </div>
            <div id="groupsList" class="space-y-3" style="max-height: 300px; overflow-y: auto;">
                <?php if (!empty($availableGroups)): ?>
                    <?php foreach ($availableGroups as $group): ?>
                        <button onclick="selectGroup('<?php echo htmlspecialchars($group); ?>')" class="group-btn group-item" data-name="<?php echo htmlspecialchars($group); ?>">
                            <div style="display:flex; align-items:center; gap:0.75rem; width:100%;">
                                <div class="group-icon" style="background: rgba(59,130,246,0.2);"><i class="fas fa-users" style="color:#93c5fd;font-size:16px;"></i></div>
                                <div style="min-width:0;">
                                    <div style="color:#fff;font-weight:600;" class="group-name"><?php echo htmlspecialchars($group); ?></div>
                                    <div class="small" style="opacity:0.7;">
                                        <?php echo htmlspecialchars($group); ?>
                                    </div>
                                </div>
                                <span class="fav-star" data-type="group" data-name="<?php echo htmlspecialchars($group); ?>" title="В избранное" onclick="toggleFavorite(event, 'group', '<?php echo htmlspecialchars($group); ?>')" style="margin-left:auto; cursor:pointer; user-select:none; font-size:1rem; line-height:1;">★</span>
                            </div>
                        </button>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="small" style="color:var(--muted); text-align:center;">Группы не найдены</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- модалка настроек -->
    <div id="settingsModal" style="display: none;">
        <div class="modal-card" style="position: relative;">
            <!-- Крестик для закрытия -->
            <button id="closeSettingsBtn" style="position: absolute; top: 1rem; right: 1rem; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); border-radius: 12px; width: 2rem; height: 2rem; display: flex; align-items: center; justify-content: center; color: #fff; cursor: pointer; transition: all 0.2s ease; z-index: 10;" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'"><i class="fas fa-times" style="font-size: 12px;"></i></button>
            <div style="text-align:center;" class="mb-4">
                <div style="width:4rem;height:4rem;margin:0 auto 1rem;border-radius:9999px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg, rgba(99,102,241,0.7), rgba(67,56,202,0.7));color:#fff;"><i class="fas fa-cog" style="font-size: 24px;"></i></div>
                
                <h2 class="h2" style="margin:0 0 0.5rem;"><i class="fas fa-cog" style="margin-right: 8px;"></i>Настройки</h2>
                <p class="small">Настройте отображение расписания</p>
                <div class="subtle">Обновлено: <span id="updatedAtDup"><?php echo date('H:i:s'); ?></span></div>
            </div>
            <div class="space-y-3">
                <div class="group-btn" style="cursor:pointer;" onclick="window.open('https://t.me/cowgivesmilk', '_blank')">
                    <div style="display:flex; align-items:center; gap:0.75rem;">
                        <div class="group-icon" style="background: rgba(34,197,94,0.2);"><span style="color:#86efac;font-weight:700;"><i class="fas fa-question" style="font-size:16px;"></i></span></div>
                        <div style="flex:1;">
                            <div style="color:#fff;font-weight:600;">Помощь</div>
                            <div class="small">Написать администратору</div>
                        </div>
                        <div class="btn" style="padding:0.5rem 1rem; font-size:0.75rem; pointer-events:none;"><span style="color:white;">Написать</span></div>
                    </div>
                </div>

                <!-- Выбор группы -->
                <div class="group-btn" style="cursor:pointer;" onclick="showGroupSelectionModal()">
                    <div style="display:flex; align-items:center; gap:0.75rem;">
                        <div class="group-icon" style="background: rgba(59,130,246,0.2);"><i class="fas fa-users" style="color:#93c5fd;font-size:16px;"></i></div>
                        <div style="flex:1;">
                            <div style="color:#fff;font-weight:600;">Группа</div>
                            <div class="small">Текущая: <?php echo $userGroup ?: 'Не выбрана'; ?></div>
                        </div>
                        <div class="btn" style="padding:0.5rem 1rem; font-size:0.75rem; pointer-events:none;"><i class="fas fa-edit" style="margin-right: 4px;"></i>Сменить</div>
                    </div>
                </div>

                <!-- Выбор преподавателя -->
                <div class="group-btn" style="cursor:pointer;" onclick="showTeacherSelectionModal()">
                    <div style="display:flex; align-items:center; gap:0.75rem;">
                        <div class="group-icon" style="background: rgba(168,85,247,0.2);"><i class="fas fa-chalkboard-teacher" style="color:#d8b4fe;font-size:16px;"></i></div>
                        <div style="flex:1;">
                            <div style="color:#fff;font-weight:600;">Препод</div>
                            <div class="small">Текущий: <?php echo $selectedTeacher ?: 'Не выбран'; ?></div>
                        </div>
                        <div class="btn" style="padding:0.5rem 1rem; font-size:0.75rem; pointer-events:none;"><i class="fas fa-edit" style="margin-right: 4px;"></i>Сменить</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- модалка выбора преподавателя -->
    <div id="teacherSelectionModal" style="display: none;">
        <div class="modal-card">
            <div style="text-align:center;" class="mb-4">
                <div style="width:4rem;height:4rem;margin:0 auto 1rem;border-radius:9999px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg, rgba(168,85,247,0.7), rgba(59,130,246,0.7));color:#fff;"><i class="fas fa-chalkboard-teacher" style="font-size: 24px;"></i></div>
                <h2 class="h2" style="margin:0 0 0.5rem;"><i class="fas fa-chalkboard-teacher" style="margin-right: 8px;"></i>Выберите преподавателя</h2>
                <p class="small">Просмотр расписания преподавателя</p>
            </div>
            <div style="position: relative; margin-bottom: 0.5rem; display:flex; justify-content: space-between; gap:0.5rem; align-items:center;">
                <input type="text" id="teacherSearch" placeholder="🔍 Поиск преподавателя..." style="flex:1; padding: 0.75rem; border: 1px solid rgba(255,255,255,0.2); border-radius: 0.5rem; background: rgba(255,255,255,0.1); color: white; font-size: 0.875rem;" onkeyup="filterTeachers()">
                <button type="button" class="btn" style="padding:0.6rem 0.8rem; font-size:0.8rem; white-space:nowrap;" onclick="switchToGroupFromTeacher()"><i class="fas fa-users" style="margin-right: 4px;"></i>Группы</button>
            </div>
            <div class="space-y-3" id="teachersList" style="max-height: 300px; overflow-y: auto;">
                <?php if (!empty($availableTeachers)): ?>
                    <?php foreach ($availableTeachers as $teacher): ?>
                        <button onclick="selectTeacher('<?php echo htmlspecialchars($teacher); ?>')" class="group-btn teacher-item" data-name="<?php echo htmlspecialchars($teacher); ?>">
                            <div style="display:flex; align-items:center; gap:0.75rem; width:100%;">
                                <div class="group-icon" style="background: rgba(168,85,247,0.2);"><i class="fas fa-chalkboard-teacher" style="color:#d8b4fe;font-size:16px;"></i></div>
                                <div style="min-width:0;">
                                    <div style="color:#fff;font-weight:600;" class="teacher-name"><?php echo htmlspecialchars($teacher); ?></div>
                                    <div class="small" style="opacity:0.7;">Преподаватель</div>
                                </div>
                                <span class="fav-star" data-type="teacher" data-name="<?php echo htmlspecialchars($teacher); ?>" title="В избранное" onclick="toggleFavorite(event, 'teacher', '<?php echo htmlspecialchars($teacher); ?>')" style="margin-left:auto; cursor:pointer; user-select:none; font-size:1rem; line-height:1;">★</span>
                            </div>
                        </button>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="small" style="color:var(--muted); text-align:center;">Преподаватели не найдены</div>
                <?php endif; ?>
                
            </div>
        </div>
    </div>

    <script>
        function switchToTeacherFromGroup(){
            document.getElementById('groupSelectionModal').style.display = 'none';
            showTeacherSelectionModal();
        }
        function switchToGroupFromTeacher(){
            document.getElementById('teacherSelectionModal').style.display = 'none';
            showGroupSelectionModal();
        }
        window.SCHEDULE_BOOTSTRAP = {
            week: <?php echo (int)$currentWeek; ?>,
            day: <?php echo (int)$currentDay; ?>,
            group: <?php echo json_encode($userGroup ?: ''); ?>,
            teacher: <?php echo json_encode($selectedTeacher ?: null); ?>,
            viewMode: <?php echo json_encode($viewMode); ?>,
            daySchedule: <?php echo json_encode($daySchedule, JSON_UNESCAPED_UNICODE); ?>,
            availableGroups: <?php echo json_encode($availableGroups, JSON_UNESCAPED_UNICODE); ?>,
            availableTeachers: <?php echo json_encode($availableTeachers, JSON_UNESCAPED_UNICODE); ?>,
        };
    </script>
    <script src="assets/js/schedule_js.js?v=<?php echo file_exists('cache_version.txt') ? file_get_contents('cache_version.txt') : time(); ?>"></script>
    
    <script>
        // Поиск и избранное
        function getFavorites(type) {
            try {
                const raw = localStorage.getItem('favorites_' + type);
                return raw ? JSON.parse(raw) : [];
            } catch (e) { return []; }
        }
        function setFavorites(type, list) {
            try { localStorage.setItem('favorites_' + type, JSON.stringify(list)); } catch (e) {}
        }
        function toggleFavorite(e, type, name) {
            e.stopPropagation();
            e.preventDefault();
            const favs = getFavorites(type);
            const idx = favs.indexOf(name);
            if (idx === -1) favs.unshift(name); else favs.splice(idx, 1);
            setFavorites(type, favs);
            sortListByFavorites(type);
        }
        function paintFavoriteStars(type) {
            const favs = getFavorites(type);
            const selector = type === 'group' ? '#groupsList .fav-star' : '#teachersList .fav-star';
            document.querySelectorAll(selector).forEach(star => {
                const name = star.getAttribute('data-name') || '';
                if (favs.includes(name)) {
                    star.style.color = '#ffd166';
                } else {
                    star.style.color = 'rgba(255,255,255,0.35)';
                }
            });
        }

        function sortListByFavorites(type) {
            const container = type === 'group' ? document.getElementById('groupsList') : document.getElementById('teachersList');
            if (!container) return;
            const favs = getFavorites(type);
            const items = Array.from(container.querySelectorAll(type === 'group' ? '.group-item' : '.teacher-item'));
            items.sort((a,b) => {
                const an = a.getAttribute('data-name') || '';
                const bn = b.getAttribute('data-name') || '';
                const ai = favs.indexOf(an);
                const bi = favs.indexOf(bn);
                const af = ai === -1 ? 9999 : ai;
                const bf = bi === -1 ? 9999 : bi;
                if (af !== bf) return af - bf;
                return an.localeCompare(bn, 'ru');
            });
            items.forEach(el => container.appendChild(el));
            paintFavoriteStars(type);
        }
        function filterGroups() {
            const q = (document.getElementById('groupSearch').value || '').toLowerCase();
            document.querySelectorAll('#groupsList .group-item').forEach(it => {
                const name = (it.getAttribute('data-name') || '').toLowerCase();
                it.style.display = name.includes(q) ? '' : 'none';
            });
        }
        function filterTeachers() {
            const q = (document.getElementById('teacherSearch').value || '').toLowerCase();
            document.querySelectorAll('#teachersList .teacher-item').forEach(it => {
                const name = (it.getAttribute('data-name') || '').toLowerCase();
                it.style.display = name.includes(q) ? '' : 'none';
            });
        }
        function showGroupSelectionModal() {
            document.getElementById('groupSelectionModal').style.display = 'flex';
            sortListByFavorites('group');
        }
        function showTeacherSelectionModal() {
            document.getElementById('teacherSelectionModal').style.display = 'flex';
            sortListByFavorites('teacher');
        }
        function selectGroup(groupName) {
            window.location.href = '?group=' + encodeURIComponent(groupName);
        }
        function selectTeacher(teacherName) {
            window.location.href = '?teacher=' + encodeURIComponent(teacherName);
        }

        // Глобальный поиск
        let searchTimeout;
        let isSearchActive = false;

        function performGlobalSearch(query) {
            if (!query || query.length < 2) {
                hideSearchResults();
                return;
            }

            const results = [];
            const normalizedQuery = normalizeSearchQuery(query);

            // Поиск по группам
            if (window.SCHEDULE_BOOTSTRAP.availableGroups) {
                window.SCHEDULE_BOOTSTRAP.availableGroups.forEach(group => {
                    const score = calculateGroupMatchScore(group, normalizedQuery);
                    if (score > 0) {
                        results.push({
                            type: 'group',
                            name: group,
                            title: group,
                            subtitle: 'Группа',
                            score: score
                        });
                    }
                });
            }

            // Поиск по преподавателям
            if (window.SCHEDULE_BOOTSTRAP.availableTeachers) {
                window.SCHEDULE_BOOTSTRAP.availableTeachers.forEach(teacher => {
                    if (teacher.toLowerCase().includes(normalizedQuery.toLowerCase())) {
                        results.push({
                            type: 'teacher',
                            name: teacher,
                            title: teacher,
                            subtitle: 'Преподаватель',
                            score: 1
                        });
                    }
                });
            }

            // Сортируем результаты по релевантности
            results.sort((a, b) => b.score - a.score);

            displaySearchResults(results);
        }

        // Нормализация поискового запроса
        function normalizeSearchQuery(query) {
            return query
                .toLowerCase()
                .replace(/\s+/g, '') // убираем пробелы
                .replace(/[-\/:]/g, ''); // убираем дефисы и слеши
        }

        // Нормализация названия группы для поиска
        function normalizeGroupName(groupName) {
            return groupName
                .toLowerCase()
                .replace(/\s+/g, '') // убираем пробелы
                .replace(/[-\/:]/g, ''); // убираем дефисы и слеши
        }

        // Расчет релевантности для групп
        function calculateGroupMatchScore(groupName, query) {
            const normalizedGroup = normalizeGroupName(groupName);
            const normalizedQuery = normalizeSearchQuery(query);
            
            let score = 0;

            if (normalizedGroup === normalizedQuery) {
                score = 100;
            } else if (normalizedGroup.startsWith(normalizedQuery)) {
                score = 80;
            } else if (normalizedGroup.includes(normalizedQuery)) {
                score = 60;
            } else {
                const queryParts = normalizedQuery.split('');
                const groupParts = normalizedGroup.split('');
                
                let matchedParts = 0;
                let queryIndex = 0;
                
                for (let i = 0; i < groupParts.length && queryIndex < queryParts.length; i++) {
                    if (groupParts[i] === queryParts[queryIndex]) {
                        matchedParts++;
                        queryIndex++;
                    }
                }
                
                if (queryIndex === queryParts.length) {
                    score = 40 + (matchedParts * 5);
                }
                
                if (score === 0) {
                    if (normalizedQuery.length >= 3) {
                        const abbreviations = extractAbbreviations(groupName);
                        for (const abbr of abbreviations) {
                            if (abbr.toLowerCase().includes(normalizedQuery) || normalizedQuery.includes(abbr.toLowerCase())) {
                                score = 30;
                                break;
                            }
                        }
                    }
                    
                    if (score === 0 && /^\d+$/.test(query)) {
                        const originalGroup = groupName.toLowerCase();
                        const numbers = originalGroup.match(/\d+/g);
                        if (numbers && numbers.some(num => num.includes(query))) {
                            score = 25;
                        }
                    }
                }
            }

            return score;
        }

        function extractAbbreviations(groupName) {
            const parts = groupName.split(/[-\/:]/);
            const abbreviations = [];
            parts.forEach(part => {
                if (part.length >= 2 && part.length <= 6 && /^[а-яё]+$/i.test(part)) {
                    abbreviations.push(part);
                }
            });
            return abbreviations;
        }

        function displaySearchResults(results) {
            const resultsContainer = document.getElementById('searchResults');
            if (!resultsContainer) return;

            if (results.length === 0) {
                resultsContainer.innerHTML = '<div class="search-no-results">Ничего не найдено</div>';
            } else {
                resultsContainer.innerHTML = results.map(result => `
                    <div class="search-result-item" onclick="selectFromSearch('${result.type}', '${result.name.replace(/'/g, "\\'")}')">
                        <div class="search-result-icon ${result.type}">
                            <i class="fas ${result.type === 'group' ? 'fa-users' : 'fa-chalkboard-teacher'}"></i>
                        </div>
                        <div class="search-result-content">
                            <div class="search-result-title">${result.title}</div>
                            <div class="search-result-subtitle">${result.subtitle}</div>
                        </div>
                    </div>
                `).join('');
            }

            resultsContainer.style.display = 'block';
            isSearchActive = true;
        }

        function hideSearchResults() {
            const resultsContainer = document.getElementById('searchResults');
            if (resultsContainer) {
                resultsContainer.style.display = 'none';
            }
            isSearchActive = false;
        }

        function selectFromSearch(type, name) {
            const searchInput = document.getElementById('globalSearch');
            const query = searchInput ? searchInput.value.trim() : '';
            addToSearchHistory(query, type, name);
            
            if (type === 'group') {
                window.location.href = '?group=' + encodeURIComponent(name);
            } else if (type === 'teacher') {
                window.location.href = '?teacher=' + encodeURIComponent(name);
            }
        }

        function clearSearch() {
            const searchInput = document.getElementById('globalSearch');
            const clearBtn = document.getElementById('clearSearch');
            
            if (searchInput) {
                searchInput.value = '';
                searchInput.focus();
            }
            
            if (clearBtn) {
                clearBtn.style.display = 'none';
            }
            
            hideSearchResults();
        }

        function getSearchHistory() {
            try {
                const history = localStorage.getItem('searchHistory');
                return history ? JSON.parse(history) : [];
            } catch (e) {
                return [];
            }
        }

        function saveSearchHistory(history) {
            try {
                localStorage.setItem('searchHistory', JSON.stringify(history));
            } catch (e) {}
        }

        function addToSearchHistory(query, type, name) {
            const history = getSearchHistory();
            const newItem = { query, type, name, timestamp: Date.now() };
            const filteredHistory = history.filter(item => 
                !(item.query === query && item.type === type && item.name === name)
            );
            filteredHistory.unshift(newItem);
            const limitedHistory = filteredHistory.slice(0, 5);
            saveSearchHistory(limitedHistory);
        }

        function displaySearchHistory() {
            const historyContainer = document.getElementById('searchHistory');
            const history = getSearchHistory();
            
            if (!historyContainer) return;
            
            if (history.length === 0) {
                historyContainer.style.display = 'none';
                return;
            }
            
            historyContainer.innerHTML = history.map(item => `
                <div class="search-history-item" onclick="selectFromHistory('${item.type}', '${item.name.replace(/'/g, "\\'")}')">
                    <div class="search-history-icon ${item.type}">
                        <i class="fas ${item.type === 'group' ? 'fa-users' : 'fa-chalkboard-teacher'}"></i>
                    </div>
                    <div class="search-history-content">
                        <div class="search-history-title">${item.name}</div>
                        <div class="search-history-subtitle">${item.type === 'group' ? 'Группа' : 'Преподаватель'} • ${new Date(item.timestamp).toLocaleDateString()}</div>
                    </div>
                </div>
            `).join('') + `
                <div class="search-history-clear" onclick="clearSearchHistory()">
                    <i class="fas fa-trash" style="margin-right: 0.5rem;"></i>Очистить историю
                </div>
            `;
            
            historyContainer.style.display = 'block';
        }

        function hideSearchHistory() {
            const historyContainer = document.getElementById('searchHistory');
            if (historyContainer) {
                historyContainer.style.display = 'none';
            }
        }

        function selectFromHistory(type, name) {
            if (type === 'group') {
                window.location.href = '?group=' + encodeURIComponent(name);
            } else if (type === 'teacher') {
                window.location.href = '?teacher=' + encodeURIComponent(name);
            }
        }

        function clearSearchHistory() {
            localStorage.removeItem('searchHistory');
            hideSearchHistory();
        }

        // Инициализация поиска
        function initGlobalSearch() {
            const searchInput = document.getElementById('globalSearch');
            const clearBtn = document.getElementById('clearSearch');

            if (!searchInput) return;

            // Показ истории при фокусе, если поле пустое
            searchInput.addEventListener('focus', function() {
                if (searchInput.value.trim() === '') {
                    displaySearchHistory();
                }
            });

            // Обработка ввода
            searchInput.addEventListener('input', function(e) {
                const query = e.target.value.trim();

                if (query.length > 0) {
                    if (clearBtn) clearBtn.style.display = 'flex';
                    hideSearchHistory();
                } else {
                    if (clearBtn) clearBtn.style.display = 'none';
                    displaySearchHistory();
                }

                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    performGlobalSearch(query);
                }, 150);
            });

            // Кнопка очистки
            if (clearBtn) {
                clearBtn.addEventListener('click', clearSearch);
            }

            // Скрытие результатов при клике вне поиска
            document.addEventListener('click', function(e) {
                const searchContainer = document.querySelector('.search-container');
                if (searchContainer && !searchContainer.contains(e.target)) {
                    hideSearchResults();
                    hideSearchHistory();
                }
            });

            // Обработка Esc
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    hideSearchResults();
                    hideSearchHistory();
                    searchInput.blur();
                }
            });
        }

        function hexToRgba(hex, a) {
            const h = hex.replace('#','');
            const r = parseInt(h.substring(0,2),16);
            const g = parseInt(h.substring(2,4),16);
            const b = parseInt(h.substring(4,6),16);
            return `rgba(${r}, ${g}, ${b}, ${a})`;
        }

        // Цветовые акценты для карточек: лекции (голубой), практики (синий)
        function addColorAccents() {
            const lessonCards = document.querySelectorAll('.lesson-card');
            const fallback = ['#8b5cf6', '#6366f1', '#60a5fa']; // фиолетовый/индиго/голубой
            lessonCards.forEach(function(card, index) {
                if (card.dataset.accentApplied === '1') return;

                const titleEl = card.querySelector('h3, .h2');
                const title = (titleEl && titleEl.textContent ? titleEl.textContent : '').trim().toLowerCase();

                // По умолчанию фиолетовый акцент
                let color = fallback[index % fallback.length];
                // Лекции — голубой, Практики — синий
                if (title.startsWith('л.')) { // лекция
                    color = '#60a5fa'; // sky-400
                } else if (title.startsWith('пр.')) { // практика
                    color = '#1e40af'; // blue-900
                }

                // Левая полоса-акцент
                if (!card.querySelector('.lesson-color-accent')) {
                    const accent = document.createElement('div');
                    accent.className = 'lesson-color-accent';
                    accent.style.cssText = `position:absolute;left:0;top:0;bottom:0;width:3px;background:${color};border-radius:0 8px 8px 0;opacity:.95;`;
                    card.style.position = 'relative';
                    card.appendChild(accent);
                }

                // Легкий цветовой тинт фона и подкраска рамки
                if (!card.querySelector('.lesson-tint')) {
                    const tint = document.createElement('div');
                    tint.className = 'lesson-tint';
                    tint.style.cssText = `position:absolute;inset:0;background:linear-gradient(180deg, ${hexToRgba(color,0.10)} 0%, transparent 80%);pointer-events:none;border-radius:inherit;`;
                    card.appendChild(tint);
                }
                card.style.borderColor = hexToRgba(color, 0.35);

                card.dataset.accentApplied = '1';
            });
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Инициализируем глобальный поиск
            initGlobalSearch();
            // Добавляем цветные акценты
            addColorAccents();
            // Периодически проверяем и добавляем акценты к новым блокам
            setInterval(addColorAccents, 500);
        });
    </script>
    
    <style>
        /* Чистый тёмно-фиолетовый фон */
        body {
            background:
                radial-gradient(1200px 600px at 50% -100px, rgba(255,255,255,0.03), transparent 70%),
                radial-gradient(900px 500px at 20% 0%, rgba(99,102,241,0.12), transparent 70%),
                radial-gradient(800px 400px at 0% 100%, rgba(139,92,246,0.10), transparent 60%),
                linear-gradient(180deg, #0c0a1f 0%, #0b0a1c 55%, #0a0a19 100%);
            background-attachment: fixed;
        }

        .header { box-shadow: inset 0 -1px 0 rgba(255,255,255,0.06); position: relative; z-index: 3; }
        
        /* Поисковая строка */
        .search-container { position: relative; flex: 1; max-width: 500px; margin-right: 1rem; }
        .search-input-wrapper {
            position: relative; display: flex; align-items: center;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px; padding: 0.5rem 1rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
        }
        .search-input-wrapper:focus-within { background: rgba(255, 255, 255, 0.15); border-color: rgba(99, 102, 241, 0.5); box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1); }
        .search-icon { color: rgba(255, 255, 255, 0.6); margin-right: 0.75rem; font-size: 0.9rem; transition: color 0.2s ease; }
        .search-input-wrapper:focus-within .search-icon { color: rgba(99, 102, 241, 0.8); }
        #globalSearch { flex: 1; background: transparent; border: none; outline: none; color: white; font-size: 0.9rem; font-weight: 500; padding: 0; }
        #globalSearch::placeholder { color: rgba(255, 255, 255, 0.5); font-weight: 400; }
        .clear-search-btn { background: rgba(255, 255, 255, 0.1); border: none; border-radius: 12px; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; color: rgba(255, 255, 255, 0.6); cursor: pointer; transition: all 0.2s ease; margin-left: 0.5rem; }
        .clear-search-btn:hover { background: rgba(255, 255, 255, 0.2); color: white; }

        .search-results { position: absolute; top: 100%; left: 0; right: 0; background: rgba(15, 23, 42, 0.95); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px; margin-top: 0.5rem; max-height: 300px; overflow-y: auto; backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3); z-index: 1000; }
        .search-result-item { display: flex; align-items: center; padding: 0.75rem 1rem; cursor: pointer; transition: all 0.2s ease; border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
        .search-result-item:last-child { border-bottom: none; }
        .search-result-item:hover { background: rgba(255, 255, 255, 0.1); }
        .search-result-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 0.75rem; font-size: 0.9rem; }
        .search-result-icon.group { background: rgba(59, 130, 246, 0.2); color: #93c5fd; }
        .search-result-icon.teacher { background: rgba(168, 85, 247, 0.2); color: #d8b4fe; }
        .search-result-content { flex: 1; min-width: 0; }
        .search-result-title { color: white; font-weight: 600; font-size: 0.9rem; margin-bottom: 0.25rem; }
        .search-result-subtitle { color: rgba(255, 255, 255, 0.6); font-size: 0.8rem; }
        .search-no-results { padding: 1.5rem; text-align: center; color: rgba(255, 255, 255, 0.6); font-size: 0.9rem; }

        /* История поиска */
        .search-history { position: absolute; top: 100%; left: 0; right: 0; background: rgba(15, 23, 42, 0.95); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px; margin-top: 0.5rem; max-height: 200px; overflow-y: auto; backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3); z-index: 1000; }
        .search-history-item { display: flex; align-items: center; padding: 0.75rem 1rem; cursor: pointer; transition: all 0.2s ease; border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
        .search-history-item:last-child { border-bottom: none; }
        .search-history-item:hover { background: rgba(255, 255, 255, 0.1); }
        .search-history-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 0.75rem; font-size: 0.9rem; background: rgba(255, 255, 255, 0.1); color: rgba(255, 255, 255, 0.6); }
        .search-history-content { flex: 1; min-width: 0; }
        .search-history-title { color: white; font-weight: 600; font-size: 0.9rem; margin-bottom: 0.25rem; }
        .search-history-subtitle { color: rgba(255, 255, 255, 0.6); font-size: 0.8rem; }
        .search-history-clear { padding: 0.5rem; text-align: center; color: rgba(255, 255, 255, 0.5); font-size: 0.8rem; border-top: 1px solid rgba(255, 255, 255, 0.1); cursor: pointer; transition: color 0.2s ease; }
        .search-history-clear:hover { color: rgba(255, 255, 255, 0.8); }

        /* Компактные размеры для всего интерфейса */
        .h1 { font-size: 1.1rem !important; font-weight: 600; letter-spacing: -0.01em; }
        .h2 { font-size: 1rem !important; font-weight: 600; }
        .small { font-size: 0.8rem !important; color: var(--muted-300); }
        .muted { color: var(--muted); }
        
        /* Компактные отступы */
        .space-y-3 > * + * { margin-top: 0.5rem !important; }
        .space-y-6 > * + * { margin-top: 1rem !important; }
        .mt-4 { margin-top: 0.75rem !important; }
        .mt-5 { margin-top: 1rem !important; }
        .mt-6 { margin-top: 1.25rem !important; }
        .mb-3 { margin-bottom: 0.5rem !important; }
        .mb-4 { margin-bottom: 0.75rem !important; }
        
        /* Единообразное закругление для всех элементов */
        .btn { padding: 0.4rem 0.8rem !important; font-size: 0.8rem !important; border-radius: 12px !important; }
        .modal-card { padding: 1.5rem !important; max-width: 24rem !important; border-radius: 16px !important; }
        .group-btn { padding: 0.75rem !important; border-radius: 12px !important; }
        .group-icon { width: 2rem !important; height: 2rem !important; font-size: 0.9rem !important; border-radius: 10px !important; }
        .card { border-radius: 12px !important; }
        .card__inner { border-radius: 12px !important; }
        .day-btn { border-radius: 12px !important; }
        .segmented { border-radius: 12px !important; }
        .segmented button { border-radius: 10px !important; }
        .chip { border-radius: 12px !important; }
        .progress { border-radius: 12px !important; }
        .search-input-wrapper { border-radius: 12px !important; }
        .search-results { border-radius: 12px !important; }
        .search-history { border-radius: 12px !important; }
        .search-result-item { border-radius: 8px !important; }
        .search-history-item { border-radius: 8px !important; }
        .search-result-icon { border-radius: 10px !important; }
        .search-history-icon { border-radius: 10px !important; }
        .progress { height: 6px !important; border-radius: 12px !important; }
        .progress__meta { font-size: 0.7rem !important; margin-top: 0.25rem !important; }
        .grid { gap: 0.75rem !important; }
        .grid--two { gap: 0.75rem !important; }
        .days__row { gap: 0.4rem !important; padding: 0.2rem !important; }
        .header { padding: 0.75rem 1rem !important; }
        .header__row { gap: 0.5rem !important; }

        /* imsitMaps промо-полоска */
        .imsitmaps-promo { 
            margin: 0.5rem 0 0.75rem; 
            padding: 0.35rem 0.5rem; 
            background: rgba(255,255,255,0.05); 
            border: 1px solid rgba(255,255,255,0.10); 
            border-radius: 12px; 
            backdrop-filter: blur(6px); 
        }
        .imsitmaps-promo .row { 
            display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; 
        }
        .imsitmaps-promo .note { 
            color: rgba(255,255,255,0.75); font-size: 0.8rem; 
        }
        .imsitmaps-promo .btn { 
            padding: 0.35rem 0.7rem !important; font-size: 0.8rem !important; 
            background: linear-gradient(135deg, #60a5fa, #6366f1); 
            border: 1px solid rgba(255,255,255,0.2);
            color: #fff; 
        }

        /* Компактные карточки вверху */
        [data-cards] { position: relative; z-index: 3; gap: 0.75rem !important; }
        [data-cards] > .card.card__inner { 
            background: linear-gradient(135deg, rgba(59,130,246,0.10), rgba(139,92,246,0.08)) !important; 
            border-color: rgba(255,255,255,0.10) !important; 
            padding: 1rem !important;
        }
        .card.card__inner { 
            background: rgba(255,255,255,0.045) !important; 
            border-color: rgba(255,255,255,0.10) !important; 
            position: relative; z-index: 2; padding: 1rem !important; border-radius: 12px !important;
        }
        #nowCard { background: linear-gradient(180deg, rgba(96,165,250,0.10) 0%, rgba(255,255,255,0.035) 100%) !important; box-shadow: 0 0 0 1px rgba(96,165,250,0.25) inset; }
        #nextCard { background: linear-gradient(180deg, rgba(99,102,241,0.10) 0%, rgba(255,255,255,0.035) 100%) !important; box-shadow: 0 0 0 1px rgba(99,102,241,0.25) inset; }

        @media (max-width: 768px) {
            .search-container { max-width: none; margin-right: 0.5rem; }
            .search-input-wrapper { padding: 0.4rem 0.75rem; }
            #globalSearch { font-size: 0.85rem; }
            .search-results { max-height: 250px; }
            .search-result-item { padding: 0.6rem 0.75rem; }
            .search-result-icon { width: 28px; height: 28px; font-size: 0.8rem; margin-right: 0.5rem; }
        }
    </style>
    
    <script>
        // Генерация снежинок
        function createSnowflakes() {
            const snowflakesCount = 50;
            const body = document.body;
            
            for (let i = 0; i < snowflakesCount; i++) {
                const snowflake = document.createElement('div');
                snowflake.className = 'snowflake';
                snowflake.innerHTML = '❄';
                snowflake.style.left = Math.random() * 100 + 'vw';
                snowflake.style.animationDuration = Math.random() * 3 + 2 + 's';
                snowflake.style.opacity = Math.random();
                snowflake.style.fontSize = Math.random() * 10 + 10 + 'px';
                
                body.appendChild(snowflake);
                
                // Перезапуск анимации
                snowflake.addEventListener('animationiteration', () => {
                    snowflake.style.left = Math.random() * 100 + 'vw';
                    snowflake.style.opacity = Math.random();
                });
            }
        }

        document.addEventListener('DOMContentLoaded', createSnowflakes);
    </script>
</body>
</html>

