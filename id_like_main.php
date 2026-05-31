<?php
/*
                ██╗███╗   ███╗███████╗██╗████████╗    ██╗██████╗ 
                ██║████╗ ████║██╔════╝██║╚══██╔══╝    ██║██╔══██╗
                ██║██╔████╔██║███████╗██║   ██║       ██║██║  ██║
                ██║██║╚██╔╝██║╚════██║██║   ██║       ██║██║  ██║
                ██║██║ ╚═╝ ██║███████║██║   ██║       ██║██████╔╝
                ╚═╝╚═╝     ╚═╝╚══════╝╚═╝   ╚═╝       ╚═╝╚═════╝ 

    ╔══════════════════════════════════════════════════════════════════════════════╗
    ║                               Version 3.2                                    ║
    ║                                                                              ║
    ║     Расписание для всех групп и преподавателей                               ║
    ║     Адаптивный дизайн для мобильных устройств                                ║
    ║     Стиль как в main_test.php                                                ║
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
    <title>ImsitID - Расписание для всех студентов и преподавателей Академии ИМСИТ</title>
    <meta name="description" content="Расписание для всех студентов и преподавателей Академии ИМСИТ. ">
    <meta name="keywords" content="Расписание, Академия ИМСИТ, Расписание для всех студентов и преподавателей Академии ИМСИТ, imsitshop, imsitid, imsit.shop, imsit.shop/shedule2, imsit.shop/shedule2.php, imsitid.ru, imsitid.com, imsitid.net, imsitid.org, imsitid.ru/schedule, imsitid.com/schedule, imsitid.net/schedule, imsitid.org/schedule, imsit, id imsit, imsitid">
    <meta name="author" content="ImsitID">
    <meta name="robots" content="index, follow">
    <meta name="googlebot" content="index, follow">
    <meta name="bingbot" content="index, follow">
    <meta name="google" content="notranslate">
    <link rel="canonical" href="https://imsit.shop/">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/main.css?v=<?php echo time(); ?>">
    <style>
      .g-class {
        background: linear-gradient(180deg, #0B1220 0%, #353535 100%) !important;
        background-repeat: no-repeat !important;
        background-attachment: fixed !important;
        background-size: 100% 100% !important;
        background-position: center center !important;
        margin: 0 !important;
        min-height: 100vh !important;
        height: 100% !important;
        font-family: 'Montserrat', system-ui, -apple-system, Segoe UI, Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Apple Color Emoji', 'Segoe UI Emoji';
      }
      
      /* Исправление для мобильных */
      @media (max-width: 768px) {
        .g-class {
          background-attachment: scroll !important;
          background-size: 100% auto !important;
          background-position: top center !important;
          min-height: -webkit-fill-available !important;
        }
      }

      /* Стили для расписания в стиле main_test.php */
      .schedule-content {
        padding: 1rem;
      }

      .schedule-header {
        margin-bottom: 1.5rem;
      }

      .schedule-title {
        font-size: 18px;
        font-weight: 600;
        color: #ffffff;
        margin-bottom: 0.5rem;
      }

      .schedule-cards {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
        margin-bottom: 1.5rem;
      }

      @media (max-width: 480px) {
        .schedule-cards {
          grid-template-columns: 1fr;
        }
      }

      .schedule-card {
        background: #333333;
        border-radius: 16px;
        padding: 1rem;
        position: relative;
      }

      .schedule-card-now {
        background: linear-gradient(135deg, rgba(96, 165, 250, 0.2) 0%, rgba(96, 165, 250, 0.1) 100%);
        border: 1px solid rgba(96, 165, 250, 0.3);
      }

      .schedule-card-next {
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.2) 0%, rgba(99, 102, 241, 0.1) 100%);
        border: 1px solid rgba(99, 102, 241, 0.3);
      }

      .schedule-card-label {
        font-size: 12px;
        color: rgba(255, 255, 255, 0.7);
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
      }

      .schedule-card-time {
        font-size: 14px;
        color: rgba(255, 255, 255, 0.9);
        font-weight: 600;
      }

      .schedule-card-subject {
        font-size: 16px;
        font-weight: 600;
        color: #ffffff;
        margin: 0.75rem 0;
      }

      .schedule-card-meta {
        font-size: 13px;
        color: rgba(255, 255, 255, 0.7);
        margin-top: 0.5rem;
      }

      .schedule-week-selector {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 1rem;
        justify-content: center;
      }

      .week-btn {
        padding: 0.5rem 1rem;
        background: #333333;
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        color: #ffffff;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
      }

      .week-btn.active {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        border-color: rgba(59, 130, 246, 0.5);
      }

      .days-selector {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
        justify-content: center;
        margin-bottom: 1.5rem;
      }

      .day-btn {
        padding: 0.5rem 0.75rem;
        background: #333333;
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        color: #ffffff;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
        min-width: 50px;
        text-align: center;
      }

      .day-btn.active {
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        border-color: rgba(99, 102, 241, 0.5);
      }

      .lessons-list {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
      }

      .lesson-item {
        background: #333333;
        border-radius: 16px;
        padding: 1rem;
        border: 1px solid rgba(255, 255, 255, 0.1);
        transition: all 0.2s;
      }

      .lesson-item:hover {
        border-color: rgba(255, 255, 255, 0.2);
        transform: translateY(-2px);
      }

      .lesson-number {
        font-size: 12px;
        color: rgba(255, 255, 255, 0.6);
        margin-bottom: 0.5rem;
      }

      .lesson-subject {
        font-size: 16px;
        font-weight: 600;
        color: #ffffff;
        margin-bottom: 0.5rem;
      }

      .lesson-info {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
        margin-top: 0.5rem;
      }

      .lesson-badge {
        padding: 0.25rem 0.5rem;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        font-size: 12px;
        color: rgba(255, 255, 255, 0.9);
      }

      .empty-state {
        text-align: center;
        padding: 3rem 1rem;
        color: rgba(255, 255, 255, 0.7);
      }

      .settings-button {
        position: fixed;
        bottom: 2rem;
        right: 50%;
        transform: translateX(50%);
        background: linear-gradient(135deg, #8b5cf6 0%, #4338ca 100%);
        border: 2px solid rgba(255, 255, 255, 0.25);
        border-radius: 25px;
        padding: 0.75rem 1.5rem;
        color: #ffffff;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        box-shadow: 0 8px 24px rgba(67, 56, 202, 0.35);
        z-index: 100;
        max-width: 420px;
      }

      @media (max-width: 480px) {
        .settings-button {
          right: 1rem;
          transform: none;
          max-width: calc(100% - 2rem);
        }
      }

      /* Модальные окна */
      .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.8);
        backdrop-filter: blur(10px);
        z-index: 1000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 1rem;
      }

      .modal-overlay.active {
        display: flex;
      }

      .modal-content {
        background: #282828;
        border-radius: 25px;
        padding: 1.5rem;
        max-width: 420px;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
        position: relative;
      }

      .modal-close {
        position: absolute;
        top: 1rem;
        right: 1rem;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 12px;
        width: 2rem;
        height: 2rem;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        cursor: pointer;
        transition: all 0.2s;
      }

      .modal-close:hover {
        background: rgba(255, 255, 255, 0.2);
      }

      .group-list, .teacher-list {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        max-height: 400px;
        overflow-y: auto;
        margin-top: 1rem;
      }

      .group-item, .teacher-item {
        background: #333333;
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        padding: 0.75rem;
        color: #ffffff;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 0.75rem;
      }

      .group-item:hover, .teacher-item:hover {
        background: #3a3a3a;
        border-color: rgba(255, 255, 255, 0.2);
      }

      .search-input-modal {
        width: 100%;
        padding: 0.75rem;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 12px;
        color: #ffffff;
        font-size: 14px;
        margin-bottom: 1rem;
      }

      .search-input-modal::placeholder {
        color: rgba(255, 255, 255, 0.5);
      }
    </style>
</head>
<body class="g-class">
    <div class="page-wrapper">
        <div class="page-content">
            <!-- Header -->
            <div class="header-wrapper">
                <div id="headerContainer" class="header-container">
                    <div class="search-overlay"></div>
                    <!-- Default Header View -->
                    <div id="defaultHeader" class="header-default">
                        <div class="header-title-group">
                            <div class="header-title">
                                <?php if ($viewMode === 'teacher' && $teacherInfo): ?>
                                    <?php echo htmlspecialchars($teacherInfo['full_name']); ?>
                                <?php elseif ($viewMode === 'group' && $userGroup): ?>
                                    <?php echo htmlspecialchars($userGroup); ?>
                                <?php else: ?>
                                    Расписание
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="header-actions">
                            <button id="searchBtn" class="header-action-button">
                                <i class="fas fa-search header-action-icon"></i>
                            </button>
                            <?php if ($currentUser): ?>
                                <a href="client_dashboard.php" class="header-action-link">
                                    <i class="fas fa-user header-action-icon"></i>
                                </a>
                            <?php else: ?>
                                <a href="https://t.me/cowgivesmilk" target="_blank" class="header-action-link">
                                    <i class="fas fa-question-circle header-action-icon"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Search Header View -->
                    <div id="searchHeader" class="header-search">
                        <input type="text" id="globalSearch" placeholder="Поиск..." autocomplete="off" class="search-input">
                        <div class="header-actions">
                            <button id="closeSearchBtn" class="header-action-button">
                                <i class="fas fa-times header-action-icon"></i>
                            </button>
                            <?php if ($currentUser): ?>
                                <a href="client_dashboard.php" class="header-action-link">
                                    <i class="fas fa-user header-action-icon"></i>
                                </a>
                            <?php else: ?>
                                <a href="https://t.me/cowgivesmilk" target="_blank" class="header-action-link">
                                    <i class="fas fa-question-circle header-action-icon"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Search Results -->
                    <div id="searchResults" class="search-results-container is-hidden">
                        <!-- Results will be inserted here -->
                    </div>
                </div>
            </div>

            <div class="content-area">
                <!-- imsitMaps promo -->
                <div style="margin: 1rem; padding: 0.5rem; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; display: flex; align-items: center; justify-content: space-between;">
                    <div style="color: rgba(255,255,255,0.75); font-size: 14px;">
                        <i class="fa-solid fa-map-location-dot" style="margin-right:6px;color:#60a5fa"></i>Интерактивная карта корпусов
                    </div>
                    <a href="imsitmaps.html" style="padding: 0.35rem 0.7rem; background: linear-gradient(135deg, #60a5fa, #6366f1); border: 1px solid rgba(255,255,255,0.2); border-radius: 12px; color: #fff; font-size: 14px; text-decoration: none;">
                        <i class="fa-solid fa-location-dot" style="margin-right: 6px;"></i>imsitMaps
                    </a>
                </div>

                <?php if ($userGroup || $selectedTeacher): ?>
                    <!-- Current and Next Lesson Cards -->
                    <div class="schedule-cards">
                        <?php if ($currentLesson): ?>
                        <div class="schedule-card schedule-card-now">
                            <div class="schedule-card-label">
                                <i class="fas fa-circle" style="color: #10b981; font-size: 8px;"></i>
                                <span>Сейчас</span>
                                <span style="margin-left: auto; font-size: 12px;">
                                    <?php echo substr($currentLesson['start_time'], 0, 5); ?>–<?php echo substr($currentLesson['end_time'], 0, 5); ?>
                                </span>
                            </div>
                            <div class="schedule-card-subject">
                                <?php echo htmlspecialchars($currentLesson['subject_name']); ?>
                            </div>
                            <div class="schedule-card-meta">
                                <?php echo htmlspecialchars($currentLesson['room_number']); ?> • 
                                <?php if ($viewMode === 'teacher' && isset($currentLesson['groups']) && is_array($currentLesson['groups']) && count($currentLesson['groups']) > 0): ?>
                                    <?php echo htmlspecialchars(implode(', ', $currentLesson['groups'])); ?>
                                <?php elseif ($viewMode === 'teacher' && isset($currentLesson['group_name']) && !empty(trim($currentLesson['group_name']))): ?>
                                    <?php echo htmlspecialchars($currentLesson['group_name']); ?>
                                <?php else: ?>
                                    <?php echo htmlspecialchars($currentLesson['teacher_name']); ?>
                                <?php endif; ?>
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
                            $progressPercent = round($scheduleManager->getLessonProgress($currentLesson));
                            ?>
                            <div style="margin-top: 0.75rem;">
                                <div style="height: 6px; background: rgba(255,255,255,0.1); border-radius: 999px; overflow: hidden;">
                                    <div style="height: 100%; width: <?php echo $progressPercent; ?>%; background: linear-gradient(90deg, #10b981 0%, #059669 100%); border-radius: 999px;"></div>
                                </div>
                                <div style="font-size: 11px; color: rgba(255,255,255,0.6); margin-top: 0.25rem;">
                                    <i class="fas fa-clock" style="margin-right: 4px;"></i>до конца пары: <?php echo $remainingLabel; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="schedule-card schedule-card-next">
                            <div class="schedule-card-label">
                                <i class="fas fa-arrow-right" style="color: #60a5fa;"></i>
                                <span>Следующая</span>
                                <span style="margin-left: auto; font-size: 12px;">
                                    <?php if ($nextLesson): ?>
                                        <?php echo substr($nextLesson['start_time'], 0, 5); ?>–<?php echo substr($nextLesson['end_time'], 0, 5); ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="schedule-card-subject">
                                <?php if ($nextLesson): ?>
                                    <?php echo htmlspecialchars($nextLesson['subject_name']); ?>
                                <?php else: ?>
                                    Следующих пар нет
                                <?php endif; ?>
                            </div>
                            <div class="schedule-card-meta">
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

                    <!-- Week and Day Selectors -->
                    <div style="padding: 0 1rem; margin-bottom: 1.5rem;">
                        <div class="schedule-week-selector">
                            <button data-week="1" class="week-btn<?php echo $currentWeek == 1 ? ' active' : ''; ?>">1 неделя</button>
                            <button data-week="2" class="week-btn<?php echo $currentWeek == 2 ? ' active' : ''; ?>">2 неделя</button>
                        </div>
                        <div class="days-selector" id="daysRow">
                            <!-- Days will be inserted here by JavaScript -->
                        </div>
                    </div>

                    <!-- Day Schedule -->
                    <div class="schedule-content">
                        <div class="schedule-header">
                            <h2 class="schedule-title"><?php echo $dayNames[$currentDay]; ?></h2>
                        </div>

                        <div class="lessons-list" id="list">
                            <?php if (!empty($daySchedule)): ?>
                                <?php foreach ($daySchedule as $index => $lesson): ?>
                                    <div class="lesson-item lesson-card">
                                        <div class="lesson-number">
                                            <?php echo $lesson['lesson_number']; ?> пара • <?php echo substr($lesson['start_time'], 0, 5); ?>–<?php echo substr($lesson['end_time'], 0, 5); ?>
                                        </div>
                                        <div class="lesson-subject">
                                            <?php echo htmlspecialchars($lesson['subject_name']); ?>
                                        </div>
                                        <div class="lesson-info">
                                            <span class="lesson-badge">
                                                <?php echo htmlspecialchars($lesson['room_number']); ?>
                                            </span>
                                            <span class="lesson-badge">
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
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <div style="font-size: 3rem; margin-bottom: 1rem;">☕</div>
                                    <p style="font-size: 16px; font-weight: 600; color: #ffffff; margin-bottom: 0.5rem;">
                                        <?php if ($currentDay == 7): ?>
                                            На текущий день пар нет
                                        <?php else: ?>
                                            Сегодня пар нет
                                        <?php endif; ?>
                                    </p>
                                    <p style="font-size: 14px; color: rgba(255,255,255,0.6);">Отдохните или выберите другой день.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Selection Buttons -->
                    <div style="text-align: center; padding: 3rem 1rem; display: flex; flex-direction: column; gap: 1rem; align-items: center;">
                        <button onclick="showGroupSelectionModal()" style="padding: 0.75rem 1.5rem; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); border: none; border-radius: 16px; color: #ffffff; font-size: 16px; font-weight: 600; cursor: pointer; width: 100%; max-width: 300px;">
                            <i class="fas fa-users" style="margin-right: 8px;"></i>Выбрать группу
                        </button>
                        <button onclick="showTeacherSelectionModal()" style="padding: 0.75rem 1.5rem; background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); border: none; border-radius: 16px; color: #ffffff; font-size: 16px; font-weight: 600; cursor: pointer; width: 100%; max-width: 300px;">
                            <i class="fas fa-chalkboard-teacher" style="margin-right: 8px;"></i>Выбрать преподавателя
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Settings Button -->
    <button id="settingsBtn" class="settings-button">
        <i class="fas fa-cog" style="margin-right: 8px;"></i>Настройки
    </button>

    <!-- Group Selection Modal -->
    <div id="groupSelectionModal" class="modal-overlay">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('groupSelectionModal')">
                <i class="fas fa-times"></i>
            </button>
            <div style="text-align: center; margin-bottom: 1.5rem;">
                <div style="width: 4rem; height: 4rem; margin: 0 auto 1rem; border-radius: 9999px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, rgba(99,102,241,0.7), rgba(67,56,202,0.7)); color: #fff;">
                    <i class="fas fa-users" style="font-size: 24px;"></i>
                </div>
                <h2 style="margin: 0 0 0.5rem; color: #ffffff; font-size: 18px; font-weight: 600;">
                    <i class="fas fa-users" style="margin-right: 8px;"></i>Выберите группу
                </h2>
                <p style="color: rgba(255,255,255,0.7); font-size: 14px;">Выбор сохраняется в течение 30 дней</p>
            </div>
            <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem;">
                <input type="text" id="groupSearch" placeholder="🔍 Поиск группы..." class="search-input-modal" onkeyup="filterGroups()">
                <button type="button" onclick="switchToTeacherFromGroup()" style="padding: 0.6rem 0.8rem; background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); border: none; border-radius: 12px; color: #fff; font-size: 14px; cursor: pointer; white-space: nowrap;">
                    <i class="fas fa-chalkboard-teacher" style="margin-right: 4px;"></i>Препод
                </button>
            </div>
            <div id="groupsList" class="group-list">
                <?php if (!empty($availableGroups)): ?>
                    <?php foreach ($availableGroups as $group): ?>
                        <button onclick="selectGroup('<?php echo htmlspecialchars($group); ?>')" class="group-item" data-name="<?php echo htmlspecialchars($group); ?>">
                            <div style="width: 2rem; height: 2rem; border-radius: 10px; background: rgba(59,130,246,0.2); display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-users" style="color: #93c5fd; font-size: 16px;"></i>
                            </div>
                            <div style="flex: 1; text-align: left;">
                                <div style="color: #fff; font-weight: 600; font-size: 14px;"><?php echo htmlspecialchars($group); ?></div>
                                <div style="color: rgba(255,255,255,0.6); font-size: 12px;">Группа</div>
                            </div>
                            <span class="fav-star" data-type="group" data-name="<?php echo htmlspecialchars($group); ?>" title="В избранное" onclick="toggleFavorite(event, 'group', '<?php echo htmlspecialchars($group); ?>')" style="cursor: pointer; user-select: none; font-size: 1.2rem; line-height: 1;">★</span>
                        </button>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align: center; color: rgba(255,255,255,0.6); padding: 2rem;">Группы не найдены</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Teacher Selection Modal -->
    <div id="teacherSelectionModal" class="modal-overlay">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('teacherSelectionModal')">
                <i class="fas fa-times"></i>
            </button>
            <div style="text-align: center; margin-bottom: 1.5rem;">
                <div style="width: 4rem; height: 4rem; margin: 0 auto 1rem; border-radius: 9999px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, rgba(168,85,247,0.7), rgba(59,130,246,0.7)); color: #fff;">
                    <i class="fas fa-chalkboard-teacher" style="font-size: 24px;"></i>
                </div>
                <h2 style="margin: 0 0 0.5rem; color: #ffffff; font-size: 18px; font-weight: 600;">
                    <i class="fas fa-chalkboard-teacher" style="margin-right: 8px;"></i>Выберите преподавателя
                </h2>
                <p style="color: rgba(255,255,255,0.7); font-size: 14px;">Просмотр расписания преподавателя</p>
            </div>
            <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem;">
                <input type="text" id="teacherSearch" placeholder="🔍 Поиск преподавателя..." class="search-input-modal" onkeyup="filterTeachers()">
                <button type="button" onclick="switchToGroupFromTeacher()" style="padding: 0.6rem 0.8rem; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); border: none; border-radius: 12px; color: #fff; font-size: 14px; cursor: pointer; white-space: nowrap;">
                    <i class="fas fa-users" style="margin-right: 4px;"></i>Группы
                </button>
            </div>
            <div id="teachersList" class="teacher-list">
                <?php if (!empty($availableTeachers)): ?>
                    <?php foreach ($availableTeachers as $teacher): ?>
                        <button onclick="selectTeacher('<?php echo htmlspecialchars($teacher); ?>')" class="teacher-item" data-name="<?php echo htmlspecialchars($teacher); ?>">
                            <div style="width: 2rem; height: 2rem; border-radius: 10px; background: rgba(168,85,247,0.2); display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-chalkboard-teacher" style="color: #d8b4fe; font-size: 16px;"></i>
                            </div>
                            <div style="flex: 1; text-align: left;">
                                <div style="color: #fff; font-weight: 600; font-size: 14px;"><?php echo htmlspecialchars($teacher); ?></div>
                                <div style="color: rgba(255,255,255,0.6); font-size: 12px;">Преподаватель</div>
                            </div>
                            <span class="fav-star" data-type="teacher" data-name="<?php echo htmlspecialchars($teacher); ?>" title="В избранное" onclick="toggleFavorite(event, 'teacher', '<?php echo htmlspecialchars($teacher); ?>')" style="cursor: pointer; user-select: none; font-size: 1.2rem; line-height: 1;">★</span>
                        </button>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align: center; color: rgba(255,255,255,0.6); padding: 2rem;">Преподаватели не найдены</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Settings Modal -->
    <div id="settingsModal" class="modal-overlay">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('settingsModal')">
                <i class="fas fa-times"></i>
            </button>
            <div style="text-align: center; margin-bottom: 1.5rem;">
                <div style="width: 4rem; height: 4rem; margin: 0 auto 1rem; border-radius: 9999px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, rgba(99,102,241,0.7), rgba(67,56,202,0.7)); color: #fff;">
                    <i class="fas fa-cog" style="font-size: 24px;"></i>
                </div>
                <h2 style="margin: 0 0 0.5rem; color: #ffffff; font-size: 18px; font-weight: 600;">
                    <i class="fas fa-cog" style="margin-right: 8px;"></i>Настройки
                </h2>
                <p style="color: rgba(255,255,255,0.7); font-size: 14px;">Настройте отображение расписания</p>
                <div style="color: rgba(255,255,255,0.5); font-size: 12px; margin-top: 0.5rem;">Обновлено: <span id="updatedAtDup"><?php echo date('H:i:s'); ?></span></div>
            </div>
            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                <div class="group-item" style="cursor: pointer;" onclick="window.open('https://t.me/cowgivesmilk', '_blank')">
                    <div style="width: 2rem; height: 2rem; border-radius: 10px; background: rgba(34,197,94,0.2); display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-question" style="color: #86efac; font-size: 16px;"></i>
                    </div>
                    <div style="flex: 1;">
                        <div style="color: #fff; font-weight: 600; font-size: 14px;">Помощь</div>
                        <div style="color: rgba(255,255,255,0.6); font-size: 12px;">Написать администратору</div>
                    </div>
                </div>

                <div class="group-item" style="cursor: pointer;" onclick="showGroupSelectionModal()">
                    <div style="width: 2rem; height: 2rem; border-radius: 10px; background: rgba(59,130,246,0.2); display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-users" style="color: #93c5fd; font-size: 16px;"></i>
                    </div>
                    <div style="flex: 1;">
                        <div style="color: #fff; font-weight: 600; font-size: 14px;">Группа</div>
                        <div style="color: rgba(255,255,255,0.6); font-size: 12px;">Текущая: <?php echo $userGroup ?: 'Не выбрана'; ?></div>
                    </div>
                    <i class="fas fa-edit" style="color: rgba(255,255,255,0.6);"></i>
                </div>

                <div class="group-item" style="cursor: pointer;" onclick="showTeacherSelectionModal()">
                    <div style="width: 2rem; height: 2rem; border-radius: 10px; background: rgba(168,85,247,0.2); display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-chalkboard-teacher" style="color: #d8b4fe; font-size: 16px;"></i>
                    </div>
                    <div style="flex: 1;">
                        <div style="color: #fff; font-weight: 600; font-size: 14px;">Препод</div>
                        <div style="color: rgba(255,255,255,0.6); font-size: 12px;">Текущий: <?php echo $selectedTeacher ?: 'Не выбран'; ?></div>
                    </div>
                    <i class="fas fa-edit" style="color: rgba(255,255,255,0.6);"></i>
                </div>
            </div>
        </div>
    </div>

    <script>
        function switchToTeacherFromGroup(){
            closeModal('groupSelectionModal');
            showTeacherSelectionModal();
        }
        function switchToGroupFromTeacher(){
            closeModal('teacherSelectionModal');
            showGroupSelectionModal();
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        function showModal(modalId) {
            document.getElementById(modalId).classList.add('active');
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
            dayNames: <?php echo json_encode($dayNames, JSON_UNESCAPED_UNICODE); ?>,
            dayShortNames: <?php echo json_encode($dayShortNames, JSON_UNESCAPED_UNICODE); ?>
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
            showModal('groupSelectionModal');
            sortListByFavorites('group');
        }
        function showTeacherSelectionModal() {
            showModal('teacherSelectionModal');
            sortListByFavorites('teacher');
        }
        function selectGroup(groupName) {
            window.location.href = 'id_like_main.php?group=' + encodeURIComponent(groupName);
        }
        function selectTeacher(teacherName) {
            window.location.href = 'id_like_main.php?teacher=' + encodeURIComponent(teacherName);
        }

        // Глобальный поиск
        let searchTimeout;
        let isSearchActive = false;
        let allSearchResults = [];

        // Элементы
        const headerContainer = document.getElementById('headerContainer');
        const defaultHeader = document.getElementById('defaultHeader');
        const searchHeader = document.getElementById('searchHeader');
        const searchBtn = document.getElementById('searchBtn');
        const closeSearchBtn = document.getElementById('closeSearchBtn');
        const globalSearch = document.getElementById('globalSearch');
        const searchResults = document.getElementById('searchResults');

        // Открытие поиска
        if (searchBtn) {
            searchBtn.addEventListener('click', function() {
                defaultHeader.classList.add('hidden');
                searchHeader.classList.add('show');
                headerContainer.classList.add('search-active');
                setTimeout(() => globalSearch.focus(), 200);
            });
        }

        // Закрытие поиска
        if (closeSearchBtn) {
            closeSearchBtn.addEventListener('click', function() {
                searchHeader.classList.remove('show');
                defaultHeader.classList.remove('hidden');
                headerContainer.classList.remove('search-active');
                globalSearch.value = '';
                hideSearchResults();
                allSearchResults = [];
                if (searchResults) {
                    searchResults.innerHTML = '';
                }
            });
        }

        // Нормализация поискового запроса
        function normalizeSearchQuery(query) {
            return query
                .toLowerCase()
                .replace(/\s+/g, '')
                .replace(/[-\/:]/g, '');
        }

        // Нормализация названия группы для поиска
        function normalizeGroupName(groupName) {
            return groupName
                .toLowerCase()
                .replace(/\s+/g, '')
                .replace(/[-\/:]/g, '');
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

        // Выполнение поиска
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
                            score: 1
                        });
                    }
                });
            }

            // Сортируем результаты по релевантности
            results.sort((a, b) => b.score - a.score);
            allSearchResults = results;
            displaySearchResults();
        }

        // Отображение всех результатов с прокруткой
        function displaySearchResults() {
            if (!searchResults) return;

            if (allSearchResults.length === 0) {
                searchResults.innerHTML = '<div class="search-no-results">Ничего не найдено</div>';
                searchResults.classList.remove('is-hidden');
                isSearchActive = true;
                return;
            }

            let html = '';
            
            // Отображаем все результаты
            allSearchResults.forEach((result, index) => {
                const gradientIndex = index % 10;
                const iconClass = result.type === 'group' ? 'fa-users' : 'fa-chalkboard-teacher';
                const labelText = result.type === 'group' ? 'Группа' : 'Преподаватель';
                
                html += `
                    <div class="search-result-item" data-type="${result.type}" data-gradient="${gradientIndex}" onclick="selectFromSearch('${result.type}', '${result.name.replace(/'/g, "\\'")}')">
                        <div class="search-result-content">
                            <div class="search-result-icon ${result.type}">
                                <i class="fas ${iconClass}"></i>
                            </div>
                            <div class="search-result-text-wrapper">
                                <div class="search-result-text">${escapeHtml(result.title)}</div>
                                <div class="search-result-label">${labelText}</div>
                            </div>
                        </div>
                        <i class="fas fa-arrow-right search-result-arrow"></i>
                    </div>
                `;
            });

            searchResults.innerHTML = html;
            searchResults.classList.remove('is-hidden');
            isSearchActive = true;
        }

        // Скрытие результатов
        function hideSearchResults() {
            if (searchResults) {
                searchResults.classList.add('is-hidden');
                searchResults.innerHTML = '';
            }
            isSearchActive = false;
            allSearchResults = [];
        }

        // Выбор из результатов (глобальная функция для onclick)
        window.selectFromSearch = function(type, name) {
            if (type === 'group') {
                window.location.href = 'id_like_main.php?group=' + encodeURIComponent(name);
            } else if (type === 'teacher') {
                window.location.href = 'id_like_main.php?teacher=' + encodeURIComponent(name);
            }
        };

        // Экранирование HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Обработка ввода в поиск
        if (globalSearch) {
            globalSearch.addEventListener('input', function(e) {
                const query = e.target.value.trim();

                clearTimeout(searchTimeout);
                
                // Если поле пустое, сразу скрываем результаты
                if (!query || query.length === 0) {
                    hideSearchResults();
                    return;
                }
                
                searchTimeout = setTimeout(() => {
                    performGlobalSearch(query);
                }, 150);
            });
        }

        // Скрытие результатов при клике вне поиска
        document.addEventListener('click', function(e) {
            const header = headerContainer;
            if (header && !header.contains(e.target) && isSearchActive) {
                hideSearchResults();
            }
        });

        // Обработка Esc
        if (globalSearch) {
            globalSearch.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    if (closeSearchBtn) closeSearchBtn.click();
                }
            });
        }

        // Настройки
        const settingsBtn = document.getElementById('settingsBtn');
        if (settingsBtn) {
            settingsBtn.addEventListener('click', function() {
                showModal('settingsModal');
            });
        }

        // Закрытие модалок при клике на overlay
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) {
                    overlay.classList.remove('active');
                }
            });
        });

        // Инициализация дней недели
        function initDays() {
            const daysRow = document.getElementById('daysRow');
            if (!daysRow || !window.SCHEDULE_BOOTSTRAP) return;

            const currentDay = window.SCHEDULE_BOOTSTRAP.day;
            const dayNames = window.SCHEDULE_BOOTSTRAP.dayShortNames || {};
            const dayFullNames = window.SCHEDULE_BOOTSTRAP.dayNames || {};

            let html = '';
            for (let day = 1; day <= 6; day++) {
                const isActive = day === currentDay;
                const shortName = dayNames[day] || '';
                html += `
                    <button class="day-btn${isActive ? ' active' : ''}" data-day="${day}" onclick="changeDay(${day})">
                        ${shortName}
                    </button>
                `;
            }
            daysRow.innerHTML = html;
        }

        // Смена дня
        window.changeDay = function(day) {
            const currentWeek = window.SCHEDULE_BOOTSTRAP.week;
            window.location.href = `id_like_main.php?week=${currentWeek}&day=${day}${window.SCHEDULE_BOOTSTRAP.group ? '&group=' + encodeURIComponent(window.SCHEDULE_BOOTSTRAP.group) : ''}${window.SCHEDULE_BOOTSTRAP.teacher ? '&teacher=' + encodeURIComponent(window.SCHEDULE_BOOTSTRAP.teacher) : ''}`;
        };

        // Инициализация недель
        function initWeeks() {
            const weekButtons = document.querySelectorAll('.week-btn');
            weekButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    const week = parseInt(this.getAttribute('data-week'));
                    const currentDay = window.SCHEDULE_BOOTSTRAP.day;
                    window.location.href = `id_like_main.php?week=${week}&day=${currentDay}${window.SCHEDULE_BOOTSTRAP.group ? '&group=' + encodeURIComponent(window.SCHEDULE_BOOTSTRAP.group) : ''}${window.SCHEDULE_BOOTSTRAP.teacher ? '&teacher=' + encodeURIComponent(window.SCHEDULE_BOOTSTRAP.teacher) : ''}`;
                });
            });
        }

        // Инициализация при загрузке
        document.addEventListener('DOMContentLoaded', function() {
            initDays();
            initWeeks();
            paintFavoriteStars('group');
            paintFavoriteStars('teacher');
        });
    </script>
</body>
</html>

