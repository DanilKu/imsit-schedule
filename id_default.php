<?php
/*
                ██╗███╗   ███╗███████╗██╗████████╗    ██╗██████╗ 
                ██║████╗ ████║██╔════╝██║╚══██╔══╝    ██║██╔══██╗
                ██║██╔████╔██║███████╗██║   ██║       ██║██║  ██║
                ██║██║╚██╔╝██║╚════██║██║   ██║       ██║██║  ██║
                ██║██║ ╚═╝ ██║███████║██║   ██║       ██║██████╔╝
                ╚═╝╚═╝     ╚═╝╚══════╝╚═╝   ╚═╝       ╚═╝╚═════╝ 

    ╔══════════════════════════════════════════════════════════════════════════════╗
    ║                               Version 3.1                                    ║
    ║                                                                              ║
    ║     Расписание для всех групп и преподавателей Академии ИМСИТ                ║
    ║     Адаптивный дизайн для мобильных устройств                                ║
    ║     Современный интерфейс с цветными акцентами                               ║
    ║     Быстрая загрузка и обновление данных                                     ║
    ║     Что нового:                                                              ║
    ║     - Добавлены избранные группы и преподаватели                             ║
    ║     - Добавлена поддержка всех групп и преподавателей СПО                    ║
    ║     - Добавлено примерное окончание текущей пары с округлением до 5 минут    ║
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
    <title>ImsitID - Расписание для всех студентов и преподавателей Академии ИМСИТ</title>
    <meta name="description" content="Расписание для всех студентов и преподавателей Академии ИМСИТ. ">
    <meta name="keywords" content="Расписание, Академия ИМСИТ, Расписание для всех студентов и преподавателей Академии ИМСИТ, imsitshop, imsitid, imsit.shop, imsit.shop/shedule2, imsit.shop/shedule2.php, imsitid.ru, imsitid.com, imsitid.net, imsitid.org, imsitid.ru/schedule, imsitid.com/schedule, imsitid.net/schedule, imsitid.org/schedule, imsit, id imsit, imsitid">
    <meta name="author" content="ImsitShop">
    <meta name="robots" content="index, follow">
    <meta name="googlebot" content="index, follow">
    <meta name="bingbot" content="index, follow">
    <meta name="yandexbot" content="index, follow">
    <meta name="google" content="notranslate">
    <meta name="google" content="notranslate">
    <link rel="canonical" href="https://imsit.shop/id.php">
    <link rel="preload" as="style" href="assets/css/schedule_style.css?v=<?php echo file_exists('cache_version.txt') ? file_get_contents('cache_version.txt') : time(); ?>"/>
    <link rel="stylesheet" href="assets/css/schedule_style.css?v=<?php echo file_exists('cache_version.txt') ? file_get_contents('cache_version.txt') : time(); ?>"/>
</head>
<body>
    <div class="page-bg" aria-hidden="true">
        <div class="blob blob-a"></div>
        <div class="blob blob-b"></div>
        <div class="overlay"></div>
    </div>

    <header class="header px">
        <div class="container header__row">
            <div class="header__row" style="align-items: center; gap: 0.75rem;">
                <?php if (file_exists('assets/images/ImsitID_png_logo.png')): ?>
                    <img src="assets/images/ImsitID_png_logo.png" alt="ImsitID Logo" style="height: 40px; width: auto; display: block;">
                <?php else: ?>
                    <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #6366f1, #d946ef); border-radius: 6px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 16px;">ID</div>
                <?php endif; ?>
                <div class="subtle"><b>imsitID</b> - Расписание</div>
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
            <!-- AD Modal -->
            
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
                        <div id="nowCard" class="card card__inner">
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
                        </div>
                        <?php endif; ?>

                        <div id="nextCard" class="card card__inner">
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
                            <article class="card card--hover card__inner lesson-card" style="
                                position: relative;
                                background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 100%);
                                border: 1px solid rgba(255,255,255,0.15);
                                border-radius: 16px;
                                backdrop-filter: blur(10px);
                                -webkit-backdrop-filter: blur(10px);
                                box-shadow: 0 4px 20px rgba(0,0,0,0.1);
                                transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
                                overflow: hidden;
                            " onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 30px rgba(0,0,0,0.15)'; this.style.borderColor='rgba(255,255,255,0.25)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 20px rgba(0,0,0,0.1)'; this.style.borderColor='rgba(255,255,255,0.15)'">
                                <!-- Цветные акценты добавляются через JavaScript -->
                                
                                <!-- Светящийся эффект при наведении -->
                                <div style="
                                    position: absolute;
                                    top: 0;
                                    left: 0;
                                    right: 0;
                                    bottom: 0;
                                    background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, transparent 50%);
                                    opacity: 0;
                                    transition: opacity 0.3s ease;
                                    pointer-events: none;
                                    border-radius: 16px;
                                " class="glow-overlay"></div>
                                
                                <div style="min-width:0; padding-left: 12px; position: relative; z-index: 1;">
                                    <div class="small muted" style="
                                        color: rgba(255,255,255,0.7);
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
                                            background: rgba(255,255,255,0.15);
                                            padding: 0.25rem 0.75rem;
                                            border-radius: 12px;
                                            font-size: 0.8rem;
                                            font-weight: 500;
                                            color: #fff;
                                            border: 1px solid rgba(255,255,255,0.2);
                                        "><?php echo htmlspecialchars($lesson['room_number']); ?></span>
                                        <span style="
                                            background: rgba(255,255,255,0.1);
                                            padding: 0.25rem 0.75rem;
                                            border-radius: 12px;
                                            font-size: 0.8rem;
                                            font-weight: 500;
                                            color: rgba(255,255,255,0.9);
                                            border: 1px solid rgba(255,255,255,0.15);
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
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div id="emptyState" class="card card__inner" style="
                            text-align:center;
                            background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 100%);
                            border: 1px solid rgba(255,255,255,0.15);
                            border-radius: 16px;
                            backdrop-filter: blur(10px);
                            -webkit-backdrop-filter: blur(10px);
                            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
                        ">
                            <div class="mb-3" style="
                                margin-left:auto;
                                margin-right:auto;
                                width:3rem;
                                height:3rem;
                                border-radius:1rem;
                                display:grid;
                                place-items:center;
                                background:linear-gradient(135deg, rgba(255,255,255,0.2), rgba(255,255,255,0.1));
                                border: 1px solid rgba(255,255,255,0.2);
                                font-size: 1.5rem;
                            ">☕</div>
                            <p class="h2" style="font-size:1.1rem; color: #fff; text-shadow: 0 1px 2px rgba(0,0,0,0.3);">Сегодня пар нет</p>
                            <p class="small" style="color: rgba(255,255,255,0.7);">Отдохните или выберите другой день.</p>
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

        // Функция для добавления цветных акцентов к блокам
        function addColorAccents() {
            const lessonCards = document.querySelectorAll('.lesson-card');
            const colors = [
                '#007AFF',   // Синий
                '#5856D6',  // Фиолетовый  
                '#34C759',  // Зеленый
                '#FF9500',  // Оранжевый
                '#FF3B30',  // Красный
                '#AF52DE',  // Пурпурный
                '#FF2D92',  // Розовый
                '#5AC8FA',  // Голубой
                '#FFCC00',  // Желтый
                '#FF6B6B'   // Коралловый
            ];
            
            lessonCards.forEach(function(card, index) {
                // Проверяем, есть ли уже цветной акцент
                if (card.querySelector('.lesson-color-accent')) return;
                
                const colorAccent = document.createElement('div');
                colorAccent.className = 'lesson-color-accent';
                colorAccent.style.cssText = `
                    position: absolute !important;
                    left: 0 !important;
                    top: 0 !important;
                    bottom: 0 !important;
                    width: 4px !important;
                    background: ${colors[index % colors.length]} !important;
                    border-radius: 0 3px 3px 0 !important;
                    opacity: 0.7 !important;
                    z-index: 10 !important;
                    transition: all 0.3s ease !important;
                    pointer-events: none !important;
                `;
                
                card.style.position = 'relative';
                card.appendChild(colorAccent);
                
                // Добавляем hover эффект
                card.addEventListener('mouseenter', function() {
                    colorAccent.style.opacity = '0.9';
                    colorAccent.style.transform = 'scaleY(1.05)';
                });
                
                card.addEventListener('mouseleave', function() {
                    colorAccent.style.opacity = '0.7';
                    colorAccent.style.transform = 'scaleY(1)';
                });
            });
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Добавляем цветные акценты
            addColorAccents();
            
            // Периодически проверяем и добавляем акценты к новым блокам
            setInterval(addColorAccents, 500);
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
        
        /* Еле заметное свечение для цветных полосок */
        .lesson-card:hover {
            box-shadow: 0 8px 30px rgba(0,0,0,0.15), 0 0 0 1px rgba(255,255,255,0.1);
        }
        
        /* Стили для кнопки настроек */
        #settingsBtn {
            background: linear-gradient(135deg, #007AFF 0%, #5856D6 100%) !important;
            border: 2px solid rgba(255, 255, 255, 0.3) !important;
            color: #fff !important;
            backdrop-filter: blur(10px) !important;
            box-shadow: 0 4px 15px rgba(0, 122, 255, 0.3) !important;
            font-weight: 600 !important;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2) !important;
            position: relative !important;
            overflow: hidden !important;
            transition: all 0.3s ease !important;
        }
        
        #settingsBtn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        
        #settingsBtn:hover {
            background: linear-gradient(135deg, #0056CC 0%, #4A3FB8 100%) !important;
            border-color: rgba(255, 255, 255, 0.4) !important;
            box-shadow: 0 6px 20px rgba(0, 122, 255, 0.4) !important;
            transform: translateY(-1px) !important;
        }
        
        #settingsBtn:hover::before {
            left: 100%;
        }
        
        #settingsBtn:active {
            transform: translateY(0) !important;
            box-shadow: 0 2px 10px rgba(0, 122, 255, 0.3) !important;
        }
        
        
        /* Защита цветных акцентов от перезаписи */
        .lesson-color-accent {
            position: absolute !important;
            left: 0 !important;
            top: 0 !important;
            bottom: 0 !important;
            width: 4px !important;
            border-radius: 0 3px 3px 0 !important;
            opacity: 0.7 !important;
            z-index: 10 !important;
            transition: all 0.3s ease !important;
            pointer-events: none !important;
        }
        
        .lesson-card:hover .lesson-color-accent {
            opacity: 0.9 !important;
            transform: scaleY(1.05) !important;
        }
        
        /* Улучшенные hover эффекты */
        .lesson-card {
            position: relative;
            overflow: hidden;
        }
        
        .lesson-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: left 0.5s ease;
        }
        
        .lesson-card:hover::after {
            left: 100%;
        }
    </style>

    
</body>
</html>

<!-- ID_TEST Verison 3.1.0 -->
