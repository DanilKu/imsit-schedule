<?php
/*
                ██╗███╗   ███╗███████╗██╗████████╗    ██╗██████╗ 
                ██║████╗ ████║██╔════╝██║╚══██╔══╝    ██║██╔══██╗
                ██║██╔████╔██║███████╗██║   ██║       ██║██║  ██║
                ██║██║╚██╔╝██║╚════██║██║   ██║       ██║██║  ██║
                ██║██║ ╚═╝ ██║███████║██║   ██║       ██║██████╔╝
                ╚═╝╚═╝     ╚═╝╚══════╝╚═╝   ╚═╝       ╚═╝╚═════╝ 

    ╔══════════════════════════════════════════════════════════════════════════════╗
    ║                               Version 3.4                                    ║
    ║                                                                              ║
    ║     Расписание для всех групп и преподавателей                               ║
    ║     Адаптивный дизайн для мобильных устройств                                ║
    ║     Добавлены избранные группы и преподаватели                               ║
    ║     Добавлена поддержка всех групп и преподавателей СПО                      ║
    ║     Добавлено примерное окончание текущей пары с округлением до 5 минут      ║
    ╚══════════════════════════════════════════════════════════════════════════════╝
*/

// мур
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

// не надо
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

    // получение расписание на все недели и дни (для быстрого переключения)
    $allSchedules = []; // [week][day] = расписание
    $currentLesson = null;
    $nextLesson = null;
    $teacherInfo = null;
    
    if ($viewMode === 'group' && $userGroup) {
        // Загружаем расписание для всех недель (1 и 2) и всех дней (1-6)
        for ($week = 1; $week <= 2; $week++) {
            $allSchedules[$week] = [];
            for ($day = 1; $day <= 6; $day++) {
                $allSchedules[$week][$day] = $scheduleManager->getSchedule($userGroup, $week, $day);
            }
        }
        $currentLesson = $scheduleManager->getCurrentLesson($userGroup);
        $nextLesson = $scheduleManager->getNextLesson($userGroup);
    } elseif ($viewMode === 'teacher' && $selectedTeacher) {
        // Используем имя преподавателя напрямую из schedule_all
        $teacherInfo = ['full_name' => $selectedTeacher, 'short_name' => $selectedTeacher];
        // Загружаем расписание для всех недель (1 и 2) и всех дней (1-6)
        for ($week = 1; $week <= 2; $week++) {
            $allSchedules[$week] = [];
            for ($day = 1; $day <= 6; $day++) {
                $allSchedules[$week][$day] = $scheduleManager->getTeacherSchedule($selectedTeacher, $week, $day);
            }
        }
        $currentLesson = $scheduleManager->getTeacherCurrentLesson($selectedTeacher);
        $nextLesson = $scheduleManager->getTeacherNextLesson($selectedTeacher);
    }
    
    // Для обратной совместимости сохраняем текущее расписание недели
    $weekSchedule = $allSchedules[$currentWeek] ?? [];
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
<html lang="ru" style="margin:0;padding:0;background:#0B1220;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#0B1220">
    <meta name="msapplication-navbutton-color" content="#0B1220">
    <style>
      html, body { margin: 0; padding: 0; background: linear-gradient(180deg, #0B1220 0%, #353535 100%) !important; background-repeat: no-repeat !important; background-attachment: fixed !important; background-size: 100% 100% !important; background-position: center center !important; min-height: 100%; min-height: 100vh; min-height: -webkit-fill-available; -webkit-tap-highlight-color: transparent; -webkit-overflow-scrolling: touch; }
      html { overflow-x: hidden; }
      body { overflow-x: hidden; }
      #ios-bg { position: fixed; top: 0; left: 0; right: 0; bottom: 0; width: 100%; height: 100%; min-height: 100vh; min-height: -webkit-fill-available; background: linear-gradient(180deg, #0B1220 0%, #353535 100%); background-repeat: no-repeat; background-attachment: fixed; background-size: 100% 100%; background-position: center center; z-index: -9999; pointer-events: none; }
    </style>
    <script>
      (function(){ var g = 'linear-gradient(180deg, #0B1220 0%, #353535 100%)'; document.documentElement.style.background = g; document.documentElement.style.backgroundColor = '#0B1220'; if(document.body){ document.body.style.background = g; document.body.style.backgroundColor = '#0B1220'; } })();
    </script>
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
    <link rel="icon" href="assets/icons/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="assets/icons/favicon-32x32.png" sizes="32x32" type="image/png">
    <link rel="icon" href="assets/icons/favicon-16x16.png" sizes="16x16" type="image/png">
    <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
    <title>ImsitID - Расписание для всех студентов и преподавателей Академии ИМСИТ</title>
    <meta name="description" content="Расписание для всех студентов и преподавателей Академии ИМСИТ. ">
    <meta name="keywords" content="Расписание, Академия ИМСИТ, Расписание для всех студентов и преподавателей Академии ИМСИТ, imsitshop, imsitid, imsit.shop, imsit.shop/shedule2, imsit.shop/shedule2.php, imsitid.ru, imsitid.com, imsitid.net, imsitid.org, imsitid.ru/schedule, imsitid.com/schedule, imsitid.net/schedule, imsitid.org/schedule, imsit, id imsit, imsitid, эос, эос имсит, eios имсит, краснодар, краснодар имсит, краснодар имсит расписание">
    <meta name="author" content="ImsitID">
    <meta name="robots" content="index, follow">
    <meta name="googlebot" content="index, follow">
    <meta name="bingbot" content="index, follow">
    <meta name="google" content="notranslate">
    <meta name="google" content="notranslate">
    <link rel="canonical" href="https://imsit.shop/">
    <link rel="preload" as="style" href="assets/css/schedule_style.css?v=<?php echo file_exists('cache_version.txt') ? file_get_contents('cache_version.txt') : time(); ?>"/>
    <link rel="stylesheet" href="assets/css/schedule_style.css?v=<?php echo file_exists('cache_version.txt') ? file_get_contents('cache_version.txt') : time(); ?>"/>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/main.css?v=<?php echo time(); ?>">
    <style>
      /* Переопределяем height:100% из schedule_style/main — чтобы скроллилось тело документа и работал bounce */
      html, body { height: auto !important; min-height: 100vh !important; min-height: -webkit-fill-available !important; overscroll-behavior: auto !important; -webkit-overflow-scrolling: touch; }
    </style>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <style id="page-bg-override">html,body,body.g-class{background:linear-gradient(180deg,#0B1220 0%,#353535 100%)!important;background-repeat:no-repeat!important;background-attachment:fixed!important;background-size:100% 100%!important}body.g-class{position:relative!important}#ios-bg{background:linear-gradient(180deg,#0B1220 0%,#353535 100%)!important;background-attachment:fixed!important;background-size:100% 100%!important}@media (max-width:768px){html,body,body.g-class{background-attachment:scroll!important;background-size:100% 100%!important}#ios-bg{position:absolute!important;top:0!important;left:0!important;right:0!important;bottom:auto!important;width:100%!important;min-height:100%!important;height:100%!important;background-attachment:scroll!important;background-size:100% 100%!important}}</style>
    <style id="constellation-styles">#bgEffect{position:fixed;inset:0;width:100%;height:100%;z-index:0;pointer-events:none;overflow:hidden}#bgEffect .bg-dot{position:absolute;width:5px;height:5px;border-radius:50%;background:rgba(255,255,255,0.9);box-shadow:0 0 10px rgba(255,255,255,0.4);transform:translate(-50%,-50%);animation:constellation-float 10s ease-in-out infinite}#bgEffect .bg-dot-break{animation:constellation-float 10s ease-in-out infinite,constellation-break 7s ease-in-out infinite}#bgEffect .bg-strip{position:absolute;height:2px;min-width:50px;background:linear-gradient(90deg,transparent,rgba(255,255,255,0.5),transparent);transform-origin:left center;animation:constellation-pulse 4s ease-in-out infinite}@keyframes constellation-float{0%,100%{transform:translate(-50%,-50%) translate(0,0)}25%{transform:translate(-50%,-50%) translate(6px,-8px)}50%{transform:translate(-50%,-50%) translate(-5px,6px)}75%{transform:translate(-50%,-50%) translate(8px,5px)}}@keyframes constellation-break{0%,100%{transform:translate(-50%,-50%) translate(0,0) scale(1)}20%{transform:translate(-50%,-50%) translate(18px,-22px) scale(0.9)}50%{transform:translate(-50%,-50%) translate(-15px,20px) scale(1.1)}80%{transform:translate(-50%,-50%) translate(12px,15px) scale(0.95)}}@keyframes constellation-pulse{0%,100%{opacity:0.5}50%{opacity:0.9}}</style>
</head>
<body class="g-class" style="margin:0;padding:0;background:linear-gradient(180deg,#0B1220 0%,#353535 100%);background-color:#0B1220;position:relative;">
    <div id="ios-bg" style="position:fixed;top:0;left:0;right:0;bottom:0;width:100%;height:100%;min-height:100vh;background:linear-gradient(180deg,#0B1220 0%,#353535 100%);background-repeat:no-repeat;background-attachment:fixed;background-size:100% 100%;background-position:center center;z-index:-9999;pointer-events:none;"></div>
    <div id="bgEffect" class="constellation-bg" aria-hidden="true" style="position:fixed;inset:0;width:100%;height:100%;z-index:0;pointer-events:none;overflow:hidden;">
      <div id="bgEffectDots" style="position:absolute;inset:0;width:100%;height:100%;"></div>
      <div id="bgEffectStrips" style="position:absolute;inset:0;width:100%;height:100%;"></div>
      <svg id="bgEffectLines" style="position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;"></svg>
    </div>
    <div class="FullScreen" style="box-sizing: border-box; position:relative; z-index:1; isolation: isolate;">
        <div class="On-header">
            <!-- Header -->
            <div class="Header">
                <div id="headerContainer" class="Header-container">
                    <div class="search-overlay"></div>
                    <!-- Default Header View -->
                    <div id="defaultHeader" class="Def-Header">
                        <div class="Def-Header-Container">
                            <div class="Def-Header-Text">
                                imsitID - Расписание
                            </div>
                        </div>
                        <div class="Def-Header-Icons">
                            <button id="searchBtn" class="Def-Search-Button">
                                <i class="fas fa-search" style="font-size: 20px; color: white; display: inline-block; line-height: 1;"></i>
                            </button>
                            <button id="refreshBtn" class="Def-Search-Button">
                                <i class="fas fa-sync-alt" style="font-size: 20px; color: white; display: inline-block; line-height: 1;"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Search Header View -->
                    <div id="searchHeader" class="Ser-Header" style="display: none;">
                        <input type="text" id="globalSearch" placeholder="Поиск..." autocomplete="off" class="Ser-Header-Input" style="flex: 1;">
                        <div class="Def-Header-Icons">
                            <button id="closeSearchBtn" style="cursor: pointer; transition-property: opacity; transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1); transition-duration: 150ms; opacity: 0.8; background: transparent; border: none; padding: 0;">
                                <i class="fas fa-times" style="height: 22px; width: 25px; font-size: 22px; color: white;"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Search Results -->
                    <div id="searchResults" class="Search-Results" style="background: rgba(0, 0, 0, 0.95); backdrop-filter: blur(22px); max-height: 500px; z-index: 1000; display: none;">
                        <!-- Results will be inserted here -->
                    </div>
                </div>
            </div>

            <!-- Баннер о закрытии проекта (видим по умолчанию; скрытие — сразу в скрипте ниже, не ждём DOMContentLoaded) -->
            <div id="projectClosureBanner" class="project-closure-banner" role="alert" style="margin:0 1rem 1rem;padding:1.25rem 1rem 1.25rem 1.25rem;background:linear-gradient(145deg,rgba(180,83,9,0.35) 0%,rgba(120,53,15,0.45) 100%);border:2px solid rgba(251,191,36,0.45);border-radius:16px;position:relative;box-shadow:0 8px 32px rgba(0,0,0,0.35);">
                <button type="button" id="projectClosureBannerClose" aria-label="Закрыть уведомление" onclick="var b=document.getElementById('projectClosureBanner');if(b)b.style.display='none';try{localStorage.setItem('idProjectClosureBannerDismissed','1');}catch(e){}" style="position:absolute;top:0.75rem;right:0.75rem;width:40px;height:40px;border:none;background:rgba(0,0,0,0.2);border-radius:10px;color:rgba(255,255,255,0.9);cursor:pointer;display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-times" style="font-size:18px;"></i>
                </button>
                <div style="padding-right:2.5rem;font-size:clamp(0.95rem,3.5vw,1.1rem);line-height:1.6;color:#fff;">
                    <div style="font-weight:700;font-size:clamp(1.05rem,4vw,1.25rem);margin-bottom:0.75rem;color:#fde68a;">
                        Расписание в imsitID больше не обновляется
                    </div>
                    <p style="margin:0 0 0.75rem 0;color:rgba(255,255,255,0.95);">
                        Мы завершаем проект в связи с разногласиями с администрацией академии IMSIT.
                    </p>
                    <p style="margin:0;color:rgba(255,255,255,0.88);">
                        Спасибо вам за то, что пользовались приложением в этом учебном году. Желаем успехов в учёбе и всего наилучшего!
                    </p>
                </div>
            </div>
            <script>
            (function () {
                var b = document.getElementById('projectClosureBanner');
                if (!b) return;
                try {
                    if (localStorage.getItem('idProjectClosureBannerDismissed') === '1') {
                        b.style.display = 'none';
                    }
                } catch (e) {}
            })();
            </script>

            <!-- Блок приглашения в Telegram-канал -->
            <div id="tgPromoBlock" class="tg-promo-block" style="display: flex; margin: 0 1rem 0.75rem; padding: 0.75rem 1rem; background: linear-gradient(135deg, rgba(0, 136, 204, 0.25) 0%, rgba(88, 101, 242, 0.2) 100%); border: 1px solid rgba(255,255,255,0.12); border-radius: 14px; position: relative; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                <a href="https://t.me/imsitID" target="_blank" rel="noopener noreferrer" style="flex: 1; min-width: 0; display: flex; align-items: center; gap: 0.5rem; color: #fff; text-decoration: none; font-size: 0.9rem;">
                    <i class="fab fa-telegram-plane" style="font-size: 1.35rem; color: #fff; line-height: 1; flex-shrink: 0;"></i>
                    <span>Нажмите, чтобы подписаться на наш канал <strong>imsitID</strong> в Telegram</span>
                </a>
                <button type="button" id="tgPromoClose" aria-label="Закрыть" style="flex-shrink: 0; width: 32px; height: 32px; border: none; background: rgba(255,255,255,0.1); border-radius: 8px; color: rgba(255,255,255,0.8); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.2s, color 0.2s;">
                    <i class="fas fa-times" style="font-size: 14px;"></i>
                </button>
            </div>

            <!-- Snow Container -->
            <!-- Content Area -->
            <div class="content-area" style="margin-top: -1.5rem;">
                <main class="px" style="padding: 0;">
        <section class="container space-y-6">
            <!-- Promo Block -->
            
            <div class="card">
                <div class="card__inner">
                    <div class="header__row">
                        <div class="header__row" style="gap:0.75rem; flex: 1; min-width: 0;">
                            <div class="h1" style="flex: 1; min-width: 0;">
                                <?php if ($viewMode === 'teacher' && $teacherInfo): ?>
                                    <?php echo htmlspecialchars($teacherInfo['full_name']); ?>
                                <?php elseif ($viewMode === 'group' && $userGroup): ?>
                                    <?php echo htmlspecialchars($userGroup); ?>
                                <?php else: ?>
                                    Выберите группу или преподавателя
                                <?php endif; ?>
                            </div>
                            <?php if ($userGroup || $selectedTeacher): ?>
                            <button id="favoriteBtn" class="favorite-button" onclick="toggleCurrentFavorite()" title="Добавить в избранное">
                                <i class="fas fa-star"></i>
                            </button>
                            <button id="shareBtn" class="share-button" onclick="shareSchedule()" title="Поделиться расписанием">
                                <i class="fas fa-share-alt"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($userGroup || $selectedTeacher): ?>
                    <!-- Уведомление о расписании -->
                    
                    <div class="mt-5" data-cards style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <?php if ($currentLesson): ?>
                        <div id="nowCard" class="card card__inner">
                            <div class="header__row" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                                <span class="btn btn--emerald" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.4rem 0.8rem; font-size: 0.8rem; border-radius: 12px; background: rgba(16, 185, 129, 0.2); border: 1px solid rgba(16, 185, 129, 0.3); color: #10b981;">
                                    <i class="fas fa-circle" style="font-size: 8px;"></i>Сейчас
                                </span>
                                <span id="nowTimeRange" class="small" style="color: rgba(255, 255, 255, 0.7); font-size: 0.8rem;">
                                    <i class="fas fa-clock" style="margin-right: 4px;"></i><?php echo substr($currentLesson['start_time'], 0, 5); ?>–<?php echo substr($currentLesson['end_time'], 0, 5); ?>
                                </span>
                            </div>
                            <div class="mt-4">
                                <div id="nowTitle" class="h2 truncate" style="color: #ffffff; font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem;">
                                    <?php echo htmlspecialchars($currentLesson['subject_name']); ?>
                                </div>
                                <div id="nowMeta" class="lesson-meta" style="color: rgba(255, 255, 255, 0.7); font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                                    <span style="display: inline-flex; align-items: center; gap: 0.25rem;">
                                        <i class="fas fa-door-open" style="font-size: 12px;"></i>
                                        <?php echo htmlspecialchars($currentLesson['room_number']); ?>
                                    </span>
                                    <span>•</span>
                                    <?php if ($viewMode === 'teacher' && isset($currentLesson['groups']) && is_array($currentLesson['groups']) && count($currentLesson['groups']) > 0): ?>
                                        <span style="display: inline-flex; align-items: center; gap: 0.25rem;">
                                            <i class="fas fa-users" style="font-size: 12px;"></i>
                                            <?php echo htmlspecialchars(implode(', ', $currentLesson['groups'])); ?>
                                        </span>
                                    <?php elseif ($viewMode === 'teacher' && isset($currentLesson['group_name']) && !empty(trim($currentLesson['group_name']))): ?>
                                        <span style="display: inline-flex; align-items: center; gap: 0.25rem;">
                                            <i class="fas fa-users" style="font-size: 12px;"></i>
                                            <?php echo htmlspecialchars($currentLesson['group_name']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="display: inline-flex; align-items: center; gap: 0.25rem;">
                                            <i class="fas fa-chalkboard-teacher" style="font-size: 12px;"></i>
                                            <?php echo htmlspecialchars($currentLesson['teacher_name']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="progress" style="height: 6px; background: rgba(255, 255, 255, 0.1); border-radius: 12px; overflow: hidden;">
                                    <div id="nowProgress" class="progress__bar" style="height: 100%; background: linear-gradient(90deg, #10b981, #059669); border-radius: 12px; transition: width 0.3s ease; width: <?php echo round($scheduleManager->getLessonProgress($currentLesson)); ?>%;"></div>
                                </div>
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
                                <div class="progress__meta" style="margin-top: 0.75rem; font-size: 0.75rem; color: rgba(255, 255, 255, 0.6);">
                                    <span id="nowProgressLabel">
                                        <i class="fas fa-hourglass-half" style="margin-right: 4px;"></i>до конца пары: <?php echo $remainingLabel; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div id="nextCard" class="card card__inner">
                            <div class="header__row" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                                <span class="btn btn--sky" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.4rem 0.8rem; font-size: 0.8rem; border-radius: 12px; background: rgba(59, 130, 246, 0.2); border: 1px solid rgba(59, 130, 246, 0.3); color: #60a5fa;">
                                    <i class="fas fa-arrow-right" style="font-size: 10px;"></i>Следующая
                                </span>
                                <span id="nextTimeRange" class="small" style="color: rgba(255, 255, 255, 0.7); font-size: 0.8rem;">
                                    <?php if ($nextLesson): ?>
                                        <i class="fas fa-clock" style="margin-right: 4px;"></i><?php echo substr($nextLesson['start_time'], 0, 5); ?>–<?php echo substr($nextLesson['end_time'], 0, 5); ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="mt-4">
                                <div id="nextTitle" class="h2 truncate" style="color: #ffffff; font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem;">
                                    <?php if ($nextLesson): ?>
                                        <?php echo htmlspecialchars($nextLesson['subject_name']); ?>
                                    <?php else: ?>
                                        Следующих пар нет
                                    <?php endif; ?>
                                </div>
                                <div id="nextMeta" class="lesson-meta" style="color: rgba(255, 255, 255, 0.7); font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                                    <?php if ($nextLesson): ?>
                                        <span style="display: inline-flex; align-items: center; gap: 0.25rem;">
                                            <i class="fas fa-door-open" style="font-size: 12px;"></i>
                                            <?php echo htmlspecialchars($nextLesson['room_number']); ?>
                                        </span>
                                        <span>•</span>
                                        <?php if ($viewMode === 'teacher' && isset($nextLesson['groups']) && is_array($nextLesson['groups']) && count($nextLesson['groups']) > 0): ?>
                                            <span style="display: inline-flex; align-items: center; gap: 0.25rem;">
                                                <i class="fas fa-users" style="font-size: 12px;"></i>
                                                <?php echo htmlspecialchars(implode(', ', $nextLesson['groups'])); ?>
                                            </span>
                                        <?php elseif ($viewMode === 'teacher' && isset($nextLesson['group_name']) && !empty(trim($nextLesson['group_name']))): ?>
                                            <span style="display: inline-flex; align-items: center; gap: 0.25rem;">
                                                <i class="fas fa-users" style="font-size: 12px;"></i>
                                                <?php echo htmlspecialchars($nextLesson['group_name']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="display: inline-flex; align-items: center; gap: 0.25rem;">
                                                <i class="fas fa-chalkboard-teacher" style="font-size: 12px;"></i>
                                                <?php echo htmlspecialchars($nextLesson['teacher_name']); ?>
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: rgba(255, 255, 255, 0.5);">
                                            <i class="fas fa-check-circle" style="margin-right: 4px;"></i>Расписание на сегодня завершено
                                        </span>
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
                                        margin-bottom: 0.3rem;
                                        display: flex;
                                        align-items: center;
                                        gap: 0.5rem;">
                                        <span style="display: inline-flex; align-items: center; gap: 0.25rem;">
                                            <i class="fas fa-clock" style="font-size: 10px;"></i>
                                            <?php echo substr($lesson['start_time'], 0, 5); ?>–<?php echo substr($lesson['end_time'], 0, 5); ?>
                                        </span>
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
                                            border: 1px solid rgba(255,255,255,0.2);
                                            display: inline-flex;
                                            align-items: center;
                                            gap: 0.25rem;">
                                            <i class="fas fa-door-open" style="font-size: 10px;"></i>
                                            <?php echo htmlspecialchars($lesson['room_number']); ?>
                                        </span>
                                        <span style=
                                            "background: rgba(255,255,255,0.1);
                                            padding: 0.2rem 0.5rem;
                                            border-radius: 12px;
                                            font-size: 0.7rem;
                                            font-weight: 500;
                                            color: rgba(255,255,255,0.9);
                                            border: 1px solid rgba(255,255,255,0.15);
                                            display: inline-flex;
                                            align-items: center;
                                            gap: 0.25rem;">
                                            <?php if ($viewMode === 'teacher' && isset($lesson['groups']) && is_array($lesson['groups']) && count($lesson['groups']) > 0): ?>
                                                <i class="fas fa-users" style="font-size: 10px;"></i>
                                                <?php echo htmlspecialchars(implode(', ', $lesson['groups'])); ?>
                                            <?php elseif ($viewMode === 'teacher' && isset($lesson['group_name']) && !empty(trim($lesson['group_name']))): ?>
                                                <i class="fas fa-users" style="font-size: 10px;"></i>
                                                <?php echo htmlspecialchars($lesson['group_name']); ?>
                                            <?php else: ?>
                                                <i class="fas fa-chalkboard-teacher" style="font-size: 10px;"></i>
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
            </div>
        </div>
    </div>

    <!-- модалка выбора группы -->
    <div id="groupSelectionModal" style="display: none;" onclick="if(event.target === this) { this.style.display = 'none'; }">
        <div class="modal-card" onclick="event.stopPropagation()" style="position: relative;">
            <!-- Крестик для закрытия -->
            <button onclick="document.getElementById('groupSelectionModal').style.display = 'none'" style="position: absolute; top: 1rem; right: 1rem; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); border-radius: 12px; width: 2rem; height: 2rem; display: flex; align-items: center; justify-content: center; color: #fff; cursor: pointer; transition: all 0.2s ease; z-index: 10;" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'"><i class="fas fa-times" style="font-size: 12px;"></i></button>
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
    <div id="settingsModal" style="display: none;" onclick="if(event.target === this) { this.style.display = 'none'; }">
        <div class="modal-card" onclick="event.stopPropagation()" style="position: relative;">
            <!-- Крестик для закрытия -->
            <button id="closeSettingsBtn" onclick="document.getElementById('settingsModal').style.display = 'none'" style="position: absolute; top: 1rem; right: 1rem; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); border-radius: 12px; width: 2rem; height: 2rem; display: flex; align-items: center; justify-content: center; color: #fff; cursor: pointer; transition: all 0.2s ease; z-index: 10;" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'"><i class="fas fa-times" style="font-size: 12px;"></i></button>
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
    <div id="teacherSelectionModal" style="display: none;" onclick="if(event.target === this) { this.style.display = 'none'; }">
        <div class="modal-card" onclick="event.stopPropagation()" style="position: relative;">
            <!-- Крестик для закрытия -->
            <button onclick="document.getElementById('teacherSelectionModal').style.display = 'none'" style="position: absolute; top: 1rem; right: 1rem; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); border-radius: 12px; width: 2rem; height: 2rem; display: flex; align-items: center; justify-content: center; color: #fff; cursor: pointer; transition: all 0.2s ease; z-index: 10;" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'"><i class="fas fa-times" style="font-size: 12px;"></i></button>
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
            allSchedules: <?php echo json_encode($allSchedules, JSON_UNESCAPED_UNICODE); ?>, // [week][day] = расписание
            availableGroups: <?php echo json_encode($availableGroups, JSON_UNESCAPED_UNICODE); ?>,
            availableTeachers: <?php echo json_encode($availableTeachers, JSON_UNESCAPED_UNICODE); ?>,
        };
    </script>
    <script src="assets/js/schedule_js.js?v=<?php echo file_exists('cache_version.txt') ? file_get_contents('cache_version.txt') : time(); ?>"></script>
    
    <script>
        // Работа с избранным через cookie (как в favorites.php)
        const FAVORITES_COOKIE_NAME = 'imsit_favorites';
        
        function getFavoritesFromCookie() {
            try {
                const cookies = document.cookie.split(';');
                for (let cookie of cookies) {
                    const [name, value] = cookie.trim().split('=');
                    if (name === FAVORITES_COOKIE_NAME) {
                        const decoded = decodeURIComponent(value);
                        return JSON.parse(decoded) || [];
                    }
                }
            } catch (e) {
                console.error('Ошибка чтения избранного:', e);
            }
            return [];
        }
        
        function setFavoritesToCookie(favorites) {
            try {
                const expires = new Date();
                expires.setTime(expires.getTime() + (365 * 24 * 60 * 60 * 1000)); // 1 год
                document.cookie = `${FAVORITES_COOKIE_NAME}=${encodeURIComponent(JSON.stringify(favorites))}; expires=${expires.toUTCString()}; path=/`;
            } catch (e) {
                console.error('Ошибка сохранения избранного:', e);
            }
        }
        
        function isFavorite(type, name) {
            const favorites = getFavoritesFromCookie();
            return favorites.some(fav => 
                fav.name === name && fav.type === type
            );
        }
        
        function addToFavorites(type, name) {
            const favorites = getFavoritesFromCookie();
            // Проверяем, нет ли уже такого элемента
            const exists = favorites.some(fav => 
                fav.name === name && fav.type === type
            );
            if (!exists) {
                favorites.unshift({ name: name, type: type });
                setFavoritesToCookie(favorites);
            }
        }
        
        function removeFromFavorites(type, name) {
            const favorites = getFavoritesFromCookie();
            const filtered = favorites.filter(fav => 
                !(fav.name === name && fav.type === type)
            );
            setFavoritesToCookie(filtered);
        }
        
        function toggleCurrentFavorite() {
            const bootstrap = window.SCHEDULE_BOOTSTRAP;
            if (!bootstrap) return;
            
            let type, name;
            if (bootstrap.viewMode === 'group' && bootstrap.group) {
                type = 'group';
                name = bootstrap.group;
            } else if (bootstrap.viewMode === 'teacher' && bootstrap.teacher) {
                type = 'teacher';
                name = bootstrap.teacher;
            } else {
                return;
            }
            
            const isFav = isFavorite(type, name);
            if (isFav) {
                removeFromFavorites(type, name);
            } else {
                addToFavorites(type, name);
            }
            
            updateFavoriteButton();
        }
        
        function updateFavoriteButton() {
            const bootstrap = window.SCHEDULE_BOOTSTRAP;
            if (!bootstrap) return;
            
            const favoriteBtn = document.getElementById('favoriteBtn');
            if (!favoriteBtn) return;
            
            let type, name;
            if (bootstrap.viewMode === 'group' && bootstrap.group) {
                type = 'group';
                name = bootstrap.group;
            } else if (bootstrap.viewMode === 'teacher' && bootstrap.teacher) {
                type = 'teacher';
                name = bootstrap.teacher;
            } else {
                return;
            }
            
            const isFav = isFavorite(type, name);
            const starIcon = favoriteBtn.querySelector('i');
            if (starIcon) {
                if (isFav) {
                    favoriteBtn.classList.add('active');
                    starIcon.style.color = '#ffd166';
                    favoriteBtn.title = 'Удалить из избранного';
                } else {
                    favoriteBtn.classList.remove('active');
                    starIcon.style.color = 'rgba(255,255,255,0.6)';
                    favoriteBtn.title = 'Добавить в избранное';
                }
            }
        }
        
        // Старые функции для совместимости с модалками выбора
        function getFavorites(type) {
            const favorites = getFavoritesFromCookie();
            return favorites
                .filter(fav => fav.type === type)
                .map(fav => fav.name);
        }
        function setFavorites(type, list) {
            const favorites = getFavoritesFromCookie();
            // Удаляем все элементы этого типа
            const filtered = favorites.filter(fav => fav.type !== type);
            // Добавляем новые
            list.forEach(name => {
                filtered.unshift({ name: name, type: type });
            });
            setFavoritesToCookie(filtered);
        }
        function toggleFavorite(e, type, name) {
            e.stopPropagation();
            e.preventDefault();
            const isFav = isFavorite(type, name);
            if (isFav) {
                removeFromFavorites(type, name);
            } else {
                addToFavorites(type, name);
            }
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
                resultsContainer.innerHTML = '<div style="padding: 1rem; text-align: center; color: rgba(255,255,255,0.6); font-size: 0.9rem;">Ничего не найдено</div>';
            } else {
                resultsContainer.innerHTML = results.map(result => `
                    <div style="display: flex; align-items: center; padding: 0.75rem 1rem; cursor: pointer; transition: all 0.2s ease; border-bottom: 1px solid rgba(255,255,255,0.05);" onclick="selectFromSearch('${result.type}', '${result.name.replace(/'/g, "\\'")}')" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='transparent'">
                        <div style="width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 0.75rem; font-size: 0.9rem; background: ${result.type === 'group' ? 'rgba(59, 130, 246, 0.2)' : 'rgba(168, 85, 247, 0.2)'}; color: ${result.type === 'group' ? '#93c5fd' : '#d8b4fe'};">
                            <i class="fas ${result.type === 'group' ? 'fa-users' : 'fa-chalkboard-teacher'}"></i>
                        </div>
                        <div style="flex: 1; min-width: 0;">
                            <div style="color: white; font-weight: 600; font-size: 0.9rem; margin-bottom: 0.25rem;">${result.title}</div>
                            <div style="color: rgba(255, 255, 255, 0.6); font-size: 0.8rem;">${result.subtitle}</div>
                        </div>
                    </div>
                `).join('');
            }

            resultsContainer.style.display = 'block';
            resultsContainer.classList.remove('hidden');
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
            const searchBtn = document.getElementById('searchBtn');
            const closeSearchBtn = document.getElementById('closeSearchBtn');
            const defaultHeader = document.getElementById('defaultHeader');
            const searchHeader = document.getElementById('searchHeader');
            const searchResults = document.getElementById('searchResults');

            if (!searchInput) return;

            // Открытие поиска
            if (searchBtn) {
                searchBtn.addEventListener('click', function() {
                    if (defaultHeader) defaultHeader.style.display = 'none';
                    if (searchHeader) searchHeader.style.display = 'flex';
                    setTimeout(() => searchInput.focus(), 100);
                });
            }

            // Закрытие поиска
            if (closeSearchBtn) {
                closeSearchBtn.addEventListener('click', function() {
                    if (searchHeader) searchHeader.style.display = 'none';
                    if (defaultHeader) defaultHeader.style.display = 'flex';
                    searchInput.value = '';
                    hideSearchResults();
                });
            }

            // Обработка ввода
            searchInput.addEventListener('input', function(e) {
                const query = e.target.value.trim();

                if (query.length === 0) {
                    hideSearchResults();
                    return;
                }

                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    performGlobalSearch(query);
                }, 150);
            });

            // Скрытие результатов при клике вне поиска
            document.addEventListener('click', function(e) {
                const headerContainer = document.getElementById('headerContainer');
                if (headerContainer && !headerContainer.contains(e.target)) {
                    hideSearchResults();
                }
            });

            // Обработка Esc
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    hideSearchResults();
                    if (closeSearchBtn) closeSearchBtn.click();
                }
            });
        }

        function hideSearchResults() {
            const searchResults = document.getElementById('searchResults');
            if (searchResults) {
                searchResults.style.display = 'none';
                searchResults.classList.add('hidden');
                searchResults.innerHTML = '';
            }
            isSearchActive = false;
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
            // Обновляем состояние кнопки избранного
            updateFavoriteButton();
            // Баннер о закрытии: основная логика — синхронный скрипт сразу после разметки; здесь дублируем скрытие на случай гонки
            (function() {
                var banner = document.getElementById('projectClosureBanner');
                if (!banner) return;
                try {
                    if (localStorage.getItem('idProjectClosureBannerDismissed') === '1') {
                        banner.style.display = 'none';
                    }
                } catch (e) {}
            })();
            // Блок приглашения в канал: скрыть если пользователь закрыл
            (function() {
                var block = document.getElementById('tgPromoBlock');
                var closeBtn = document.getElementById('tgPromoClose');
                if (block && closeBtn) {
                    try {
                        if (localStorage.getItem('tgPromoClosed') === '1') block.style.display = 'none';
                    } catch (e) {}
                    closeBtn.addEventListener('click', function() {
                        block.style.display = 'none';
                        try { localStorage.setItem('tgPromoClosed', '1'); } catch (e) {}
                    });
                }
            })();
            // Периодически проверяем и добавляем акценты к новым блокам
            setInterval(addColorAccents, 500);
        });
    </script>
    
    <style>
      /* Фон страницы и область над контентом (overscroll, статус-бар) — как в main_test: без полос сверху/снизу */
      html {
        margin: 0;
        padding: 0;
        background: linear-gradient(180deg, #0B1220 0%, #353535 100%) !important;
        background-repeat: no-repeat !important;
        background-attachment: fixed !important;
        background-size: 100% 100% !important;
        background-position: center center !important;
        min-height: 100%;
        min-height: 100vh;
        min-height: -webkit-fill-available;
        overflow-x: hidden;
      }
      body {
        margin: 0;
        padding: 0;
        background: linear-gradient(180deg, #0B1220 0%, #353535 100%) !important;
        background-repeat: no-repeat !important;
        background-attachment: fixed !important;
        background-size: 100% 100% !important;
        background-position: center center !important;
        overflow-x: hidden;
      }
      .g-class {
        background: linear-gradient(180deg, #0B1220 0%, #353535 100%) !important;
        background-repeat: no-repeat !important;
        background-attachment: fixed !important;
        background-size: 100% 100% !important;
        background-position: center center !important;
        margin: 0 !important;
        min-height: 100vh !important;
        min-height: -webkit-fill-available !important;
        height: 100% !important;
        font-family: 'Montserrat', system-ui, -apple-system, Segoe UI, Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Apple Color Emoji', 'Segoe UI Emoji';
      }
      
      @media (max-width: 768px) {
        .g-class {
          background-attachment: scroll !important;
          background-size: 100% auto !important;
          background-position: top center !important;
          min-height: -webkit-fill-available !important;
        }
      }

      .FullScreen {
        display: flex;
        min-height: 100vh;
        min-height: -webkit-fill-available;
        width: 100%;
        justify-content: center;
      }

      .On-header {
        width: 100%;
        max-width: 420px;
        padding-left: 1rem;
        padding-right: 1rem;
        padding-top: env(safe-area-inset-top);
        padding-bottom: calc(80px + env(safe-area-inset-bottom));
      }

      .Header {
        margin-left: -1rem;
        margin-right: -1rem;
      }

      .Header-container {
        position: relative;
        width: 100%;
        overflow: visible;
        border-radius: 25px;
        background: none;
        min-height: 60px;
      }

      .Def-Header {
        display: flex;
        height: 60px;
        align-items: center;
        justify-content: space-between;
        padding-left: 1rem;
        padding-right: 1.5rem;
      }

      .Def-Header-Container {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        user-select: none;
      }

      .Def-Header-Text {
        background-clip: text;
        font-size: 15px;
        font-weight: 600;
        line-height: 18px;
        color: transparent;
        background-image: linear-gradient(90deg, #FFFFFF 0%, #999999 100%);
      }

      .Def-Header-Icons {
        display: flex;
        align-items: center;
        gap: 1.75rem;
      }

      .Def-Search-Button {
        cursor: pointer;
        transition-property: opacity;
        transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
        transition-duration: 150ms;
        opacity: 0.8;
        background: transparent;
        border: none;
        padding: 0;
      }

      .Def-Search-Button:hover {
        opacity: 1;
      }

      .Ser-Header {
        position: absolute;
        left: 0px;
        right: 0px;
        top: 0px;
        display: flex;
        height: 60px;
        align-items: center;
        justify-content: space-between;
        padding-left: 1rem;
        padding-right: 1.5rem;
        gap: 0.75rem;
      }

      .Ser-Header-Input {
        background: transparent;
        border: none;
        outline: none;
        color: #ffffff;
        font-size: 16px;
        font-weight: 500;
        padding: 0;
        flex: 1;
        min-width: 0;
      }
      
      .Ser-Header .fa-search {
        font-family: "Font Awesome 6 Free" !important;
        font-weight: 900 !important;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
        display: inline-block !important;
        font-style: normal;
        font-variant: normal;
        text-rendering: auto;
        line-height: 1;
        speak: none;
      }
      
      .Ser-Header .fa-search::before {
        content: "\f002";
      }

      .Ser-Header-Input::placeholder {
        color: rgba(255, 255, 255, 0.5);
      }

      .Search-Results {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        margin-top: 0.5rem;
        border-radius: 16px;
        overflow-y: auto;
        overflow-x: hidden;
      }

      .Search-Results.hidden {
        display: none !important;
      }

      .content-area {
        margin-top: 0.5rem;
        padding-bottom: 1rem;
      }

      /* Promo Block */
      .promo-block {
        margin: 0 0 0.75rem 0;
        padding: 0;
        width: 100%;
        box-sizing: border-box;
      }

      .promo-link {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        padding: 0.4rem 0.75rem;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 10px;
        text-decoration: none;
        transition: all 0.2s ease;
        backdrop-filter: blur(6px);
        width: 100%;
        box-sizing: border-box;
        margin: 0;
      }

      .promo-link:hover {
        background: rgba(255, 255, 255, 0.08);
        border-color: rgba(255, 255, 255, 0.2);
        transform: translateY(-1px);
      }

      .promo-text {
        color: rgba(255, 255, 255, 0.85);
        font-size: 0.75rem;
        font-weight: 500;
        line-height: 1.2;
        flex: 1;
      }

      .promo-icon {
        color: rgba(255, 255, 255, 0.6);
        font-size: 0.7rem;
        flex-shrink: 0;
      }

      /* Modal styles */
      #groupSelectionModal, #teacherSelectionModal, #settingsModal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(4px);
        z-index: 9998;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
      }

      .modal-card {
        background: rgba(15, 23, 42, 0.95);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 16px;
        padding: 1.5rem;
        max-width: 24rem;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
        position: relative;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
      }

      /* Компактные размеры для всего интерфейса */
      .h1 { font-size: 1.1rem !important; font-weight: 600; letter-spacing: -0.01em; color: #ffffff !important; }
      .h2 { font-size: 1rem !important; font-weight: 600; color: #ffffff !important; }
      .small { font-size: 0.8rem !important; color: rgba(255, 255, 255, 0.6) !important; }
      .muted { color: rgba(255, 255, 255, 0.6) !important; }
      
      /* Компактные отступы */
      .space-y-3 > * + * { margin-top: 0.5rem !important; }
      .space-y-6 > * + * { margin-top: 1rem !important; }
      .mt-4 { margin-top: 0.75rem !important; }
      .mt-5 { margin-top: 1rem !important; }
      .mt-6 { margin-top: 1.25rem !important; }
      .mb-3 { margin-bottom: 0.5rem !important; }
      .mb-4 { margin-bottom: 0.75rem !important; }
      
      /* Единообразное закругление для всех элементов */
      .btn { 
        padding: 0.4rem 0.8rem !important; 
        font-size: 0.8rem !important; 
        border-radius: 12px !important; 
        background: rgba(255, 255, 255, 0.05) !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
        color: #ffffff !important;
      }
      .btn:hover {
        background: rgba(255, 255, 255, 0.08) !important;
        border-color: rgba(255, 255, 255, 0.2) !important;
      }
      .group-btn { 
        padding: 0.75rem !important; 
        border-radius: 12px !important; 
        background: rgba(255, 255, 255, 0.05) !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
        color: #ffffff !important;
      }
      .group-btn:hover {
        background: rgba(255, 255, 255, 0.08) !important;
        border-color: rgba(255, 255, 255, 0.2) !important;
      }
      .group-icon { width: 2rem !important; height: 2rem !important; font-size: 0.9rem !important; border-radius: 10px !important; }
      .card { border-radius: 12px !important; }
      .card__inner { 
        border-radius: 12px !important; 
        background: rgba(255, 255, 255, 0.05) !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
      }
      .day-btn { border-radius: 12px !important; }
      .segmented { border-radius: 12px !important; background: rgba(255, 255, 255, 0.05) !important; border: 1px solid rgba(255, 255, 255, 0.1) !important; }
      .segmented button { border-radius: 10px !important; background: transparent !important; color: rgba(255, 255, 255, 0.7) !important; }
      .segmented button.active { background: rgba(59, 130, 246, 0.2) !important; color: #60a5fa !important; }
      .chip { border-radius: 12px !important; }
      .progress { border-radius: 12px !important; height: 6px !important; }
      .progress__meta { font-size: 0.7rem !important; margin-top: 0.25rem !important; color: rgba(255, 255, 255, 0.6) !important; }
      .grid { gap: 0.75rem !important; }
      .grid--two { gap: 0.75rem !important; }
      .days__row { gap: 0.4rem !important; padding: 0.2rem !important; }
      .header__row { gap: 0.5rem !important; }
      .container { max-width: 100% !important; padding: 0 !important; }
      .px { padding-left: 0 !important; padding-right: 0 !important; }

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
      [data-cards] { 
        position: relative; 
        z-index: 3; 
        gap: 0.75rem !important; 
        display: flex;
        flex-direction: column;
      }
      [data-cards] > .card.card__inner { 
        background: rgba(255,255,255,0.05) !important; 
        border: 1px solid rgba(255,255,255,0.1) !important; 
        padding: 1rem !important;
      }
      .card.card__inner { 
        background: rgba(255,255,255,0.05) !important; 
        border: 1px solid rgba(255,255,255,0.1) !important; 
        position: relative; 
        z-index: 2; 
        padding: 1rem !important; 
        border-radius: 12px !important;
      }
      #nowCard { 
        background: linear-gradient(180deg, rgba(16, 185, 129, 0.15) 0%, rgba(255,255,255,0.05) 100%) !important; 
        box-shadow: 0 0 0 1px rgba(16, 185, 129, 0.25) inset; 
      }
      #nextCard { 
        background: linear-gradient(180deg, rgba(59, 130, 246, 0.15) 0%, rgba(255,255,255,0.05) 100%) !important; 
        box-shadow: 0 0 0 1px rgba(59, 130, 246, 0.25) inset; 
      }

      /* Стили для карточек расписания */
      .lesson-card {
        background: rgba(255, 255, 255, 0.05) !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
        color: #ffffff !important;
      }

      .lesson-meta {
        color: rgba(255, 255, 255, 0.7) !important;
      }

      /* Стили для дней недели */
      .days {
        margin-top: 1rem;
      }

      .day-btn {
        background: rgba(255, 255, 255, 0.05) !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
        color: rgba(255, 255, 255, 0.7) !important;
      }

      .day-btn.active {
        background: rgba(59, 130, 246, 0.2) !important;
        border-color: rgba(59, 130, 246, 0.3) !important;
        color: #60a5fa !important;
      }

        @media (max-width: 768px) {
            .search-container { max-width: none; margin-right: 0.5rem; }
            .search-input-wrapper { padding: 0.4rem 0.75rem; }
            #globalSearch { font-size: 0.85rem; }
            .search-results { max-height: 250px; }
            .search-result-item { padding: 0.6rem 0.75rem; }
            .search-result-icon { width: 28px; height: 28px; font-size: 0.8rem; margin-right: 0.5rem; }
      }

      /* Bottom Navigation */
      .bottom-navigation {
        position: fixed;
        bottom: 0;
        left: 50%;
        transform: translateX(-50%);
        width: 100%;
        max-width: 420px;
        background: #1a1a1a;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 25px;
        display: flex;
        justify-content: space-around;
        align-items: center;
        padding: 0.5rem 0;
        padding-bottom: calc(0.5rem + env(safe-area-inset-bottom));
        z-index: 1000;
        box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.3);
      }

      .nav-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.25rem;
        padding: 0.5rem 0.75rem;
        text-decoration: none;
        color: rgba(255, 255, 255, 0.6);
        transition: all 0.2s ease;
        position: relative;
        flex: 1;
        max-width: 25%;
            cursor: pointer;
            background: transparent;
            border: none;
      }

      .nav-item:hover {
        color: rgba(255, 255, 255, 0.8);
      }

      .nav-item.active {
        color: #3b82f6;
      }

      .nav-item.active .nav-icon {
        color: #3b82f6;
      }

      .nav-icon {
        font-size: 20px;
        color: inherit;
        transition: color 0.2s ease;
      }

      .nav-label {
        font-size: 11px;
        font-weight: 500;
        color: inherit;
        text-align: center;
        line-height: 1.2;
      }

      .nav-avatar {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        position: relative;
        flex-shrink: 0;
      }

      .nav-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 50%;
        display: block;
      }

      .nav-icon-fallback {
        font-size: 14px;
        color: rgba(255, 255, 255, 0.6);
        display: block;
      }

      .nav-item.active .nav-avatar {
        background: rgba(59, 130, 246, 0.2);
        border: 2px solid #3b82f6;
      }

      .nav-item.active .nav-icon-fallback {
        color: #3b82f6;
      }

        .nav-indicator {
            position: absolute;
            top: 0.25rem;
            right: 0.5rem;
            width: 8px;
            height: 8px;
            background: #10b981;
            border-radius: 50%;
            border: 2px solid #1a1a1a;
        }

        .nav-indicator.pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
                transform: scale(1);
            }
            50% {
                opacity: 0.8;
                transform: scale(1.1);
            }
        }

        /* Добавляем отступ снизу для контента, чтобы навигация не перекрывала */
        main {
            padding-bottom: calc(80px + env(safe-area-inset-bottom)) !important;
        }

        @media (max-width: 480px) {
        .nav-label {
          font-size: 10px;
        }
        .nav-icon {
          font-size: 18px;
        }
        .nav-item {
          padding: 0.4rem 0.5rem;
        }
      }

      /* Telegram channel button hover effect */
      .telegram-channel-button:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
        background: linear-gradient(135deg, #0099e6, #006fa6) !important;
      }

      .telegram-channel-button:active {
        transform: translateY(0);
      }

      /* Кнопка "Поделиться" */
      .share-button {
        background: rgba(59, 130, 246, 0.2) !important;
        border: 1px solid rgba(59, 130, 246, 0.3) !important;
        color: #60a5fa !important;
        padding: 0.5rem 0.75rem !important;
        border-radius: 12px !important;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        min-width: 2.5rem;
        height: 2.5rem;
      }

      .share-button:hover {
        background: rgba(59, 130, 246, 0.3) !important;
        border-color: rgba(59, 130, 246, 0.5) !important;
        transform: translateY(-1px);
      }

      .share-button:active {
        transform: translateY(0);
      }

      .share-button i {
        font-size: 16px;
      }

      /* Кнопка "Избранное" */
      .favorite-button {
        background: rgba(255, 209, 102, 0.2) !important;
        border: 1px solid rgba(255, 209, 102, 0.3) !important;
        color: rgba(255, 255, 255, 0.6) !important;
        padding: 0.5rem 0.75rem !important;
        border-radius: 12px !important;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        min-width: 2.5rem;
        height: 2.5rem;
        margin-right: 0.5rem;
      }

      .favorite-button:hover {
        background: rgba(255, 209, 102, 0.3) !important;
        border-color: rgba(255, 209, 102, 0.5) !important;
        transform: translateY(-1px);
      }

      .favorite-button:active {
        transform: translateY(0);
      }

      .favorite-button i {
        font-size: 16px;
        transition: color 0.2s ease;
      }

      .favorite-button.active i {
        color: #ffd166 !important;
      }

      /* Уведомление о копировании */
      #copyNotification {
        position: fixed;
        bottom: 100px;
        left: 50%;
        transform: translateX(-50%) translateY(100px);
        background: rgba(16, 185, 129, 0.95);
        color: #fff;
        padding: 0.75rem 1.5rem;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 14px;
        font-weight: 500;
        z-index: 10000;
        opacity: 0;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        pointer-events: none;
        backdrop-filter: blur(10px);
      }

      #copyNotification.show {
        opacity: 1;
        transform: translateX(-50%) translateY(0);
      }

      #copyNotification i {
        font-size: 16px;
      }

      @media (max-width: 480px) {
        #copyNotification {
          bottom: 80px;
          padding: 0.625rem 1.25rem;
          font-size: 13px;
        }
      }

    </style>

    <!-- Bottom Navigation -->
    <div class="bottom-navigation">
        <a href="main_test.php" class="nav-item<?php echo basename($_SERVER['PHP_SELF']) === 'main_test.php' ? ' active' : ''; ?>">
            <i class="fas fa-home nav-icon"></i>
            <span class="nav-label">Главная</span>
        </a>
        <a href="id.php" class="nav-item<?php echo basename($_SERVER['PHP_SELF']) === 'id.php' || basename($_SERVER['PHP_SELF']) === 'id2.php' ? ' active' : ''; ?>">
            <i class="fas fa-calendar-alt nav-icon"></i>
            <span class="nav-label">Расписание</span>
            <?php if (isset($currentLesson) && $currentLesson): ?>
                <span class="nav-indicator pulse"></span>
            <?php endif; ?>
        </a>
        <a href="imsitmaps.php" class="nav-item<?php echo basename($_SERVER['PHP_SELF']) === 'imsitmaps.php' ? ' active' : ''; ?>">
            <i class="fas fa-map nav-icon"></i>
            <span class="nav-label">Карта</span>
        </a>
        <button type="button" class="nav-item" id="navSettingsBtn" onclick="showSettingsModal()">
            <i class="fas fa-cog nav-icon"></i>
            <span class="nav-label">Настройки</span>
        </button>
    </div>

    <!-- Модальное окно для выбора приложения для шаринга -->

    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <script>
        // Функция для открытия модалки настроек (должна быть определена в schedule_js.js)
        function showSettingsModal() {
            const m = document.getElementById('settingsModal');
            if (m) {
                m.style.display = 'flex';
            }
        }

        // Функция для сокращения ссылки через is.gd API (с fallback на v.gd)
        async function shortenUrl(longUrl) {
            const timeout = 5000; // 5 секунд таймаут
            
            // Функция для запроса с таймаутом
            const fetchWithTimeout = (url, options = {}) => {
                return Promise.race([
                    fetch(url, options),
                    new Promise((_, reject) => 
                        setTimeout(() => reject(new Error('Timeout')), timeout)
                    )
                ]);
            };

            // Пробуем is.gd
            try {
                const response = await fetchWithTimeout(`https://is.gd/create.php?format=json&url=${encodeURIComponent(longUrl)}`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                
                if (response.ok) {
                    const data = await response.json();
                    if (data.shorturl) {
                        return data.shorturl;
                    }
                }
            } catch (error) {
                console.warn('is.gd не доступен, пробуем v.gd:', error);
            }

            // Fallback на v.gd
            try {
                const response = await fetchWithTimeout(`https://v.gd/create.php?format=json&url=${encodeURIComponent(longUrl)}`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                
                if (response.ok) {
                    const data = await response.json();
                    if (data.shorturl) {
                        return data.shorturl;
                    }
                }
            } catch (error) {
                console.warn('v.gd также не доступен:', error);
            }

            // Если оба сервиса не работают, возвращаем оригинальную ссылку
            console.warn('Не удалось сократить ссылку, используем оригинальную');
            return longUrl;
        }

        // Функция для генерации ссылки на бота
        async function generateBotShareLink(type, value) {
            try {
                const response = await fetch(`api/generate_share_link.php?type=${encodeURIComponent(type)}&value=${encodeURIComponent(value)}`);
                const data = await response.json();
                
                if (data.ok && data.link) {
                    return data.link;
                } else {
                    console.error('Ошибка генерации ссылки на бота:', data);
                    return null;
                }
            } catch (error) {
                console.error('Ошибка при генерации ссылки на бота:', error);
                return null;
            }
        }

        // Функция для показа уведомления о копировании
        function showCopyNotification() {
            // Удаляем предыдущее уведомление, если есть
            const existingNotification = document.getElementById('copyNotification');
            if (existingNotification) {
                existingNotification.remove();
            }

            // Создаем уведомление
            const notification = document.createElement('div');
            notification.id = 'copyNotification';
            notification.innerHTML = '<i class="fas fa-check-circle"></i> Текст скопирован';
            document.body.appendChild(notification);

            // Показываем уведомление с анимацией
            setTimeout(() => {
                notification.classList.add('show');
            }, 10);

            // Скрываем уведомление через 2 секунды
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 2000);
        }

        // Функция для шаринга расписания (копирование в буфер обмена)
        async function shareSchedule() {
            const bootstrap = window.SCHEDULE_BOOTSTRAP;
            if (!bootstrap) return;

            // Показываем индикатор загрузки
            const shareBtn = document.getElementById('shareBtn');
            const originalContent = shareBtn ? shareBtn.innerHTML : '';
            if (shareBtn) {
                shareBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                shareBtn.disabled = true;
            }

            let type, value, text;
            
            // Функция для очистки текста от HTML-тегов и атрибутов
            function cleanText(str) {
                if (!str) return '';
                // Создаем временный элемент для удаления HTML-тегов
                const div = document.createElement('div');
                div.innerHTML = str;
                return div.textContent || div.innerText || '';
            }
            
            if (bootstrap.viewMode === 'group' && bootstrap.group) {
                type = 'group';
                value = cleanText(bootstrap.group);
            } else if (bootstrap.viewMode === 'teacher' && bootstrap.teacher) {
                type = 'teacher';
                value = cleanText(bootstrap.teacher);
            } else {
                if (shareBtn) {
                    shareBtn.innerHTML = originalContent;
                    shareBtn.disabled = false;
                }
                return;
            }

            // Генерируем ссылку на бота
            const botLink = await generateBotShareLink(type, value);
            
            if (!botLink) {
                // Если не удалось сгенерировать ссылку на бота, используем обычную ссылку
                const baseUrl = window.location.origin + window.location.pathname;
                const fallbackUrl = type === 'group' 
                    ? baseUrl + '?group=' + encodeURIComponent(value)
                    : baseUrl + '?teacher=' + encodeURIComponent(value);
                
                if (type === 'group') {
                    text = `📅 Расписание группы ${value}\n\nПосмотреть расписание:\n${fallbackUrl}`;
                } else {
                    text = `👨‍🏫 Расписание преподавателя ${value}\n\nПосмотреть расписание:\n${fallbackUrl}`;
                }
            } else {
                // Формируем текст для шаринга с ссылкой на бота
                if (type === 'group') {
                    text = `📅 Расписание группы ${value}\n\nНажмите на ссылку, чтобы открыть расписание через бота:\n${botLink}`;
                } else {
                    text = `👨‍🏫 Расписание преподавателя ${value}\n\nНажмите на ссылку, чтобы открыть расписание через бота:\n${botLink}`;
                }
            }

            // Копируем текст в буфер обмена
            let copySuccess = false;
            let lastError = null;
            
            // Функция для копирования через Clipboard API
            async function copyWithClipboardAPI() {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    // Проверяем безопасный контекст
                    const isSecureContext = window.isSecureContext || 
                        location.protocol === 'https:' || 
                        location.hostname === 'localhost' || 
                        location.hostname === '127.0.0.1' ||
                        location.hostname === 't.me' ||
                        location.hostname.includes('telegram');
                    
                    console.log('Clipboard API проверка:', {
                        hasClipboard: !!navigator.clipboard,
                        hasWriteText: !!navigator.clipboard?.writeText,
                        isSecureContext: isSecureContext,
                        protocol: location.protocol,
                        hostname: location.hostname
                    });
                    
                    if (isSecureContext) {
                        try {
                            await navigator.clipboard.writeText(text);
                            console.log('Clipboard API: успешно');
                            return true;
                        } catch (err) {
                            console.warn('Clipboard API ошибка:', err);
                            lastError = err;
                            return false;
                        }
                    } else {
                        console.log('Clipboard API: небезопасный контекст');
                    }
                } else {
                    console.log('Clipboard API: недоступен');
                }
                return false;
            }
            
            // Функция для копирования через execCommand (fallback)
            function copyWithExecCommand() {
                try {
                    console.log('Пробуем execCommand с textarea');
                    // Создаем невидимый textarea
                    const textArea = document.createElement('textarea');
                    textArea.value = text;
                    textArea.style.position = 'fixed';
                    textArea.style.left = '0';
                    textArea.style.top = '0';
                    textArea.style.width = '2em';
                    textArea.style.height = '2em';
                    textArea.style.padding = '0';
                    textArea.style.border = 'none';
                    textArea.style.outline = 'none';
                    textArea.style.boxShadow = 'none';
                    textArea.style.background = 'transparent';
                    textArea.style.opacity = '0';
                    textArea.style.pointerEvents = 'none';
                    textArea.setAttribute('readonly', '');
                    textArea.setAttribute('aria-hidden', 'true');
                    
                    document.body.appendChild(textArea);
                    
                    // Выбираем текст
                    if (navigator.userAgent.match(/ipad|iphone/i)) {
                        // Для iOS
                        const range = document.createRange();
                        range.selectNodeContents(textArea);
                        const selection = window.getSelection();
                        selection.removeAllRanges();
                        selection.addRange(range);
                        textArea.setSelectionRange(0, 999999);
                    } else {
                        textArea.focus();
                        textArea.select();
                        textArea.setSelectionRange(0, 999999);
                    }
                    
                    // Копируем
                    const successful = document.execCommand('copy');
                    console.log('execCommand результат:', successful);
                    document.body.removeChild(textArea);
                    
                    return successful;
                } catch (err) {
                    console.error('Ошибка execCommand:', err);
                    lastError = err;
                    return false;
                }
            }
            
            // Функция для копирования через временный input (альтернативный fallback)
            function copyWithInput() {
                try {
                    console.log('Пробуем execCommand с input');
                    const input = document.createElement('input');
                    input.value = text;
                    input.style.position = 'fixed';
                    input.style.left = '0';
                    input.style.top = '0';
                    input.style.width = '2em';
                    input.style.height = '2em';
                    input.style.padding = '0';
                    input.style.border = 'none';
                    input.style.outline = 'none';
                    input.style.boxShadow = 'none';
                    input.style.background = 'transparent';
                    input.style.opacity = '0';
                    input.style.pointerEvents = 'none';
                    
                    document.body.appendChild(input);
                    input.focus();
                    input.select();
                    input.setSelectionRange(0, 999999);
                    
                    const successful = document.execCommand('copy');
                    console.log('execCommand с input результат:', successful);
                    document.body.removeChild(input);
                    
                    return successful;
                } catch (err) {
                    console.error('Ошибка копирования через input:', err);
                    lastError = err;
                    return false;
                }
            }
            
            // Функция для показа модалки с текстом для ручного копирования
            function showManualCopyModal() {
                // Сохраняем текст в локальную переменную, чтобы он точно был чистым
                const textToCopy = text;
                
                const modal = document.createElement('div');
                modal.id = 'manualCopyModal';
                modal.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.8); display: flex; align-items: center; justify-content: center; z-index: 10000; padding: 1rem; box-sizing: border-box;';
                
                const card = document.createElement('div');
                card.style.cssText = 'background: #1a1a1a; border-radius: 16px; padding: 1.5rem; max-width: 500px; width: 100%; max-height: 80vh; overflow-y: auto; position: relative; border: 1px solid rgba(255, 255, 255, 0.1);';
                
                // Кнопка закрытия
                const closeBtn = document.createElement('button');
                closeBtn.innerHTML = '<i class="fas fa-times" style="font-size: 12px;"></i>';
                closeBtn.style.cssText = 'position: absolute; top: 1rem; right: 1rem; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); border-radius: 12px; width: 2rem; height: 2rem; display: flex; align-items: center; justify-content: center; color: #fff; cursor: pointer; transition: all 0.2s ease;';
                closeBtn.onclick = () => modal.remove();
                
                // Заголовок
                const title = document.createElement('h3');
                title.style.cssText = 'color: #fff; margin: 0 0 1rem; font-size: 1.25rem;';
                title.innerHTML = '<i class="fas fa-copy" style="margin-right: 8px;"></i>Скопируйте текст';
                
                // Textarea
                const textarea = document.createElement('textarea');
                textarea.id = 'manualCopyText';
                textarea.readOnly = true;
                textarea.value = textToCopy; // Устанавливаем чистый текст
                textarea.style.cssText = 'width: 100%; min-height: 150px; padding: 1rem; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #fff; font-size: 14px; font-family: inherit; resize: vertical; box-sizing: border-box;';
                
                // Кнопка копирования
                const copyBtn = document.createElement('button');
                copyBtn.innerHTML = '<i class="fas fa-copy"></i> Выделить и скопировать';
                copyBtn.style.cssText = 'width: 100%; margin-top: 1rem; padding: 0.75rem; background: rgba(59, 130, 246, 0.2); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 8px; color: #60a5fa; cursor: pointer; font-size: 14px; font-weight: 500; transition: all 0.2s ease;';
                copyBtn.onclick = function() {
                    textarea.select();
                    textarea.setSelectionRange(0, 999999);
                    try {
                        const success = document.execCommand('copy');
                        if (success) {
                            const original = copyBtn.innerHTML;
                            copyBtn.innerHTML = '<i class="fas fa-check"></i> Скопировано!';
                            copyBtn.style.background = 'rgba(16, 185, 129, 0.2)';
                            copyBtn.style.borderColor = 'rgba(16, 185, 129, 0.5)';
                            copyBtn.style.color = '#10b981';
                            setTimeout(() => {
                                copyBtn.innerHTML = original;
                                copyBtn.style.background = '';
                                copyBtn.style.borderColor = '';
                                copyBtn.style.color = '';
                            }, 2000);
                            if (typeof showCopyNotification === 'function') {
                                showCopyNotification();
                            }
                        } else {
                            alert('Выделите текст и скопируйте вручную (Ctrl+C или Cmd+C)');
                        }
                    } catch(e) {
                        alert('Выделите текст и скопируйте вручную (Ctrl+C или Cmd+C)');
                    }
                };
                
                // Собираем структуру
                card.appendChild(closeBtn);
                card.appendChild(title);
                card.appendChild(textarea);
                card.appendChild(copyBtn);
                modal.appendChild(card);
                document.body.appendChild(modal);
                
                // Закрытие по клику вне модалки
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        modal.remove();
                    }
                });
                
                // Автоматически выделяем текст при открытии
                setTimeout(() => {
                    textarea.focus();
                    textarea.select();
                }, 100);
            }
            
            // Пробуем разные методы копирования
            try {
                console.log('Начинаем копирование, длина текста:', text.length);
                
                // Метод 1: Clipboard API
                copySuccess = await copyWithClipboardAPI();
                console.log('Метод 1 (Clipboard API):', copySuccess);
                
                // Метод 2: execCommand с textarea
                if (!copySuccess) {
                    copySuccess = copyWithExecCommand();
                    console.log('Метод 2 (execCommand textarea):', copySuccess);
                }
                
                // Метод 3: execCommand с input
                if (!copySuccess) {
                    copySuccess = copyWithInput();
                    console.log('Метод 3 (execCommand input):', copySuccess);
                }
                
                if (copySuccess) {
                    // Показываем уведомление об успешном копировании
                    showCopyNotification();
                } else {
                    console.error('Все методы копирования не сработали. Последняя ошибка:', lastError);
                    // Показываем модалку для ручного копирования
                    showManualCopyModal();
                }
            } catch (error) {
                console.error('Критическая ошибка при копировании:', error);
                // Показываем модалку для ручного копирования
                showManualCopyModal();
            } finally {
                // Восстанавливаем кнопку
                if (shareBtn) {
                    shareBtn.innerHTML = originalContent;
                    shareBtn.disabled = false;
                }
            }
        }

    </script>
    <script>
(function(){
  function run(){
    var c=document.getElementById('bgEffect');
    if(!c)return;
    var dotsEl=document.getElementById('bgEffectDots');
    var stripsEl=document.getElementById('bgEffectStrips');
    var svg=document.getElementById('bgEffectLines');
    if(!dotsEl||!stripsEl||!svg)return;
    var isMobile=window.innerWidth<=768;
    var nStrips=isMobile?8:16,nStripDots=isMobile?4:8,nFree=isMobile?12:26,nBreak=isMobile?4:9;
    var dots=[],cur={x:-1e4,y:-1e4},raf=null,R=isMobile?200:280,M=isMobile?100:140;
    function r(a,b){return a+Math.random()*(b-a);}
    var stripList=[];
    for(var i=0;i<nStrips;i++){
      var lx=r(0,100),ly=r(0,100);
      var s=document.createElement('div');
      s.className='bg-strip';
      s.style.left=lx+'%';
      s.style.top=ly+'%';
      s.style.transform='rotate('+r(0,360)+'deg)';
      s.style.width=(50+r(0,80))+'px';
      s.style.animationDelay=r(0,3)+'s';
      stripsEl.appendChild(s);
      stripList.push(s);
      if(i<nStripDots){
        var d=document.createElement('div');
        d.className='bg-dot';
        d.style.left=lx+'%';
        d.style.top=ly+'%';
        d.style.animationDelay=r(0,5)+'s';
        d.style.animationDuration=(7+r(0,4))+'s';
        dotsEl.appendChild(d);
        dots.push({el:d,x:0,y:0});
      }
    }
    for(var i=0;i<nFree;i++){
      var d=document.createElement('div');
      d.className=i<nBreak?'bg-dot bg-dot-break':'bg-dot';
      d.style.left=r(0,100)+'%';
      d.style.top=r(0,100)+'%';
      d.style.animationDelay=r(0,5)+'s';
      d.style.animationDuration=(7+r(0,4))+'s';
      if(i<nBreak){d.style.animationDelay=(r(0,4)+2)+'s';}
      dotsEl.appendChild(d);
      dots.push({el:d,x:0,y:0});
    }
    function updatePos(){
      for(var i=0;i<dots.length;i++){
        var rect=dots[i].el.getBoundingClientRect();
        dots[i].x=rect.left+rect.width/2;
        dots[i].y=rect.top+rect.height/2;
      }
    }
    var MStatic=isMobile?130:180;
    function draw(){
      updatePos();
      var cx=cur.x,cy=cur.y,lines=[];
      for(var a=0;a<dots.length;a++)
        for(var b=a+1;b<dots.length;b++){
          var ddx=dots[a].x-dots[b].x,ddy=dots[a].y-dots[b].y;
          var dist=Math.sqrt(ddx*ddx+ddy*ddy);
          if(dist<MStatic)lines.push({x1:dots[a].x,y1:dots[a].y,x2:dots[b].x,y2:dots[b].y,op:0.22*(1-dist/MStatic)});
        }
      if(cx>-1e3&&cy>-1e3){
        for(var i=0;i<dots.length;i++){
          var dx=dots[i].x-cx,dy=dots[i].y-cy;
          var d2=dx*dx+dy*dy;
          if(d2<R*R)lines.push({x1:cx,y1:cy,x2:dots[i].x,y2:dots[i].y,op:0.4*(1-Math.sqrt(d2)/R)});
        }
        var inRange=[];
        for(var i=0;i<dots.length;i++){
          var dx=dots[i].x-cx,dy=dots[i].y-cy;
          if(dx*dx+dy*dy<R*R)inRange.push(i);
        }
        for(var a=0;a<inRange.length;a++)
          for(var b=a+1;b<inRange.length;b++){
            var i=inRange[a],j=inRange[b];
            var ddx=dots[i].x-dots[j].x,ddy=dots[i].y-dots[j].y;
            var dist=Math.sqrt(ddx*ddx+ddy*ddy);
            if(dist<M)lines.push({x1:dots[i].x,y1:dots[i].y,x2:dots[j].x,y2:dots[j].y,op:0.45*(1-dist/M)});
          }
      }
      var w=window.innerWidth,h=window.innerHeight;
      svg.setAttribute('viewBox','0 0 '+w+' '+h);
      svg.setAttribute('width',w);
      svg.setAttribute('height',h);
      svg.innerHTML='';
      lines.forEach(function(l){
        var line=document.createElementNS('http://www.w3.org/2000/svg','line');
        line.setAttribute('x1',l.x1);
        line.setAttribute('y1',l.y1);
        line.setAttribute('x2',l.x2);
        line.setAttribute('y2',l.y2);
        line.setAttribute('stroke','rgba(255,255,255,'+l.op+')');
        line.setAttribute('stroke-width','1');
        line.setAttribute('stroke-linecap','round');
        svg.appendChild(line);
      });
    }
    function onMove(e){cur.x=e.clientX;cur.y=e.clientY;if(!raf){raf=requestAnimationFrame(function(){raf=null;draw();});}}
    function onLeave(){cur.x=-1e4;cur.y=-1e4;if(!raf){raf=requestAnimationFrame(function(){raf=null;draw();});}}
    function onTouch(e){if(e.touches&&e.touches[0]){cur.x=e.touches[0].clientX;cur.y=e.touches[0].clientY;if(!raf){raf=requestAnimationFrame(function(){raf=null;draw();});}}}
    document.addEventListener('mousemove',onMove,{passive:true});
    document.addEventListener('mouseleave',onLeave);
    document.addEventListener('touchstart',onTouch,{passive:true});
    document.addEventListener('touchmove',onTouch,{passive:true});
    document.addEventListener('touchend',onLeave);
    document.addEventListener('touchcancel',onLeave);
    window.addEventListener('resize',function(){onLeave();setTimeout(draw,50);});
    setInterval(draw,120);
    setTimeout(draw,300);
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',run);
  else run();
})();
    </script>
</body>
</html>

<!-- ID_BASIC Version 3.4.0 -->


