<?php
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
        // если не авторизован, то null и редирект дальше без профиля
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
    
    // определение группы
    $userGroup = null;
    
    // 1. проверка get параметра
    if (isset($_GET['group']) && in_array($_GET['group'], ['Исип-05', 'Исип-06'])) {
        $userGroup = $_GET['group'];
        // сохранение в куки
        setcookie('selected_group', $userGroup, time() + (30 * 24 * 60 * 60), '/');
        $_SESSION['selected_group'] = $userGroup;
    }
    // 2. сохранение в куки
    elseif (isset($_COOKIE['selected_group']) && in_array($_COOKIE['selected_group'], ['Исип-05', 'Исип-06'])) {
        $userGroup = $_COOKIE['selected_group'];
        $_SESSION['selected_group'] = $userGroup;
    }
    // 3. проверка группы авторизованного
    elseif ($currentUser && isset($currentUser['group']) && in_array($currentUser['group'], ['Исип-05', 'Исип-06'])) {
        $userGroup = $currentUser['group'];
        // сохранение в куки
        setcookie('selected_group', $userGroup, time() + (30 * 24 * 60 * 60), '/');
        $_SESSION['selected_group'] = $userGroup;
    }
    // 4. если не авторизован и нет группы то модалка с выбором
    else {
        $userGroup = null;
    }
    
    // валидация settings
    if ($currentWeek < 1 || $currentWeek > 2) $currentWeek = $settings['current_week'];
    if ($currentDay < 1 || $currentDay > 6) $currentDay = $settings['current_day'];

    // получаем расписание на всю неделю 
    $weekSchedule = [];
    $currentLesson = null;
    $nextLesson = null;
    
    if ($userGroup) {
        for ($day = 1; $day <= 6; $day++) {
            $weekSchedule[$day] = $scheduleManager->getSchedule($userGroup, $currentWeek, $day);
        }

        // текущая и следующая пары для выбранной группы
        $currentLesson = $scheduleManager->getCurrentLesson($userGroup);
        $nextLesson = $scheduleManager->getNextLesson($userGroup);
    }
} catch (Exception $e) {
    die('Ошибка получения данных: ' . $e->getMessage());
}

// сокращенные названия дней
$dayNames = $scheduleManager->getDayNames();
$dayShortNames = [
    1 => 'Пн', 2 => 'Вт', 3 => 'Ср', 4 => 'Чт', 5 => 'Пт', 6 => 'Сб', 7 => 'Вс'
];

// Get расписание текущего дня
$daySchedule = $weekSchedule[$currentDay] ?? [];
?>
<!DOCTYPE html>
<html lang="ru" class="h-full">
<head>
    <meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Расписание</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
  <body class="h-full bg-slate-950 text-slate-100 antialiased font-sans [font-family:Inter,ui-sans-serif,system-ui]">
    <!-- gradienti(krasivo i lagaet) -->
    <div class="fixed inset-0 -z-10 overflow-hidden">
      <div class="absolute -top-40 -right-32 h-[42rem] w-[42rem] rounded-full bg-gradient-to-br from-indigo-500/30 via-fuchsia-500/20 to-emerald-400/20 blur-3xl"></div>
      <div class="absolute -bottom-40 -left-20 h-[38rem] w-[38rem] rounded-full bg-gradient-to-tr from-purple-500/20 via-blue-500/20 to-cyan-400/20 blur-3xl"></div>
      <div class="absolute inset-0 bg-[radial-gradient(60%_50%_at_50%_0%,rgba(255,255,255,0.06),rgba(0,0,0,0)_70%)]"></div>
    </div>

    <header class="sm:px-6 sm:pt-6 pt-4 pr-4 pb-2 pl-4">
      <div class="max-w-[72rem] flex mr-auto ml-auto items-center justify-between">
        <div class="flex items-center gap-2 text-sm text-slate-300">
                <span>Обновлено: <span id="updatedAt" class="tabular-nums"><?php echo date('H:i:s'); ?></span></span>
        </div>
        <div class="flex gap-2 items-center">
                <!-- profile knopka -->
                <?php if ($currentUser): ?>
                    <a href="client_dashboard.php" class="inline-flex items-center gap-2 rounded-full bg-white/5 px-3.5 py-2 text-sm text-slate-200 ring-1 ring-white/10 backdrop-blur-md hover:bg-white/10 hover:ring-white/20 active:scale-[0.98] transition">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" data-lucide="user-round" class="lucide lucide-user-round h-4 w-4"><circle cx="12" cy="8" r="5"></circle><path d="M20 21a8 8 0 0 0-16 0"></path></svg>
            Профиль
                    </a>
                <?php else: ?>
                    <!-- login knopka если не авторизован -->
                    <a href="login.php" class="inline-flex items-center gap-2 rounded-full bg-white/5 px-3.5 py-2 text-sm text-slate-200 ring-1 ring-white/10 backdrop-blur-md hover:bg-white/10 hover:ring-white/20 active:scale-[0.98] transition">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" data-lucide="log-in" class="lucide lucide-log-in h-4 w-4"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10,17 15,12 10,7"></polyline><line x1="15" y1="12" x2="3" y2="12"></line></svg>
                        Войти
                    </a>
                <?php endif; ?>
                
            
                
                <!-- refresh knopka для обновления странички-->
                <button id="refreshBtn" class="inline-flex items-center gap-2 rounded-full bg-white/5 px-3.5 py-2 text-sm text-slate-200 ring-1 ring-white/10 backdrop-blur-md hover:bg-white/10 active:scale-[0.98] transition">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" data-lucide="refresh-ccw" class="lucide lucide-refresh-ccw h-4 w-4"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"></path><path d="M16 16h5v5"></path></svg>
            Обновить
          </button>
        </div>
      </div>
    </header>

    <main class="px-4 pb-24 sm:px-6">
      <section class="mx-auto max-w-[72rem] space-y-6">
            <!-- основная инфа -->
        <div class="relative overflow-hidden rounded-2xl border border-white/10 bg-white/5 backdrop-blur-xl shadow-2xl ring-1 ring-white/10">
          <div class="absolute inset-x-0 -top-24 h-48 bg-gradient-to-b from-white/10 to-transparent"></div>
          <div class="p-5 sm:p-7">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div class="relative grid h-12 w-12 place-items-center rounded-xl bg-gradient-to-br from-indigo-500/70 to-fuchsia-500/70 text-white ring-1 ring-white/20 shadow-lg">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" data-lucide="users" class="lucide lucide-users h-6 w-6"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><path d="M16 3.128a4 4 0 0 1 0 7.744"></path><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><circle cx="9" cy="7" r="4"></circle></svg>
                </div>
                            <!-- кнопка для открытия модалки выбора группы-->
                <div>
                                <h1 class="text-[22px] sm:text-2xl font-semibold tracking-tight">
                                    <?php echo $userGroup ? htmlspecialchars($userGroup) : 'Выберите группу'; ?>
                                </h1>
                                <p id="contextLine" class="text-sm text-slate-300">
                                    <?php if ($userGroup): ?>
                                        <?php echo $currentWeek; ?> неделя • <?php echo $dayNames[$currentDay]; ?>
                                    <?php else: ?>
                                        Нажмите на кнопку ниже для выбора группы
                                    <?php endif; ?>
                                </p>
                </div>
              </div>
              <div class="hidden sm:flex items-center gap-2 text-sm text-slate-300">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" data-lucide="dot" class="lucide lucide-dot h-4 w-4"><circle cx="12.1" cy="12.1" r="1"></circle></svg>
                            <span>Обновлено: <span id="updatedAt" class="tabular-nums"><?php echo date('H:i:s'); ?></span></span>
              </div>
            </div>

                    <!-- карточки текущей и следующей пары -->
                    <?php if ($userGroup): ?>
                    <div class="mt-5 grid grid-cols-1 <?php echo $currentLesson ? 'sm:grid-cols-2' : 'sm:grid-cols-1'; ?> gap-4">
              <!-- Now -->
                        <?php if ($currentLesson): ?>
              <div id="nowCard" class="relative rounded-xl border border-white/10 bg-white/5 p-4 ring-1 ring-white/10">
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-2">
                    <div class="h-8 w-8 grid place-items-center rounded-lg bg-emerald-500/20 text-emerald-300 ring-1 ring-emerald-400/30">
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" data-lucide="play-circle" class="lucide lucide-play-circle h-4 w-4"><path d="M9 9.003a1 1 0 0 1 1.517-.859l4.997 2.997a1 1 0 0 1 0 1.718l-4.997 2.997A1 1 0 0 1 9 14.996z"></path><circle cx="12" cy="12" r="10"></circle></svg>
                    </div>
                    <span class="text-sm font-medium text-emerald-300">Сейчас</span>
                  </div>
                                <span id="nowTimeRange" class="text-xs text-slate-300">
                                    <?php echo substr($currentLesson['start_time'], 0, 5); ?>–<?php echo substr($currentLesson['end_time'], 0, 5); ?>
                                </span>
                </div>
                <div class="mt-3">
                                <div id="nowTitle" class="text-base font-medium tracking-tight">
                                    <?php echo htmlspecialchars($currentLesson['subject_name']); ?>
                                </div>
                                <div id="nowMeta" class="text-sm text-slate-300 mt-1">
                                    <?php echo htmlspecialchars($currentLesson['room_number']); ?> • <?php echo htmlspecialchars($currentLesson['teacher_name']); ?>
                                </div>
                </div>
                <div class="mt-4">
                  <div class="h-2.5 w-full rounded-full bg-white/10 overflow-hidden">
                                    <div id="nowProgress" class="h-full w-0 rounded-full bg-gradient-to-r from-emerald-400 to-teal-400 transition-[width] duration-500" style="width: <?php echo round($scheduleManager->getLessonProgress($currentLesson)); ?>%;"></div>
                  </div>
                  <div class="mt-1.5 flex justify-between text-xs text-slate-400">
                                    <span id="nowProgressLabel"><?php echo round($scheduleManager->getLessonProgress($currentLesson)); ?>%</span>
                    <span id="nowRemaining" class="">—</span>
                  </div>
                </div>
              </div>
                        <?php endif; ?>

                        <!-- след пара -->
              <div id="nextCard" class="relative rounded-xl border border-white/10 bg-white/5 p-4 ring-1 ring-white/10">
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-2">
                    <div class="h-8 w-8 grid place-items-center rounded-lg bg-sky-500/20 text-sky-300 ring-1 ring-sky-400/30">
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" data-lucide="chevrons-right" class="lucide lucide-chevrons-right h-4 w-4"><path d="m6 17 5-5-5-5"></path><path d="m13 17 5-5-5-5"></path></svg>
                    </div>
                    <span class="text-sm font-medium text-sky-300">Следующая</span>
                  </div>
                                <span id="nextTimeRange" class="text-xs text-slate-300">
                                    <?php if ($nextLesson): ?>
                                        <?php echo substr($nextLesson['start_time'], 0, 5); ?>–<?php echo substr($nextLesson['end_time'], 0, 5); ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </span>
                </div>
                <div class="mt-3">
                                <div id="nextTitle" class="text-base font-medium tracking-tight">
                                    <?php if ($nextLesson): ?>
                                        <?php echo htmlspecialchars($nextLesson['subject_name']); ?>
                                    <?php else: ?>
                                        Следующих пар нет
                                    <?php endif; ?>
                                </div>
                                <!-- nerabotaet nihuya -->
                                <div id="nextMeta" class="mt-1 text-sm text-slate-300">
                                    <?php if ($nextLesson): ?>
                                        <?php echo htmlspecialchars($nextLesson['room_number']); ?> • <?php echo htmlspecialchars($nextLesson['teacher_name']); ?>
                                    <?php else: ?>
                                        Расписание на сегодня завершено
                                    <?php endif; ?>
                                </div>
                </div>
              </div>
            </div>
                    <?php else: ?>
                    <!-- кнопка выбора группы -->
                    <div class="mt-5 text-center">
                        <button onclick="showGroupSelectionModal()" class="inline-flex items-center gap-2 rounded-full bg-white/10 px-6 py-3 text-lg text-slate-200 ring-1 ring-white/10 backdrop-blur-md hover:bg-white/20 hover:ring-white/20 active:scale-[0.98] transition">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" data-lucide="users" class="lucide lucide-users h-5 w-5"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><path d="M16 3.128a4 4 0 0 1 0 7.744"></path><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><circle cx="9" cy="7" r="4"></circle></svg>
                            Выбрать группу
                        </button>
                    </div>
                    <?php endif; ?>

                    <!-- controls -->
                    <?php if ($userGroup): ?>
            <div class="mt-6 flex flex-col gap-4 sm:flex-row sm:items-center justify-center align-items:center display:flex; flex-direction:column; gap:1rem;">
                        <!-- неделя segmented -->
              <div class="inline-flex justify-center align-items:center display:flex; flex-direction:column; gap:1rem items-center rounded-full bg-white/5 p-1 ring-1 ring-white/10 backdrop-blur-md">
                            <button data-week="1" class="seg-week px-3.5 py-1.5 text-sm rounded-full text-slate-300 hover:text-white transition ring-1 ring-white/10 <?php echo $currentWeek == 1 ? 'bg-white/20 text-white' : ''; ?>">1 неделя</button>
                            <button data-week="2" class="seg-week px-3.5 py-1.5 text-sm rounded-full text-slate-300 hover:text-white transition ring-1 ring-white/10 <?php echo $currentWeek == 2 ? 'bg-white/20 text-white' : ''; ?>">2 неделя</button>
              </div>
              

                        <!-- дни scroller -->
              <div class="overflow-x-auto -mx-1">
                <div class="flex items-center gap-2 px-1">
                                <div id="daysRow" class="flex items-center gap-2">
                                    <?php for ($day = 1; $day <= 6; $day++): ?>
                                        <button type="button" data-day="<?php echo $day; ?>" class="day-btn relative inline-flex items-center justify-center rounded-full px-3 py-1.5 text-sm text-slate-300 ring-1 ring-white/10 bg-white/5 backdrop-blur-md hover:text-white transition <?php echo $day == $currentDay ? 'bg-white/20 text-white ring-white/20' : ''; ?>">
                                            <span class="font-medium"><?php echo $dayShortNames[$day]; ?></span>
                                        </button>
                                    <?php endfor; ?>
                </div>
              </div>
            </div>
                    </div>
                    <?php endif; ?>
          </div>
        </div>

            <!-- расписание дня -->
            <?php if ($userGroup): ?>
        <section class="space-y-3" aria-labelledby="dayTitle">
          <div class="flex items-center gap-2 px-1">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" data-lucide="calendar-clock" class="lucide lucide-calendar-clock h-5 w-5 text-slate-300"><path d="M16 14v2.2l1.6 1"></path><path d="M16 2v4"></path><path d="M21 7.5V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h3.5"></path><path d="M3 10h5"></path><path d="M8 2v4"></path><circle cx="16" cy="16" r="6"></circle></svg>
                    <h2 id="dayTitle" class="text-xl font-semibold tracking-tight"><?php echo $dayNames[$currentDay]; ?></h2>
                </div>
          
                <div id="list" class="grid grid-cols-1 gap-3">
                    <?php if (!empty($daySchedule)): ?>
                        <?php foreach ($daySchedule as $lesson): ?>
            <article class="group relative overflow-hidden rounded-xl border border-white/10 bg-white/5 p-4 ring-1 ring-white/10 backdrop-blur-xl transition hover:bg-white/[0.08]">
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                                        <div class="text-xs text-slate-400"><?php echo $lesson['lesson_number']; ?> пара</div>
                                        <h3 class="mt-0.5 text-base font-medium tracking-tight truncate"><?php echo htmlspecialchars($lesson['subject_name']); ?></h3>
                  <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-slate-300">
                    <span class="inline-flex items-center gap-1">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" data-lucide="map-pin" class="lucide lucide-map-pin h-4 w-4"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                                <?php echo htmlspecialchars($lesson['room_number']); ?>
                    </span>
                    <span class="inline-flex items-center gap-1">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" data-lucide="user" class="lucide lucide-user h-4 w-4"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                                <?php echo htmlspecialchars($lesson['teacher_name']); ?>
                    </span>
                  </div>
                </div>
                <div class="shrink-0 text-right">
                  <div class="inline-flex items-center rounded-full bg-white/5 px-2.5 py-1 text-xs text-slate-200 ring-1 ring-white/10">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" data-lucide="clock-3" class="lucide lucide-clock-3 mr-1 h-3.5 w-3.5"><path d="M12 6v6h4"></path><circle cx="12" cy="12" r="10"></circle></svg>
                                            <?php echo substr($lesson['start_time'], 0, 5); ?>–<?php echo substr($lesson['end_time'], 0, 5); ?>
                  </div>
                </div>
              </div>
            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div id="emptyState" class="rounded-2xl border border-white/10 bg-white/5 p-8 text-center ring-1 ring-white/10">
              <div class="mx-auto mb-3 grid h-10 w-10 place-items-center rounded-xl bg-white/10 text-slate-200">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" data-lucide="coffee" class="lucide lucide-coffee h-5 w-5"><path d="M10 2v2"></path><path d="M14 2v2"></path><path d="M16 8a1 1 0 0 1 1 1v8a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V9a1 1 0 0 1 1-1h14a4 4 0 1 1 0 8h-1"></path><path d="M6 2v2"></path></svg>
              </div>
              <p class="text-base font-medium tracking-tight">Сегодня пар нет</p>
              <p class="mt-1 text-sm text-slate-300">Отдохните или выберите другой день.</p>
            </div>
                    <?php endif; ?>
          </div>
        </section>
            <?php endif; ?>
      </section>
    </main>

    <!-- кнопка настроек -->
    <div class="fixed bottom-4 left-1/2 transform -translate-x-1/2 z-50">
        <button onclick="showSettingsModal()" class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-2 text-sm text-slate-200 ring-1 ring-white/10 backdrop-blur-md hover:bg-white/20 hover:ring-white/20 active:scale-[0.98] transition">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" data-lucide="settings" class="lucide lucide-settings h-4 w-4"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1 1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
            Настройки
        </button>
    </div>

    <!-- модалка выбора группы -->
    <div id="groupSelectionModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4" style="display: none;">
        <div class="bg-slate-800/95 backdrop-blur-xl border border-white/10 rounded-2xl p-8 max-w-md w-full shadow-2xl">
            <div class="text-center mb-6">
                <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gradient-to-br from-indigo-500/70 to-fuchsia-500/70 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><path d="M16 3.128a4 4 0 0 1 0 7.744"></path><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><circle cx="9" cy="7" r="4"></circle></svg>
                </div>
                <h2 class="text-2xl font-bold text-white mb-2">Выберите группу</h2>
                <p class="text-slate-300">Выбор сохраняется в течение 30 дней</p>
            </div>
            
            <div class="space-y-3">
                <button onclick="selectGroup('Исип-05')" class="w-full p-4 rounded-xl border border-white/10 bg-white/5 hover:bg-white/10 transition text-left group">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-blue-500/20 flex items-center justify-center group-hover:bg-blue-500/30 transition">
                            <span class="text-blue-300 font-bold">05</span>
                        </div>
                        <div>
                            <div class="text-white font-semibold">Исип-05</div>
                            <div class="text-slate-400 text-sm">22-СПО-ИСИП-05</div>
                        </div>
                    </div>
                </button>
                
                <button onclick="selectGroup('Исип-06')" class="w-full p-4 rounded-xl border border-white/10 bg-white/5 hover:bg-white/10 transition text-left group">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-purple-500/20 flex items-center justify-center group-hover:bg-purple-500/30 transition">
                            <span class="text-purple-300 font-bold">06</span>
                        </div>
                        <div>
                            <div class="text-white font-semibold">Исип-06</div>
                            <div class="text-slate-400 text-sm">22-СПО-ИСИП-06</div>
                        </div>
                    </div>
                </button>
            </div>
        </div>
    </div>

    <!-- модалка настроек -->
    <div id="settingsModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4" style="display: none;">
        <div class="bg-slate-800/95 backdrop-blur-xl border border-white/10 rounded-2xl p-8 max-w-md w-full shadow-2xl">
            <div class="text-center mb-6">
                <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gradient-to-br from-indigo-500/70 to-fuchsia-500/70 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1 1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                </div>
                <h2 class="text-2xl font-bold text-white mb-2">Настройки</h2>
                <p class="text-slate-300">Настройте отображение расписания</p>
            </div>
            
            <div class="space-y-3">
                <!-- Переключение дизайна -->
                <div class="w-full p-4 rounded-xl border border-white/10 bg-white/5 hover:bg-white/10 transition text-left group">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-emerald-500/20 flex items-center justify-center group-hover:bg-emerald-500/30 transition">
                            <span class="text-emerald-300 font-bold">🎨</span>
                        </div>
                        <div class="flex-1">
                            <div class="text-white font-semibold">Дизайн</div>
                            <div class="text-slate-400 text-sm">Текущий: Новый</div>
                        </div>
                        <button onclick="switchToFastDesign()" class="inline-flex items-center gap-2 rounded-full bg-white/5 px-3.5 py-2 text-sm text-slate-200 ring-1 ring-white/10 backdrop-blur-md hover:bg-white/10 hover:ring-white/20 active:scale-[0.98] transition">
                            Быстрый дизайн
                        </button>
                    </div>
                </div>

                <!-- Выбор группы -->
                <div class="w-full p-4 rounded-xl border border-white/10 bg-white/5 hover:bg-white/10 transition text-left group">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-blue-500/20 flex items-center justify-center group-hover:bg-blue-500/30 transition">
                            <span class="text-blue-300 font-bold">👥</span>
                        </div>
                        <div class="flex-1">
                            <div class="text-white font-semibold">Группа</div>
                            <div class="text-slate-400 text-sm">Текущая: <?php echo $userGroup ?: 'Не выбрана'; ?></div>
                        </div>
                        <button onclick="showGroupSelectionModal()" class="inline-flex items-center gap-2 rounded-full bg-white/5 px-3.5 py-2 text-sm text-slate-200 ring-1 ring-white/10 backdrop-blur-md hover:bg-white/10 hover:ring-white/20 active:scale-[0.98] transition">
                            Сменить
                        </button>
                    </div>
                </div>

                <!-- Выбор преподавателя -->
                <div class="w-full p-4 rounded-xl border border-white/10 bg-white/5 hover:bg-white/10 transition text-left group">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-purple-500/20 flex items-center justify-center group-hover:bg-purple-500/30 transition">
                            <span class="text-purple-300 font-bold">👨‍🏫</span>
                        </div>
                        <div class="flex-1">
                            <div class="text-white font-semibold">Преподаватель</div>
                            <div class="text-slate-400 text-sm">Текущий: <span id="currentTeacherNew">Не выбран</span></div>
                        </div>
                        <button onclick="showTeacherSelectionModal()" class="inline-flex items-center gap-2 rounded-full bg-white/5 px-3.5 py-2 text-sm text-slate-200 ring-1 ring-white/10 backdrop-blur-md hover:bg-white/10 hover:ring-white/20 active:scale-[0.98] transition">
                            Выбрать
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- модалка выбора преподавателя -->
    <div id="teacherSelectionModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4" style="display: none;">
        <div class="bg-slate-800/95 backdrop-blur-xl border border-white/10 rounded-2xl p-8 max-w-md w-full shadow-2xl">
            <div class="text-center mb-6">
                <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gradient-to-br from-purple-500/70 to-pink-500/70 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                </div>
                <h2 class="text-2xl font-bold text-white mb-2">Выберите преподавателя</h2>
                <p class="text-slate-300">Просмотр расписания преподавателя</p>
            </div>
            <div class="space-y-3" id="teachersListNew">
                <!-- Список преподавателей будет загружен через AJAX -->
                <div class="text-center text-slate-400">Загрузка...</div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        // какашки
      const $ = (s, el = document) => el.querySelector(s);
      const $$ = (s, el = document) => Array.from(el.querySelectorAll(s));

      const DAYS = [
        { key: "Пн", full: "Понедельник" },
        { key: "Вт", full: "Вторник" },
        { key: "Ср", full: "Среда" },
        { key: "Чт", full: "Четверг" },
        { key: "Пт", full: "Пятница" },
        { key: "Сб", full: "Суббота" },
        { key: "Вс", full: "Воскресенье" },
      ];

      function pad(n) { return String(n).padStart(2, "0"); }

      function nowString() {
        const d = new Date();
        return `${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
      }

        // стейт
        const state = {
            week: <?php echo $currentWeek; ?>,
            day: <?php echo $currentDay; ?>,
            group: '<?php echo $userGroup ?: ''; ?>'
        };

        // куки для сохранения группы
        function setCookie(name, value, days) {
            const expires = new Date();
            expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
            document.cookie = name + '=' + value + ';expires=' + expires.toUTCString() + ';path=/';
        }

        function getCookie(name) {
            const nameEQ = name + "=";
            const ca = document.cookie.split(';');
            for (let i = 0; i < ca.length; i++) {
                let c = ca[i];
                while (c.charAt(0) === ' ') c = c.substring(1, c.length);
                if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
            }
            return null;
        }

        function updateGroupInState(newGroup) {
            state.group = newGroup;
            setCookie('selected_group', newGroup, 30);
        }

        // функции выбора группы
        function showGroupSelectionModal() {
            // Закрываем модалку настроек если она открыта
            const settingsModal = document.getElementById('settingsModal');
            if (settingsModal && settingsModal.style.display === 'flex') {
                settingsModal.style.display = 'none';
            }
            
            document.getElementById('groupSelectionModal').style.display = 'flex';
        }

        function selectGroup(group) {
            updateGroupInState(group);
            // перенаправляем на ту же страницу с выбранной группой
            window.location.href = `?week=${state.week}&day=${state.day}&group=${group}`;
        }

        // кнопки дней
      function buildDays() {
        const row = $("#daysRow");
        row.innerHTML = "";
        DAYS.forEach((d, idx) => {
          if (idx === 6) return; // убираем воскресенье из выбора
          const b = document.createElement("button");
          b.type = "button";
                b.dataset.day = idx + 1; // PHP использует 1-7, JS 0-6
                b.className = "day-btn relative inline-flex items-center justify-center rounded-full px-3 py-1.5 text-sm text-slate-300 ring-1 ring-white/10 bg-white/5 backdrop-blur-md hover:text-white transition";
          b.innerHTML = `<span class="font-medium">${d.key}</span>`;
          b.addEventListener("click", () => {
                    state.day = idx + 1;
                    updateGroupInState(state.group);
                    window.location.href = `?week=${state.week}&day=${state.day}&group=${state.group}`;
          });
          row.appendChild(b);
        });
      }

      function setActiveSegments() {
        $$(".seg-week").forEach(el => {
          const active = Number(el.dataset.week) === state.week;
          el.classList.toggle("bg-white/20", active);
          el.classList.toggle("text-white", active);
        });

        $$(".day-btn").forEach((el) => {
          const active = Number(el.dataset.day) === state.day;
          el.classList.toggle("bg-white/20", active);
          el.classList.toggle("text-white", active);
          el.classList.toggle("ring-white/20", active);
        });
      }

      function scrollIntoViewIfNeeded(el, container) {
        const elLeft = el.offsetLeft, elRight = elLeft + el.offsetWidth;
        const cLeft = container.scrollLeft, cRight = cLeft + container.clientWidth;
        if (elLeft < cLeft) container.scrollTo({ left: elLeft - 16, behavior: "smooth" });
        else if (elRight > cRight) container.scrollTo({ left: elRight - container.clientWidth + 16, behavior: "smooth" });
      }

        function renderContext() {
            const weekLabel = `${state.week} неделя`;
            const dayLabel = DAYS[state.day - 1].full; // PHP юзает 1-7, а нам это нахui ненужон
            $("#contextLine").textContent = `${weekLabel} • ${dayLabel}`;
        }

        function render() {
            setActiveSegments();
            renderContext();
            updateScheduleData();
            updateCurrentLesson();
            $("#updatedAt").textContent = nowString();
            lucide.createIcons();
        }

        function tick() {
            $("#updatedAt").textContent = nowString();
            // обновляем текущую пару каждую минуту(пиздеж, не работает)
            if (new Date().getSeconds() === 0) {
                updateCurrentLesson();
            }
        }

        // Настройки
        function showSettingsModal() {
            document.getElementById('settingsModal').style.display = 'flex';
        }

        function switchToFastDesign() {
            setCookie('design_preference', 'fast', 365);
            window.location.href = 'shedule2.php';
        }

        // Выбор преподавателя
        function showTeacherSelectionModal() {
            // Закрываем модалку настроек если она открыта
            const settingsModal = document.getElementById('settingsModal');
            if (settingsModal && settingsModal.style.display === 'flex') {
                settingsModal.style.display = 'none';
            }
            
            const modal = document.getElementById('teacherSelectionModal');
            modal.style.display = 'flex';
            loadTeachersNew();
        }

        function selectTeacherNew(teacherId, teacherName) {
            setCookie('selected_teacher', teacherId, 30);
            setCookie('selected_teacher_name', teacherName, 30);
            document.getElementById('currentTeacherNew').textContent = teacherName;
            document.getElementById('teacherSelectionModal').style.display = 'none';
            window.location.href = `?week=${state.week}&day=${state.day}&teacher=${teacherId}`;
        }

        async function loadTeachersNew() {
            try {
                const response = await fetch('api/get_teachers.php');
                const data = await response.json();
                
                const list = document.getElementById('teachersListNew');
                if (data.success && data.teachers) {
                    list.innerHTML = data.teachers.map(teacher => `
                        <button onclick="selectTeacherNew(${teacher.id}, '${teacher.full_name}')" class="w-full p-4 rounded-xl border border-white/10 bg-white/5 hover:bg-white/10 transition text-left group">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-purple-500/20 flex items-center justify-center group-hover:bg-purple-500/30 transition">
                                    <span class="text-purple-300 font-bold">👨‍🏫</span>
                                </div>
                                <div>
                                    <div class="text-white font-semibold">${teacher.full_name}</div>
                                    <div class="text-slate-400 text-sm">${teacher.department || 'Преподаватель'}</div>
                                </div>
                            </div>
                        </button>
                    `).join('');
                } else {
                    list.innerHTML = '<div class="text-center text-slate-400">Преподаватели не найдены</div>';
                }
            } catch (error) {
                console.error('Ошибка загрузки преподавателей:', error);
                document.getElementById('teachersListNew').innerHTML = '<div class="text-center text-slate-400">Ошибка загрузки</div>';
            }
        }

        async function updateCurrentLesson() {
            try {
                const response = await fetch(`api/get_current_lesson.php?group=${encodeURIComponent(state.group)}`);
                const data = await response.json();
                
                const nowCard = $("#nowCard");
                const cardsContainer = nowCard?.parentElement;
                
                if (data.success && data.currentLesson) {
                    // есть текущая пара - чиназес, показываем блок
                    if (!nowCard) {
                        // создаем блок "сейчас" если его нет, ну и показываем
                        const nowCardHTML = `
                            <div id="nowCard" class="relative rounded-xl border border-white/10 bg-white/5 p-4 ring-1 ring-white/10">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <div class="h-8 w-8 grid place-items-center rounded-lg bg-emerald-500/20 text-emerald-300 ring-1 ring-emerald-400/30">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" data-lucide="play-circle" class="lucide lucide-play-circle h-4 w-4"><path d="M9 9.003a1 1 0 0 1 1.517-.859l4.997 2.997a1 1 0 0 1 0 1.718l-4.997 2.997A1 1 0 0 1 9 14.996z"></path><circle cx="12" cy="12" r="10"></circle></svg>
                                        </div>
                                        <span class="text-sm font-medium text-emerald-300">Сейчас</span>
                                    </div>
                                    <span id="nowTimeRange" class="text-xs text-slate-300">
                                        ${data.currentLesson.start_time.substring(0, 5)}–${data.currentLesson.end_time.substring(0, 5)}
                                    </span>
                                </div>
                                <div class="mt-3">
                                    <div id="nowTitle" class="text-base font-medium tracking-tight">
                                        ${data.currentLesson.subject_name}
                                    </div>
                                    <div id="nowMeta" class="text-sm text-slate-300 mt-1">
                                        ${data.currentLesson.room_number} • ${data.currentLesson.teacher_name}
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="h-2.5 w-full rounded-full bg-white/10 overflow-hidden">
                                        <div id="nowProgress" class="h-full w-0 rounded-full bg-gradient-to-r from-emerald-400 to-teal-400 transition-[width] duration-500" style="width: ${data.progress}%;"></div>
                                    </div>
                                    <div class="mt-1.5 flex justify-between text-xs text-slate-400">
                                        <span id="nowProgressLabel">${data.progress}%</span>
                                        <span id="nowRemaining">—</span>
                                    </div>
                                </div>
                            </div>
                        `;
                        
                        if (cardsContainer) {
                            cardsContainer.insertAdjacentHTML('afterbegin', nowCardHTML);
                            cardsContainer.className = 'mt-5 grid grid-cols-1 sm:grid-cols-2 gap-4';
                        }
                    } else {
                        // обновляем существующий блок
                        $("#nowTimeRange").textContent = `${data.currentLesson.start_time.substring(0, 5)}–${data.currentLesson.end_time.substring(0, 5)}`;
                        $("#nowTitle").textContent = data.currentLesson.subject_name;
                        $("#nowMeta").textContent = `${data.currentLesson.room_number} • ${data.currentLesson.teacher_name}`;
                        $("#nowProgress").style.width = `${data.progress}%`;
                        $("#nowProgressLabel").textContent = `${data.progress}%`;
                    }
                } else {
                    // нет текущей пары - скрываем блок
                    if (nowCard) {
                        nowCard.remove();
                        if (cardsContainer) {
                            cardsContainer.className = 'mt-5 grid grid-cols-1 sm:grid-cols-1 gap-4';
                        }
                    }
                }
            } catch (error) {
                console.error('Ошибка обновления текущей пары:', error);
            }
        }

        function updateScheduleData() {
            // обновляем контекст
            renderContext();
            
            // обновляем заголовок дня
            $("#dayTitle").textContent = DAYS[state.day - 1].full;
            
            // обновляем список пар для текущего дня
            const dayLessons = <?php echo json_encode($daySchedule); ?>;
        const list = $("#list");
        const empty = $("#emptyState");

            if (!dayLessons || dayLessons.length === 0) {
          list.innerHTML = "";
          empty.classList.remove("hidden");
          return;
        }
            
        empty.classList.add("hidden");

            list.innerHTML = dayLessons.map((lesson) => {
          return `
            <article class="group relative overflow-hidden rounded-xl border border-white/10 bg-white/5 p-4 ring-1 ring-white/10 backdrop-blur-xl transition hover:bg-white/[0.08]">
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                                <div class="text-xs text-slate-400">${lesson.lesson_number} пара</div>
                                <h3 class="mt-0.5 text-base font-medium tracking-tight truncate">${lesson.subject_name}</h3>
                  <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-slate-300">
                    <span class="inline-flex items-center gap-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" data-lucide="map-pin" class="lucide lucide-map-pin h-4 w-4"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                        ${lesson.room_number}
                    </span>
                    <span class="inline-flex items-center gap-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" data-lucide="user" class="lucide lucide-user h-4 w-4"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                        ${lesson.teacher_name}
                    </span>
                  </div>
                </div>
                <div class="shrink-0 text-right">
                  <div class="inline-flex items-center rounded-full bg-white/5 px-2.5 py-1 text-xs text-slate-200 ring-1 ring-white/10">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" data-lucide="clock-3" class="lucide lucide-clock-3 mr-1 h-3.5 w-3.5"><path d="M12 6v6h4"></path><circle cx="12" cy="12" r="10"></circle></svg>
                                    ${lesson.start_time.substring(0, 5)}–${lesson.end_time.substring(0, 5)}
                  </div>
                </div>
              </div>
            </article>
          `;
        }).join("");

        lucide.createIcons();
      }

        async function refreshSchedule() {
            const refreshBtn = $("#refreshBtn");
            const originalText = refreshBtn.innerHTML;
            refreshBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" data-lucide="refresh-ccw" class="lucide lucide-refresh-ccw h-4 w-4 animate-spin"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"></path><path d="M16 16h5v5"></path></svg> Обновление...';
            refreshBtn.disabled = true;
            
            try {
                const response = await fetch('api/update_schedule_time.php');
                const data = await response.json();
                
                if (data.success) {
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                }
            } catch (error) {
                console.error('Ошибка обновления:', error);
            } finally {
                refreshBtn.innerHTML = originalText;
                refreshBtn.disabled = false;
            }
        }

        // Закрытие модальных окон
        function setupModalCloseHandlers() {
            const modals = ['groupSelectionModal', 'settingsModal', 'teacherSelectionModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (modal) {
                    modal.addEventListener('click', function(e) {
                        if (e.target === this) {
                            this.style.display = 'none';
                        }
                    });
                }
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    modals.forEach(modalId => {
                        const modal = document.getElementById(modalId);
                        if (modal && modal.style.display === 'flex') {
                            modal.style.display = 'none';
                        }
                    });
                }
            });
        }

        // init
        document.addEventListener("DOMContentLoaded", () => {
            // показываем модалку выбора группы если нет группы
            if (!state.group) {
                showGroupSelectionModal();
                return;
            }
            
            // юай для недель
        $$(".seg-week").forEach(el => {
          el.addEventListener("click", () => {
            state.week = Number(el.dataset.week);
                    updateGroupInState(state.group);
                    window.location.href = `?week=${state.week}&day=${state.day}&group=${state.group}`;
          });
        });

        buildDays();
            
            // выделяем сегодняшний день
        setTimeout(() => {
          const todayBtn = document.querySelector(`.day-btn[data-day="${state.day}"]`);
          const row = document.querySelector("#daysRow");
          if (todayBtn && row) {
                    scrollIntoViewIfNeeded(todayBtn, row);
          }
        }, 50);

            $("#refreshBtn").addEventListener("click", refreshSchedule);

            // Настройка закрытия модальных окон
            setupModalCloseHandlers();

        render();
        lucide.createIcons();

        setInterval(tick, 1000);
            
            // автообновление каждые 5 минут(уже работает вроде)
            setInterval(async () => {
                try {
                    await fetch('api/auto_update_schedule_time.php');
                } catch (error) {
                    console.error('Ошибка автообновления:', error);
                }
            }, 300000); // 5 минут кто не шарит в матеше=300 секунд=300к миллисекунд
      });
    </script>
</body>
</html>