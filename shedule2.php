<?php
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
    
    // 1. проверка get параметра группы
    if (isset($_GET['group']) && in_array($_GET['group'], ['Исип-05', 'Исип-06'])) {
        $userGroup = $_GET['group'];
        $viewMode = 'group';
        setcookie('selected_group', $userGroup, time() + (30 * 24 * 60 * 60), '/');
        setcookie('selected_teacher', '', time() - 3600, '/'); // удаляем выбор преподавателя
        $_SESSION['selected_group'] = $userGroup;
    }
    // 2. проверка get параметра преподавателя
    elseif (isset($_GET['teacher']) && is_numeric($_GET['teacher'])) {
        $selectedTeacher = (int)$_GET['teacher'];
        $viewMode = 'teacher';
        setcookie('selected_teacher', $selectedTeacher, time() + (30 * 24 * 60 * 60), '/');
        setcookie('selected_group', '', time() - 3600, '/'); // удаляем выбор группы
        $_SESSION['selected_teacher'] = $selectedTeacher;
    }
    // 3. проверка куки группы
    elseif (isset($_COOKIE['selected_group']) && in_array($_COOKIE['selected_group'], ['Исип-05', 'Исип-06'])) {
        $userGroup = $_COOKIE['selected_group'];
        $viewMode = 'group';
        $_SESSION['selected_group'] = $userGroup;
    }
    // 4. проверка куки преподавателя
    elseif (isset($_COOKIE['selected_teacher']) && is_numeric($_COOKIE['selected_teacher'])) {
        $selectedTeacher = (int)$_COOKIE['selected_teacher'];
        $viewMode = 'teacher';
        $_SESSION['selected_teacher'] = $selectedTeacher;
    }
    // 5. проверка группы авторизованного пользователя
    elseif ($currentUser && isset($currentUser['group']) && in_array($currentUser['group'], ['Исип-05', 'Исип-06'])) {
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
        // Получение инфы о преподе
        $stmt = $pdo->prepare("SELECT full_name, short_name FROM teachers WHERE id = ? AND is_active = 1");
        $stmt->execute([$selectedTeacher]);
        $teacherInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($teacherInfo) {
            for ($day = 1; $day <= 6; $day++) {
                $weekSchedule[$day] = $scheduleManager->getTeacherSchedule($selectedTeacher, $currentWeek, $day);
            }
            $currentLesson = $scheduleManager->getTeacherCurrentLesson($selectedTeacher);
            $nextLesson = $scheduleManager->getTeacherNextLesson($selectedTeacher);
            
        }
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="assets/icons/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="assets/icons/favicon-32x32.png" sizes="32x32" type="image/png">
    <link rel="icon" href="assets/icons/favicon-16x16.png" sizes="16x16" type="image/png">
    <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
    <meta name="theme-color" content="#0f172a">
    <title>ImsitID - Расписание для всех студентов Академии ИМСИТ</title>
    <meta name="description" content="Расписание для всех студентов и преподавателей Академии ИМСИТ. Готовые практики и курсовые работы по доступным ценам - imsitShop">
    <meta name="keywords" content="Расписание, Академия ИМСИТ, Расписание для всех студентов и преподавателей Академии ИМСИТ. Готовые практики и курсовые работы по доступным ценам - imsitShop, imsitshop, imsitid, imsit.shop, imsit.shop/shedule2, imsit.shop/shedule2.php, imsitid.ru, imsitid.com, imsitid.net, imsitid.org, imsitid.ru/schedule, imsitid.com/schedule, imsitid.net/schedule, imsitid.org/schedule">
    <meta name="author" content="ImsitShop">
    <meta name="robots" content="index, follow">
    <meta name="googlebot" content="index, follow">
    <meta name="bingbot" content="index, follow">
    <meta name="yandexbot" content="index, follow">
    <meta name="google" content="notranslate">
    <meta name="google" content="notranslate">
    <link rel="canonical" href="https://imsit.shop/shedule2.php">
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
                    <a href="login.php" class="btn">Войти</a>
                <?php endif; ?>
                <button id="refreshBtn" class="btn">Обновить</button>
            </div>
        </div>
    </header>

    <main class="px" style="padding-bottom: 6rem;">
        <section class="container space-y-6">
            <!-- Закрываемый рекламный блок -->
            <div id="adBlock" class="ad-block" style="display: none;">
                <div class="ad-block__content">
                    <button class="ad-block__close" onclick="closeAdBlock()" aria-label="Закрыть рекламу">
                        ×
                    </button>
                    <div class="ad-block__text">
                        <h3 class="ad-block__title">📢 Новости</h3>
                        <p class="ad-block__message">Подпишитесь на нас в телеграм @imsitshop </p>
                    </div>
					<div class="ad-block__progress" role="progressbar" aria-label="Авто-скрытие уведомления">
						<div class="ad-block__progress-bar" id="adProgressBar"></div>
					</div>
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
                                    <?php if ($viewMode === 'teacher' && isset($currentLesson['group_name']) && !empty(trim($currentLesson['group_name']))): ?>
                                        <?php echo htmlspecialchars($currentLesson['group_name']); ?>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($currentLesson['teacher_name']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="progress"><div id="nowProgress" class="progress__bar" style="width: <?php echo round($scheduleManager->getLessonProgress($currentLesson)); ?>%;"></div></div>
                                <div class="progress__meta"><span id="nowProgressLabel"><?php echo round($scheduleManager->getLessonProgress($currentLesson)); ?>%</span><span id="nowRemaining">—</span></div>
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
                                        <?php if ($viewMode === 'teacher' && isset($nextLesson['group_name']) && !empty(trim($nextLesson['group_name']))): ?>
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
                    <div class="mt-5" style="text-align:center;">
                        <button onclick="showGroupSelectionModal()" class="btn" style="padding:0.75rem 1.5rem; font-size:1rem;">Выбрать группу</button>
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
                        <?php foreach ($daySchedule as $lesson): ?>
                            <article class="card card--hover card__inner lesson-card">
                                <div style="min-width:0;">
                                    <div class="small muted"><?php echo $lesson['lesson_number']; ?> пара • <?php echo substr($lesson['start_time'], 0, 5); ?>–<?php echo substr($lesson['end_time'], 0, 5); ?></div>
                                    <h3 class="h2" style="margin-top:0.25rem; line-height:1.3; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;">
                                        <?php echo htmlspecialchars($lesson['subject_name']); ?>
                                    </h3>
                                    <div class="lesson-meta">
                                        <span><?php echo htmlspecialchars($lesson['room_number']); ?></span>
                                        <span>
                                            <?php if ($viewMode === 'teacher' && isset($lesson['group_name']) && !empty(trim($lesson['group_name']))): ?>
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
                        <div id="emptyState" class="card card__inner" style="text-align:center;">
                            <div class="mb-3" style="margin-left:auto;margin-right:auto;width:2.5rem;height:2.5rem;border-radius:0.75rem;display:grid;place-items:center;background:rgba(255,255,255,0.1);">☕</div>
                            <p class="h2" style="font-size:1rem;">Сегодня пар нет</p>
                            <p class="small">Отдохните или выберите другой день.</p>
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
            <div class="space-y-3">
                <button onclick="selectGroup('Исип-05')" class="group-btn">
                    <div style="display:flex; align-items:center; gap:0.75rem;">
                        <div class="group-icon" style="background: rgba(59,130,246,0.2);"><span style="color:#93c5fd;font-weight:700;">05</span></div>
                        <div>
                            <div style="color:#fff;font-weight:600;">Исип-05</div>
                            <div class="small">22-СПО-ИСИП-05</div>
                        </div>
                    </div>
                </button>
                <button onclick="selectGroup('Исип-06')" class="group-btn">
                    <div style="display:flex; align-items:center; gap:0.75rem;">
                        <div class="group-icon" style="background: rgba(168,85,247,0.2);"><span style="color:#d8b4fe;font-weight:700;">06</span></div>
                        <div>
                            <div style="color:#fff;font-weight:600;">Исип-06</div>
                            <div class="small">22-СПО-ИСИП-06</div>
                        </div>
                    </div>
                </button>
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
                            <div class="small">Текущий: Быстрый (сохранение в кеш)</div>
                        </div>
                        <button onclick="switchToNewDesign()" class="btn" style="padding:0.5rem 1rem; font-size:0.75rem;">Новый дизайн</button>
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
                            <div class="small">Текущий: <span id="currentTeacher">Не выбран</span></div>
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
            <div class="space-y-3" id="teachersList">
                <!-- Список преподавателей будет загружен через AJAX -->
                <div style="text-align:center; color:var(--muted);">Загрузка...</div>
            </div>
        </div>
    </div>

    <script>
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
        // Управление рекламным блоком
		let adDismissTimer = null;
        function showAdBlock() {
            const adBlock = document.getElementById('adBlock');
			if (!adBlock) return;
			adBlock.style.display = 'block';
			// Анимация прогресс-бара на 5 секунд
			const bar = document.getElementById('adProgressBar');
			if (bar) {
				bar.style.transition = 'none';
				bar.style.width = '0%';
				// Принудительный рефлоу для сброса transition
				void bar.offsetWidth;
				bar.style.transition = 'width 5s linear';
				requestAnimationFrame(() => { bar.style.width = '100%'; });
			}
			// Авто-закрытие через 5 секунд
			if (adDismissTimer) { clearTimeout(adDismissTimer); }
			adDismissTimer = setTimeout(() => { closeAdBlock(); }, 5000);
        }
        
        function closeAdBlock() {
            const adBlock = document.getElementById('adBlock');
			if (!adBlock) return;
			if (adDismissTimer) { clearTimeout(adDismissTimer); adDismissTimer = null; }
			adBlock.style.animation = 'slideOutRight 0.3s ease-in';
			setTimeout(() => {
				adBlock.style.display = 'none';
			}, 300);
        }
        
        // Показываем рекламный блок через 2 секунды после загрузки страницы
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(showAdBlock, 2000);
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
    </style>
</body>
</html>


