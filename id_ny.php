<?php
/*
                ██╗███╗   ███╗███████╗██╗████████╗    ██╗██████╗ 
                ██║████╗ ████║██╔════╝██║╚══██╔══╝    ██║██╔══██╗
                ██║██╔████╔██║███████╗██║   ██║       ██║██║  ██║
                ██║██║╚██╔╝██║╚════██║██║   ██║       ██║██║  ██║
                ██║██║ ╚═╝ ██║███████║██║   ██║       ██║██████╔╝
                ╚═╝╚═╝     ╚═╝╚══════╝╚═╝   ╚═╝       ╚═╝╚═════╝ 

    ╔══════════════════════════════════════════════════════════════════════════════╗
    ║                               Version 3.1 (NY Edition)                       ║
    ║                                                                              ║
    ║     Новогоднее оформление: гирлянды, падающий снег, снежинки и снеговики     ║
    ║     Адаптивный дизайн и улучшенная читаемость                                ║
    ║     Примерное окончание текущей пары с округлением до 5 минут                ║
    ╚══════════════════════════════════════════════════════════════════════════════╝
*/
// Отключение кеширования
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';
require_once 'includes/ScheduleManager.php';

date_default_timezone_set('Europe/Moscow');

$scheduleManager = new ScheduleManager($pdo);

// инфа о пользователе
$currentUser = null;

// Определение типа пары по названию предмета (пр./л./практика/лекция, с вариациями пробелов и точек)
if (!function_exists('detectLessonType')) {
    function detectLessonType($subjectName) {
        $s = is_string($subjectName) ? mb_strtolower(trim($subjectName), 'UTF-8') : '';
        // убрать начальные служебные символы
        $s = preg_replace('/^[\s\-–—]+/u', '', $s);
        if (preg_match('/^(пр\.?|пра\p{L}*|практика)/u', $s)) {
            return 'practice';
        }
        if (preg_match('/^(л\.?|лек\p{L}*|лекция)/u', $s)) {
            return 'lecture';
        }
        return '';
    }
}

// Проверяем авторизацию
if (isset($_SESSION['user_id'])) {
    try {
        require_once 'config/auth.php';
        $currentUser = getCurrentUser();
    } catch (Exception $e) {
        $currentUser = null;
    }
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
    <meta name="theme-color" content="#0f172a">
    <title>ImsitID - Новогоднее расписание</title>
    <meta name="description" content="ImsitID - Новогодняя версия расписания с гирляндами и снегом.">
    <meta name="keywords" content="Расписание, ИМСИТ, Новый год, гирлянды, снег">
    <meta name="author" content="ImsitShop">
    <meta name="robots" content="index, follow">
    <meta name="googlebot" content="index, follow">
    <meta name="yandexbot" content="index, follow">
    <meta name="google" content="notranslate">
    <meta name="google" content="notranslate">
    <link rel="canonical" href="https://imsit.shop/id_ny.php">
    <link rel="preload" as="style" href="assets/css/schedule_style.css?v=<?php echo file_exists('cache_version.txt') ? file_get_contents('cache_version.txt') : time(); ?>"/>
    <link rel="stylesheet" href="assets/css/schedule_style.css?v=<?php echo file_exists('cache_version.txt') ? file_get_contents('cache_version.txt') : time(); ?>"/>
    <!-- Font Awesome Free CDN (для фоновых иконок) → убран integrity, чтобы избежать блокировки SRI -->
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer"/>
    <style>
        /* Новогодний фон: мягкий градиент, туман и лёгкий шум */
        html { scroll-behavior: smooth; }
        body { 
            background: radial-gradient(1200px 600px at 20% -10%, rgba(59,130,246,0.25), transparent 60%), 
                        radial-gradient(1200px 600px at 80% -10%, rgba(236,72,153,0.18), transparent 60%), 
                        linear-gradient(180deg, #0b1220 0%, #0b1326 100%);
            background-attachment: fixed;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Гирлянда (фоновая картинка поверх страницы, если загружена) */
        .ny-garland-image { position: fixed; top: 0; left: 0; right: 0; height: 120px; background-image: url('assets/images/garland_top.png'); background-repeat: repeat-x; background-size: contain; background-position: top center; pointer-events: none; z-index: 0; opacity: 0.9; transform: translateZ(0); will-change: auto; }

        /* SVG/CSS гирлянды — лампочки с анимацией яркости */
        .ny-garlands { position: fixed; top: 0; left: 0; right: 0; height: 100px; pointer-events: none; z-index: 0; transform: translateZ(0); will-change: auto; }
        .garland-row { position: absolute; left: 0; right: 0; height: 80px; display: flex; justify-content: space-between; align-items: flex-start; padding: 0 24px; }
        /* один верхний ряд */
        .bulb { width: 12px; height: 18px; border-radius: 8px 8px 12px 12px; box-shadow: 0 0 14px currentColor, 0 0 24px currentColor inset; animation: bulbGlow 1.8s ease-in-out infinite; transform-origin: top center; }
        .bulb.red { color: #ef4444; background: #ef4444; }
        .bulb.green { color: #22c55e; background: #22c55e; }
        .bulb.blue { color: #3b82f6; background: #3b82f6; }
        .bulb.yellow { color: #f59e0b; background: #f59e0b; }
        .bulb.purple { color: #a855f7; background: #a855f7; }
        .bulb.cyan { color: #06b6d4; background: #06b6d4; }
        .bulb.white { color: #fff; background: #fff; }
        @keyframes bulbGlow { 0%,100% { filter: brightness(0.8); transform: rotate(2deg); } 50% { filter: brightness(1.3); transform: rotate(-2deg); } }
        .garland-wire { position: absolute; top: 20px; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, rgba(34,197,94,0.6), rgba(59,130,246,0.6)); opacity: 0.6; filter: blur(0.5px); }

        /* Слои: контент и шапка выше гирлянд */
        .header { position: relative; z-index: 3; transform: translateZ(0); }
        main { position: relative; z-index: 2; transform: translateZ(0); }

        /* Падающий снег */
        .ny-snowfield { position: fixed; inset: 0; pointer-events: none; z-index: 1; overflow: hidden; }
        .ny-flake { position: absolute; top: -5vh; color: rgba(255,255,255,0.85); opacity: 0.7; text-shadow: 0 0 3px rgba(255,255,255,0.75), 0 0 6px rgba(59,130,246,0.25); will-change: transform, margin-left, opacity; animation-name: snowfall, swayX; animation-timing-function: linear, ease-in-out; animation-iteration-count: infinite, infinite; }
        @keyframes snowfall { 0% { transform: translateY(-10vh); opacity: 0; } 10% { opacity: 1; } 100% { transform: translateY(110vh); opacity: 0; } }
        @keyframes swayX { 0% { margin-left: 0; } 50% { margin-left: 24px; } 100% { margin-left: 0; } }

        /* Снеговики по углам (появятся, если вы загрузите PNG) */
        .ny-snowman { position: fixed; bottom: 8px; width: 160px; height: auto; pointer-events: none; z-index: 4; opacity: 0.95; }
        .ny-snowman.left { left: 8px; transform: scale(0.95); }
        .ny-snowman.right { right: 8px; transform: scale(0.95) scaleX(-1); }

        /* Сугроб убран по запросу */

        /* Немного «зимнего льда» для карточек */
        .lesson-card, .card__inner { backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); }
        .card__inner { background: linear-gradient(135deg, rgba(255,255,255,0.12) 0%, rgba(255,255,255,0.06) 100%) !important; border: 1px solid rgba(255,255,255,0.18) !important; box-shadow: 0 6px 26px rgba(0,0,0,0.18), 0 0 0 1px rgba(255,255,255,0.05) inset !important; }

        /* Кнопка настроек — зимняя расцветка */
        #settingsBtn { background: linear-gradient(135deg, #38bdf8 0%, #a78bfa 100%) !important; box-shadow: 0 8px 24px rgba(56,189,248,0.35) !important; border: 2px solid rgba(255,255,255,0.35) !important; }

        /* Немного «инея» на заголовках */
        .h1, .h2 { text-shadow: 0 1px 2px rgba(0,0,0,0.25), 0 0 24px rgba(255,255,255,0.08); }

        /* Дерево на фоне - убрано */
        /* .ny-tree { position: fixed; right: 0; bottom: 0; width: min(32vw, 420px); height: min(40vh, 520px); background: url('assets/images/tree-1.png') no-repeat bottom right / contain; pointer-events: none; z-index: 0; opacity: 0.95; transform: translateZ(0); } */

        /* Улучшаем читаемость шапки поверх гирлянд */
        .header { backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); background: linear-gradient(180deg, rgba(2,6,23,0.65), rgba(2,6,23,0.35)); border-bottom: 1px solid rgba(255,255,255,0.12); box-shadow: 0 10px 24px rgba(0,0,0,0.25); }
        .header .subtle { color: #e2e8f0; text-shadow: 0 1px 2px rgba(0,0,0,0.35); }
        .header .btn { background: linear-gradient(135deg, #0ea5e9, #60a5fa) !important; border: 1px solid rgba(255,255,255,0.3) !important; color: #fff !important; box-shadow: 0 6px 18px rgba(14,165,233,0.35) !important; }
        .header .login { background: linear-gradient(135deg, #38bdf8, #93c5fd) !important; color: #0b1220 !important; border: 1px solid rgba(255,255,255,0.35) !important; box-shadow: 0 6px 18px rgba(147,197,253,0.35) !important; }

        /* Подсветка типа пары (поверх фонового стиля) */
        .lesson-practice { box-shadow: inset 0 0 0 9999px rgba(59,130,246,0.30); border-color: rgba(59,130,246,0.55) !important; }
        .lesson-lecture { box-shadow: inset 0 0 0 9999px rgba(125,211,252,0.24); border-color: rgba(125,211,252,0.55) !important; }
        .lesson-practice .h2, .lesson-lecture .h2 { color: #fff; }

        /* Фоновые иконки (много маленьких, по всей ширине) */
        .ny-card-bgicon { position: absolute; inset: 0; pointer-events: none; z-index: 0; }
        .ny-card-bgicon i { position: absolute; color: #e0f2fe; opacity: 0.06; filter: drop-shadow(0 1px 4px rgba(14,165,233,0.18)); transform: rotate(-10deg); }
        .card__inner { position: relative; overflow: hidden; }
        .ny-type-tint { position: absolute; inset: 0; pointer-events: none; z-index: 0; border-radius: 16px; }
    </style>
</head>
<body>
    <!-- Фон страницы из базовой темы -->
    <div class="page-bg" aria-hidden="true">
        <div class="blob blob-a"></div>
        <div class="blob blob-b"></div>
        <div class="overlay"></div>
    </div>

    <!-- Новогодние декоративные слои -->
    <div class="ny-garland-image" aria-hidden="true"></div>
    <div class="ny-garlands" aria-hidden="true">
        <div class="garland-wire"></div>
        <div class="garland-row" id="garlandRow1"></div>
    </div>
    <div class="ny-snowfield" id="nySnowfield" aria-hidden="true"></div>
    <img src="assets/images/snowman_left.png" alt="" class="ny-snowman left" onerror="this.remove()">
    <img src="assets/images/snowman_right.png" alt="" class="ny-snowman right" onerror="this.remove()">

    <header class="header px">
        <div class="container header__row">
            <div class="header__row" style="align-items: center; gap: 0.75rem;">
                <?php if (file_exists('assets/images/ImsitID_png_logo.png')): ?>
                    <img src="assets/images/ImsitID_png_logo.png" alt="ImsitID Logo" style="height: 40px; width: auto; display: block;">
                <?php else: ?>
                    <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #38bdf8, #a78bfa); border-radius: 6px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 16px;">ID</div>
                <?php endif; ?>
                <div class="subtle"><b>imsitID</b> - Новогоднее расписание</div>
            </div>
            <div class="header__row">
                <?php if ($currentUser): ?>
                    <a href="client_dashboard.php" class="btn">Профиль</a>
                <?php else: ?>
                    <a href="https://t.me/cowgivesmilk" target="_blank" class="login">Помощь</a>
                <?php endif; ?>
                <button id="refreshBtn" class="btn">Обновить</button>
            </div>
        </div>
    </header>

    <main class="px" style="padding-bottom: 6rem;">
        <section class="container space-y-6">
            <div class="card" id="topCard">
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
                            <div class="small" id="contextLine">
                                <?php if ($viewMode === 'teacher' && $teacherInfo): ?>
                                    Преподаватель • <?php echo $currentWeek; ?> неделя • <?php echo $dayNames[$currentDay]; ?>
                                <?php elseif ($viewMode === 'group' && $userGroup): ?>
                                    <?php echo $currentWeek; ?> неделя • <?php echo $dayNames[$currentDay]; ?>
                                <?php else: ?>
                                    Нажмите на кнопку ниже для выбора
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="subtle">Обновлено: <span id="updatedAtDup"><?php echo date('H:i:s'); ?></span></div>
                    </div>

                    <?php if ($userGroup || $selectedTeacher): ?>
                    <div class="mt-5 grid grid--two" data-cards>
                        <?php if ($currentLesson): ?>
                        <?php 
                            $nowType = isset($currentLesson['subject_name']) ? detectLessonType($currentLesson['subject_name']) : '';
                        ?>
                        <div id="nowCard" class="card card__inner<?php echo $nowType==='practice' ? ' lesson-practice' : ($nowType==='lecture' ? ' lesson-lecture' : ''); ?>">
                            <div class="header__row">
                                <span class="btn btn--emerald">Сейчас</span>
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
                                <div class="progress__meta"><span id="nowProgressLabel"><i class="fas fa-clock" style="margin-right: 4px;"></i><?php echo $remainingLabel; ?></span><span id="nowRemaining"></span></div>
                            </div>
                            <div class="ny-card-bgicon" aria-hidden="true"></div>
                            <?php if ($nowType==='practice'): ?>
                                <div class="ny-type-tint" style="background: linear-gradient(135deg, rgba(59,130,246,0.32), rgba(59,130,246,0.12));"></div>
                            <?php elseif ($nowType==='lecture'): ?>
                                <div class="ny-type-tint" style="background: linear-gradient(135deg, rgba(125,211,252,0.28), rgba(125,211,252,0.12));"></div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <?php 
                            $nextType = ($nextLesson && isset($nextLesson['subject_name'])) ? detectLessonType($nextLesson['subject_name']) : '';
                        ?>
                        <div id="nextCard" class="card card__inner<?php echo $nextType==='practice' ? ' lesson-practice' : ($nextType==='lecture' ? ' lesson-lecture' : ''); ?>">
                            <div class="header__row">
                                <span class="btn btn--sky">Следующая</span>
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
                            <div class="ny-card-bgicon" aria-hidden="true"></div>
                            <?php if ($nextType==='practice'): ?>
                                <div class="ny-type-tint" style="background: linear-gradient(135deg, rgba(59,130,246,0.20), rgba(59,130,246,0.08));"></div>
                            <?php elseif ($nextType==='lecture'): ?>
                                <div class="ny-type-tint" style="background: linear-gradient(135deg, rgba(125,211,252,0.20), rgba(125,211,252,0.08));"></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="mt-5" style="text-align:center; display:flex; flex-direction:column; gap:1rem; align-items:center;">
                        <button onclick="showGroupSelectionModal()" class="btn" style="padding:0.75rem 1.5rem; font-size:1rem;">Выбрать группу</button>
                        <button onclick="showTeacherSelectionModal()" class="btn" style="padding:0.75rem 1.5rem; font-size:1rem; background: linear-gradient(135deg, #a855f7, #ec4899);">Выбрать преподавателя</button>
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
                    <?php if (!empty($daySchedule)): ?>
                        <?php foreach ($daySchedule as $index => $lesson): ?>
                            <?php
                                $t = isset($lesson['subject_name']) ? detectLessonType($lesson['subject_name']) : '';
                                $typeClass = $t==='practice' ? ' lesson-practice' : ($t==='lecture' ? ' lesson-lecture' : '');
                            ?>
                            <article class="card card--hover card__inner lesson-card<?php echo $typeClass; ?>" style="
                                position: relative;
                                background: linear-gradient(135deg, rgba(255,255,255,0.12) 0%, rgba(255,255,255,0.06) 100%);
                                border: 1px solid rgba(255,255,255,0.18);
                                border-radius: 16px;
                                backdrop-filter: blur(10px);
                                -webkit-backdrop-filter: blur(10px);
                                box-shadow: 0 4px 20px rgba(0,0,0,0.12);
                                transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
                                overflow: hidden;
                            " onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 30px rgba(0,0,0,0.18)'; this.style.borderColor='rgba(255,255,255,0.28)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 20px rgba(0,0,0,0.12)'; this.style.borderColor='rgba(255,255,255,0.18)'">
                                <!-- Светящийся эффект при наведении -->
                                <div style="
                                    position: absolute;
                                    top: 0;
                                    left: 0;
                                    right: 0;
                                    bottom: 0;
                                    background: linear-gradient(135deg, rgba(255,255,255,0.12) 0%, transparent 60%);
                                    opacity: 0;
                                    transition: opacity 0.3s ease;
                                    pointer-events: none;
                                    border-radius: 16px;
                                " class="glow-overlay"></div>
                                
                                <div style="min-width:0; padding-left: 12px; position: relative; z-index: 1;">
                                    <div class="small muted" style="
                                        color: rgba(255,255,255,0.8);
                                        font-weight: 500;
                                        letter-spacing: 0.5px;
                                        text-transform: uppercase;
                                        font-size: 0.75rem;
                                    "><?php echo $lesson['lesson_number']; ?> пара • <?php echo substr($lesson['start_time'], 0, 5); ?>–<?php echo substr($lesson['end_time'], 0, 5); ?></div>
                                    <h3 class="h2" style="
                                        margin-top:0.5rem; 
                                        line-height:1.3; 
                                        display:-webkit-box; 
                                        -webkit-line-clamp:2; 
                                        -webkit-box-orient:vertical; 
                                        overflow:hidden;
                                        color: #fff;
                                        text-shadow: 0 1px 2px rgba(0,0,0,0.3);
                                        font-weight: 600;
                                    ">
                                        <?php echo htmlspecialchars($lesson['subject_name']); ?>
                                    </h3>
                                    <div class="lesson-meta" style="
                                        margin-top: 0.75rem;
                                        display: flex;
                                        align-items: center;
                                        gap: 0.75rem;
                                        flex-wrap: wrap;
                                    ">
                                        <span style="
                                            background: rgba(255,255,255,0.18);
                                            padding: 0.25rem 0.75rem;
                                            border-radius: 12px;
                                            font-size: 0.8rem;
                                            font-weight: 500;
                                            color: #fff;
                                            border: 1px solid rgba(255,255,255,0.22);
                                        "><?php echo htmlspecialchars($lesson['room_number']); ?></span>
                                        <span style="
                                            background: rgba(255,255,255,0.12);
                                            padding: 0.25rem 0.75rem;
                                            border-radius: 12px;
                                            font-size: 0.8rem;
                                            font-weight: 500;
                                            color: rgba(255,255,255,0.95);
                                            border: 1px solid rgba(255,255,255,0.18);
                                        ">
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
                                <div class="ny-card-bgicon" aria-hidden="true"></div>
                                <?php if ($t === 'practice'): ?>
                                    <div class="ny-type-tint" style="background: linear-gradient(135deg, rgba(59,130,246,0.32), rgba(59,130,246,0.12));"></div>
                                <?php elseif ($t === 'lecture'): ?>
                                    <div class="ny-type-tint" style="background: linear-gradient(135deg, rgba(125,211,252,0.28), rgba(125,211,252,0.12));"></div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div id="emptyState" class="card card__inner" style="
                            text-align:center;
                            background: linear-gradient(135deg, rgba(255,255,255,0.12) 0%, rgba(255,255,255,0.06) 100%);
                            border: 1px solid rgba(255,255,255,0.18);
                            border-radius: 16px;
                            backdrop-filter: blur(10px);
                            -webkit-backdrop-filter: blur(10px);
                            box-shadow: 0 4px 20px rgba(0,0,0,0.12);
                        ">
                            <div class="mb-3" style="
                                margin-left:auto;
                                margin-right:auto;
                                width:3rem;
                                height:3rem;
                                border-radius:1rem;
                                display:grid;
                                place-items:center;
                                background:linear-gradient(135deg, rgba(255,255,255,0.22), rgba(255,255,255,0.12));
                                border: 1px solid rgba(255,255,255,0.22);
                                font-size: 1.5rem;
                            ">☕</div>
                            <p class="h2" style="font-size:1.1rem; color: #fff; text-shadow: 0 1px 2px rgba(0,0,0,0.3);">Сегодня пар нет</p>
                            <p class="small" style="color: rgba(255,255,255,0.8);">Отдохните или выберите другой день.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
            <?php endif; ?>
        </section>
    </main>

    <div class="switch-group">
        <button id="settingsBtn" class="btn">Настройки</button>
    </div>


    <!-- модалка выбора группы -->
    <div id="groupSelectionModal" style="display: none;">
        <div class="modal-card">
            <div style="text-align:center;" class="mb-4">
                <div style="width:4rem;height:4rem;margin:0 auto 1rem;border-radius:9999px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg, rgba(99,102,241,0.7), rgba(217,70,239,0.7));color:#fff;">👥</div>
                <h2 class="h2" style="margin:0 0 0.5rem;">Выберите группу</h2>
                <p class="small">Выбор сохраняется в течение 30 дней</p>
            </div>
            <div style="position: relative; margin-bottom: 0.5rem; display:flex; justify-content: space-between; gap:0.5rem; align-items:center;">
                <input type="text" id="groupSearch" placeholder="Поиск группы..." style="flex:1; padding: 0.75rem; border: 1px solid rgba(255,255,255,0.2); border-radius: 0.5rem; background: rgba(255,255,255,0.1); color: white; font-size: 0.875rem;" onkeyup="filterGroups()">
                <button type="button" class="btn" style="padding:0.6rem 0.8rem; font-size:0.8rem; white-space:nowrap;" onclick="switchToTeacherFromGroup()">Препод</button>
            </div>
            <div id="groupsList" class="space-y-3" style="max-height: 300px; overflow-y: auto;">
                <?php if (!empty($availableGroups)): ?>
                    <?php foreach ($availableGroups as $group): ?>
                        <button onclick="selectGroup('<?php echo htmlspecialchars($group); ?>')" class="group-btn group-item" data-name="<?php echo htmlspecialchars($group); ?>">
                            <div style="display:flex; align-items:center; gap:0.75rem; width:100%;">
                                <div class="group-icon" style="background: rgba(59,130,246,0.2);"><span style="color:#93c5fd;font-weight:700;">👥</span></div>
                                <div style="min-width:0;">
                                    <div style="color:#fff;font-weight:600;" class="group-name"><?php echo htmlspecialchars($group); ?></div>
                                    <div class="small" style="opacity:0.7;"><?php echo htmlspecialchars($group); ?></div>
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
        <div class="modal-card">
            <div style="text-align:center;" class="mb-4">
                <div style="width:4rem;height:4rem;margin:0 auto 1rem;border-radius:9999px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg, rgba(99,102,241,0.7), rgba(217,70,239,0.7));color:#fff;">⚙️</div>
                <h2 class="h2" style="margin:0 0 0.5rem;">Настройки</h2>
                <p class="small">Настройте отображение расписания</p>
            </div>
            
            <div class="space-y-3">
                <!-- Переключение дизайна -->
                <div class="group-btn" style="cursor:default;">
                    <div style="display:flex; align-items:center; gap:0.75rem;">
                        <div class="group-icon" style="background: rgba(34,197,94,0.2);"><span style="color:#86efac;font-weight:700;">🎨</span></div>
                        <div style="flex:1;">
                            <div style="color:#fff;font-weight:600;">Дизайн</div>
                            <div class="small">Раздел скоро появится</div>
                        </div>
                        <button class="btn" disabled title="Раздел скоро появится" style="padding:0.5rem 1rem; font-size:0.75rem; opacity:0.6; cursor:not-allowed;">Скоро</button>
                    </div>
                </div>

                <!-- Выбор группы -->
                <div class="group-btn" style="cursor:default;">
                    <div style="display:flex; align-items:center; gap:0.75rem;">
                        <div class="group-icon" style="background: rgba(59,130,246,0.2);"><span style="color:#93c5fd;font-weight:700;">👥</span></div>
                        <div style="flex:1;">
                            <div style="color:#fff;font-weight:600;">Группа</div>
                            <div class="small">Текущая: <?php echo $userGroup ?: 'Не выбрана'; ?></div>
                        </div>
                        <button onclick="showGroupSelectionModal()" class="btn" style="padding:0.5rem 1rem; font-size:0.75rem;">Сменить</button>
                    </div>
                </div>

                <!-- Выбор преподавателя -->
                <div class="group-btn" style="cursor:default;">
                    <div style="display:flex; align-items:center; gap:0.75rem;">
                        <div class="group-icon" style="background: rgba(168,85,247,0.2);"><span style="color:#d8b4fe;font-weight:700;">👨‍🏫</span></div>
                        <div style="flex:1;">
                            <div style="color:#fff;font-weight:600;">Преподаватель</div>
                            <div class="small">Текущий: <?php echo $selectedTeacher ?: 'Не выбран'; ?></div>
                        </div>
                        <button onclick="showTeacherSelectionModal()" class="btn" style="padding:0.5rem 1rem; font-size:0.75rem;">Выбрать</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- модалка выбора преподавателя -->
    <div id="teacherSelectionModal" style="display: none;">
        <div class="modal-card">
            <div style="text-align:center;" class="mb-4">
                <div style="width:4rem;height:4rem;margin:0 auto 1rem;border-radius:9999px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg, rgba(168,85,247,0.7), rgba(236,72,153,0.7));color:#fff;">👨‍🏫</div>
                <h2 class="h2" style="margin:0 0 0.5rem;">Выберите преподавателя</h2>
                <p class="small">Просмотр расписания преподавателя</p>
            </div>
            <div style="position: relative; margin-bottom: 0.5rem; display:flex; justify-content: space-between; gap:0.5rem; align-items:center;">
                <input type="text" id="teacherSearch" placeholder="Поиск преподавателя..." style="flex:1; padding: 0.75rem; border: 1px solid rgba(255,255,255,0.2); border-radius: 0.5rem; background: rgba(255,255,255,0.1); color: white; font-size: 0.875rem;" onkeyup="filterTeachers()">
                <button type="button" class="btn" style="padding:0.6rem 0.8rem; font-size:0.8rem; white-space:nowrap;" onclick="switchToGroupFromTeacher()">Группы</button>
            </div>
            <div class="space-y-3" id="teachersList" style="max-height: 300px; overflow-y: auto;">
                <?php if (!empty($availableTeachers)): ?>
                    <?php foreach ($availableTeachers as $teacher): ?>
                        <button onclick="selectTeacher('<?php echo htmlspecialchars($teacher); ?>')" class="group-btn teacher-item" data-name="<?php echo htmlspecialchars($teacher); ?>">
                            <div style="display:flex; align-items:center; gap:0.75rem; width:100%;">
                                <div class="group-icon" style="background: rgba(168,85,247,0.2);"><span style="color:#d8b4fe;font-weight:700;">👨‍🏫</span></div>
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
            daySchedule: <?php echo json_encode($daySchedule, JSON_UNESCAPED_UNICODE); ?>
        };
    </script>
    <script src="assets/js/schedule_js.js?v=<?php echo file_exists('cache_version.txt') ? file_get_contents('cache_version.txt') : time(); ?>"></script>
    
    <script>
        // Избранное и поиск (как в основной версии)
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

        // Новогодние элементы: гирлянды и снег
        function bootstrapGarlands() {
            const colors = ['red','green','blue','yellow','purple','cyan','white'];
            const row1 = document.getElementById('garlandRow1');
            if (!row1) return;

            // Горизонтальная верхняя гирлянда
            const bulbsPerRow = Math.min(36, Math.max(20, Math.floor(window.innerWidth / 44)));
            row1.innerHTML = '';
            for (let i = 0; i < bulbsPerRow; i++) {
                const b = document.createElement('div');
                const c = colors[i % colors.length];
                b.className = 'bulb ' + c;
                b.style.marginTop = (10 + Math.sin(i/2) * 10) + 'px';
                b.style.animationDelay = (Math.random()*1.8).toFixed(2) + 's';
                row1.appendChild(b);
            }

        }

        function bootstrapSnow() {
            const field = document.getElementById('nySnowfield');
            if (!field) return;
            field.innerHTML = '';
            const flakes = Math.min(60, Math.max(24, Math.floor(window.innerWidth / 20)));
            for (let i = 0; i < flakes; i++) {
                const f = document.createElement('span');
                f.className = 'ny-flake';
                f.textContent = '❄';
                const size = (Math.random()*0.9 + 0.6) * (window.innerWidth < 420 ? 12 : 14);
                f.style.left = (Math.random() * 100) + 'vw';
                f.style.fontSize = size.toFixed(1) + 'px';
                const fall = (Math.random()*16 + 12);
                const sway = (Math.random()*2 + 1);
                f.style.animationDuration = fall.toFixed(1) + 's, ' + (6 + sway).toFixed(1) + 's';
                // отрицательная задержка, чтобы хлопья уже были распределены по высоте
                const offset = Math.random() * fall; // сек
                const offset2 = Math.random() * (6 + sway);
                f.style.animationDelay = (-offset).toFixed(2) + 's, ' + (-offset2).toFixed(2) + 's';
                field.appendChild(f);
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            bootstrapGarlands();
            bootstrapSnow();
        });
        window.addEventListener('resize', () => {
            bootstrapGarlands();
        });
        
        // Фоновые FA-иконки + ПОДСВЕТКА ТИПА ПАРЫ (устойчиво к перерисовкам)
        function addCardBgIcons(root) {
            try {
                const scope = root || document;
                const faSets = [
                    ['fas','fa-snowflake'],
                    ['fas','fa-candy-cane'],
                    ['fas','fa-gift'],
                    ['fas','fa-tree']
                ];
                const targets = [];
                const nowInner = document.querySelector('#nowCard .card__inner');
                if (nowInner) targets.push(nowInner);
                const nextInner = document.querySelector('#nextCard .card__inner');
                if (nextInner) targets.push(nextInner);
                scope.querySelectorAll('article.lesson-card').forEach(el => targets.push(el));
                targets.forEach((host, idx) => {
                    const container = host.classList.contains('card__inner') ? host : (host.querySelector('.card__inner') || host);
                    if (!container) return;
                    
                    // ПОДСВЕТКА ТИПА ПАРЫ - проверяем заголовок и добавляем классы/тинт
                    const titleEl = container.querySelector('h3, .h2');
                    if (titleEl) {
                        const titleText = titleEl.textContent || '';
                        const isPractice = /^пр\.?/i.test(titleText.trim());
                        const isLecture = /^л\.?/i.test(titleText.trim());
                        
                        if (isPractice) {
                            container.classList.add('lesson-practice');
                            if (!container.querySelector('.ny-type-tint')) {
                                const tint = document.createElement('div');
                                tint.className = 'ny-type-tint';
                                tint.style.cssText = 'background: linear-gradient(135deg, rgba(59,130,246,0.32), rgba(59,130,246,0.12)); position: absolute; inset: 0; pointer-events: none; z-index: 0; border-radius: 16px;';
                                container.appendChild(tint);
                            }
                        } else if (isLecture) {
                            container.classList.add('lesson-lecture');
                            if (!container.querySelector('.ny-type-tint')) {
                                const tint = document.createElement('div');
                                tint.className = 'ny-type-tint';
                                tint.style.cssText = 'background: linear-gradient(135deg, rgba(125,211,252,0.28), rgba(125,211,252,0.12)); position: absolute; inset: 0; pointer-events: none; z-index: 0; border-radius: 16px;';
                                container.appendChild(tint);
                            }
                        }
                    }
                    
                    // если уже есть фон — не трогаем, чтобы не плодить мутации
                    if (!container.querySelector('.ny-card-bgicon')) {
                        const wrap = document.createElement('div');
                        wrap.className = 'ny-card-bgicon';
                        // хаотичное распределение 6 иконок по площади карточки
                        const count = 6;
                        for (let i = 0; i < count; i++) {
                            const iEl = document.createElement('i');
                            const set = faSets[(idx + i) % faSets.length];
                            iEl.className = set.join(' ');
                            const size = 22 + Math.random()*12; // 22-34px
                            iEl.style.fontSize = size.toFixed(0) + 'px';
                            const left = 6 + Math.random()*88; // проценты с небольшими полями
                            const top = 6 + Math.random()*88;
                            const rot = -18 + Math.random()*36; // -18..18deg
                            iEl.style.left = left.toFixed(2) + '%';
                            iEl.style.top = top.toFixed(2) + '%';
                            iEl.style.transform = `rotate(${rot.toFixed(1)}deg)`;
                            wrap.appendChild(iEl);
                        }
                        container.appendChild(wrap);
                    }
                });
            } catch (e) {}
        }
        document.addEventListener('DOMContentLoaded', function(){
            // Инициализация фоновых иконок после загрузки FA (без жёсткого ожидания SRI)
            const boot = () => addCardBgIcons(document);
            setTimeout(boot, 50);
            let nyBgIconRaf = 0;
            const scheduleAdd = (root) => {
                if (nyBgIconRaf) return;
                nyBgIconRaf = requestAnimationFrame(() => {
                    nyBgIconRaf = 0;
                    addCardBgIcons(root);
                });
            };
            const list = document.getElementById('list');
            if (list) {
                const obs = new MutationObserver((muts) => {
                    // игнорируем добавления только фона
                    const hasRealChange = muts.some(m => !(m.addedNodes && Array.from(m.addedNodes).every(n => n.classList && n.classList.contains('ny-card-bgicon'))));
                    if (hasRealChange) scheduleAdd(list);
                });
                obs.observe(list, { childList: true, subtree: true });
            }
            const cardsWrap = document.querySelector('[data-cards]');
            if (cardsWrap) {
                const obs2 = new MutationObserver((muts) => {
                    const hasRealChange = muts.some(m => !(m.addedNodes && Array.from(m.addedNodes).every(n => n.classList && n.classList.contains('ny-card-bgicon'))));
                    if (hasRealChange) scheduleAdd(cardsWrap);
                });
                obs2.observe(cardsWrap, { childList: true, subtree: true });
            }
        });
    </script>
    
    <style>
        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
        
        /* Анимация свечения для блоков пар */
        .lesson-card:hover .glow-overlay {
            opacity: 1 !important;
        }
        
        /* Еле заметное свечение */
        .lesson-card:hover {
            box-shadow: 0 8px 30px rgba(0,0,0,0.18), 0 0 0 1px rgba(255,255,255,0.1);
        }
    </style>
    
    
</body>
</html>

<!-- ID_NY Version 3.1.0 -->


