<?php
// Запускаем сессию СРАЗУ
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/auth.php';
require_once 'config/database.php';
require_once 'includes/ScheduleManager.php';
date_default_timezone_set('Europe/Moscow');

requireAdmin();

$scheduleManager = new ScheduleManager($pdo);

// Обработка POST запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_settings':
                $week = (int)$_POST['current_week'];
                $day = (int)$_POST['current_day'];
                $time = $_POST['current_time'];
                $scheduleManager->updateSettings($week, $day, $time);
                $success = 'Настройки обновлены!';
                break;
                
            case 'add_lesson':
                $data = [
                    'group_name' => $_POST['group_name'],
                    'week_number' => (int)$_POST['week_number'],
                    'day_of_week' => (int)$_POST['day_of_week'],
                    'lesson_number' => (int)$_POST['lesson_number'],
                    'subject_name' => trim($_POST['subject_name']),
                    'room_number' => trim($_POST['room_number']),
                    'teacher_name' => trim($_POST['teacher_name']),
                    'start_time' => $_POST['start_time'],
                    'end_time' => $_POST['end_time']
                ];
                
                if ($scheduleManager->addLesson($data)) {
                    $success = 'Пара добавлена!';
                } else {
                    $error = 'Ошибка при добавлении пары';
                }
                break;
                
            case 'update_lesson':
                $id = (int)$_POST['lesson_id'];
                $data = [
                    'group_name' => $_POST['group_name'],
                    'week_number' => (int)$_POST['week_number'],
                    'day_of_week' => (int)$_POST['day_of_week'],
                    'lesson_number' => (int)$_POST['lesson_number'],
                    'subject_name' => trim($_POST['subject_name']),
                    'room_number' => trim($_POST['room_number']),
                    'teacher_name' => trim($_POST['teacher_name']),
                    'start_time' => $_POST['start_time'],
                    'end_time' => $_POST['end_time']
                ];
                
                if ($scheduleManager->updateLesson($id, $data)) {
                    $success = 'Пара обновлена!';
                } else {
                    $error = 'Ошибка при обновлении пары';
                }
                break;
                
            case 'delete_lesson':
                $id = (int)$_POST['lesson_id'];
                if ($scheduleManager->deleteLesson($id)) {
                    $success = 'Пара удалена!';
                } else {
                    $error = 'Ошибка при удалении пары';
                }
                break;
                
            case 'export_csv':
                $group = $_POST['group_name'];
                $week = (int)$_POST['week_number'];
                $csv = $scheduleManager->exportToCSV($group, $week);
                
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="schedule_' . $group . '_week' . $week . '.csv"');
                echo $csv;
                exit;
        }
    }
}

// Получение данных
$settings = $scheduleManager->getSettings();
$lessonTimes = $scheduleManager->getLessonTimes();
$dayNames = $scheduleManager->getDayNames();
$dayShortNames = $scheduleManager->getDayShortNames();

// Получение расписания для отображения
$selectedGroup = $_GET['group'] ?? 'Исип-05';
$selectedWeek = (int)($_GET['week'] ?? $settings['current_week']);
$selectedDay = (int)($_GET['day'] ?? $settings['current_day']);

$schedule = $scheduleManager->getSchedule($selectedGroup, $selectedWeek, $selectedDay);

$theme = $_COOKIE['theme'] ?? 'dark';
?>
<!DOCTYPE html>
<html lang="ru" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление расписанием - ImsitShop</title>
    <link rel="icon" href="assets/icons/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root {
            --primary-color: #3b82f6;
            --primary-hover: #2563eb;
            --secondary-color: #64748b;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #06b6d4;
            
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-blue: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            --gradient-card: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-success: linear-gradient(135deg, #10b981 0%, #059669 100%);
            --gradient-warning: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            --gradient-danger: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            --gradient-bg: linear-gradient(135deg, #0f172a 20%,rgb(96, 30, 167) 100%);
            
            --bg-color: #0f172a;
            --bg-secondary: #1e293b;
            --card-bg: #1e293b;
            --card-bg-hover: #334155;
            --text-color: #f1f5f9;
            --text-muted: #94a3b8;
            --text-light: #e2e8f0;
            --border-color: #334155;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            background: var(--gradient-bg);
            min-height: 100vh;
            position: relative;
        }

        html::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--gradient-bg);
            z-index: -2;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--gradient-bg);
            color: var(--text-color);
            line-height: 1.6;
            transition: all 0.3s ease;
            min-height: 100vh;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 80%, rgba(59, 130, 246, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(118, 75, 162, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(102, 126, 234, 0.05) 0%, transparent 50%);
            pointer-events: none;
            z-index: -1;
        }

        .header {
            background: none;
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow-lg);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.5rem;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            font-weight: 700;
        }

        .logo i {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.5rem;
            border-radius: 12px;
            backdrop-filter: blur(10px);
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .current-time {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-color);
            backdrop-filter: blur(10px);
        }

        .logout-btn {
            background: var(--danger-color);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .logout-btn:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        .main {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
            background: transparent;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 2rem;
            text-align: center;
            color: #ffffff;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .alert {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
        }

        .alert-success {
            background: var(--gradient-success);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .alert-error {
            background: var(--gradient-danger);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .card {
            background: rgba(30, 41, 59, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow-lg);
        }

        .card h2 {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-group label {
            font-weight: 600;
            color: var(--text-light);
        }

        .form-group input,
        .form-group select {
            padding: 0.75rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-color);
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .current-value {
            padding: 0.75rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-color);
            font-size: 1rem;
            font-weight: 600;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--gradient-blue);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-success {
            background: var(--gradient-success);
            color: white;
        }

        .btn-warning {
            background: var(--gradient-warning);
            color: white;
        }

        .btn-danger {
            background: var(--gradient-danger);
            color: white;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-color);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-group label {
            font-weight: 600;
            color: var(--text-light);
        }

        .filter-group select {
            padding: 0.5rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-color);
        }

        .schedule-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            overflow: hidden;
        }

        .schedule-table th,
        .schedule-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .schedule-table th {
            background: rgba(59, 130, 246, 0.2);
            font-weight: 600;
            color: #ffffff;
        }

        .schedule-table tr:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .lesson-number {
            font-weight: 700;
            color: var(--primary-color);
            font-size: 1.1rem;
        }

        .lesson-time {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-sm {
            padding: 0.5rem;
            font-size: 0.9rem;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(8px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: rgba(30, 41, 59, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            padding: 2rem;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            backdrop-filter: blur(10px);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #ffffff;
        }

        .close {
            background: none;
            border: none;
            font-size: 2rem;
            color: var(--text-muted);
            cursor: pointer;
            transition: color 0.3s;
        }

        .close:hover {
            color: var(--danger-color);
        }

        .scroll-top-btn {
            position: fixed;
            right: 20px;
            bottom: 24px;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            border: none;
            outline: none;
            cursor: pointer;
            background: var(--gradient-blue);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            opacity: 0;
            transform: translateY(10px) scale(.95);
            pointer-events: none;
            transition: all 0.3s ease;
            border: 2px solid rgba(255, 255, 255, 0.2);
        }

        .scroll-top-btn.visible {
            opacity: 1;
            transform: translateY(0) scale(1);
            pointer-events: auto;
        }

        .scroll-top-btn:hover { 
            transform: translateY(-4px) scale(1.05);
            box-shadow: var(--shadow-xl);
            background: linear-gradient(135deg, #2563eb, #1e40af);
        }

        .scroll-top-btn i { 
            font-size: 1.2rem;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .header-content {
                padding: 0 1rem;
            }
            
            .main {
                padding: 1rem;
            }
            
            .page-title {
                font-size: 2rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .filters {
                flex-direction: column;
                align-items: stretch;
            }
            
            .schedule-table {
                font-size: 0.9rem;
            }
            
            .schedule-table th,
            .schedule-table td {
                padding: 0.75rem 0.5rem;
            }
            
            .actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <a href="admin" class="logo">
                <i class="fas fa-calendar-alt"></i>
                Управление расписанием
            </a>
            <div class="header-right">
                <div class="current-time">
                    <i class="fas fa-clock"></i>
                    <span id="moscow-time"></span>
                </div>
                <a href="logout" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Выйти
                </a>
            </div>
        </div>
    </header>

    <main class="main">
        <h1 class="page-title">
            <i class="fas fa-calendar-alt"></i>
            Управление расписанием
        </h1>

        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Настройки времени -->
        <div class="card">
            <h2><i class="fas fa-cog"></i> Настройки времени</h2>
            <div class="form-grid">
                <div class="form-group">
                    <label>Текущая неделя:</label>
                    <div class="current-value"><?php echo $settings['current_week']; ?> неделя</div>
                </div>

                <div class="form-group">
                    <label>Текущий день:</label>
                    <div class="current-value"><?php echo $dayNames[$settings['current_day']]; ?></div>
                </div>

                <div class="form-group">
                    <label>Текущее время (МСК):</label>
                    <div class="current-value" id="current-time-display"><?php echo $settings['current_time']; ?></div>
                </div>

                <div class="form-group" style="align-self: end;">
                    <button type="button" onclick="updateTimeManually()" class="btn btn-primary">
                        <i class="fas fa-sync-alt"></i>
                        Обновить сейчас
                    </button>
                </div>
            </div>
            
            <div style="margin-top: 1rem; padding: 1rem; background: rgba(59, 130, 246, 0.1); border-radius: 8px; border: 1px solid rgba(59, 130, 246, 0.2);">
                <p style="margin: 0; color: var(--text-color); font-size: 0.9rem;">
                    <i class="fas fa-info-circle"></i>
                    <strong>Автоматическое время:</strong> Время обновляется автоматически каждую минуту по московскому времени. 
                    Неделя определяется по четности недели года (четные = 1 неделя, нечетные = 2 неделя).
                    <br><i class="fas fa-clock"></i> Последнее обновление: <span id="last-update"><?php echo date('H:i:s'); ?></span>
                </p>
            </div>
        </div>

        <!-- Фильтры -->
        <div class="card">
            <h2><i class="fas fa-filter"></i> Фильтры</h2>
            <div class="filters">
                <div class="filter-group">
                    <label>Группа:</label>
                    <select onchange="updateFilters()" id="groupFilter">
                        <option value="Исип-05" <?php echo $selectedGroup == 'Исип-05' ? 'selected' : ''; ?>>Исип-05</option>
                        <option value="Исип-06" <?php echo $selectedGroup == 'Исип-06' ? 'selected' : ''; ?>>Исип-06</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Неделя:</label>
                    <select onchange="updateFilters()" id="weekFilter">
                        <option value="1" <?php echo $selectedWeek == 1 ? 'selected' : ''; ?>>1 неделя</option>
                        <option value="2" <?php echo $selectedWeek == 2 ? 'selected' : ''; ?>>2 неделя</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>День:</label>
                    <select onchange="updateFilters()" id="dayFilter">
                        <?php foreach ($dayNames as $num => $name): ?>
                            <option value="<?php echo $num; ?>" <?php echo $selectedDay == $num ? 'selected' : ''; ?>>
                                <?php echo $name; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button onclick="openAddModal()" class="btn btn-success">
                    <i class="fas fa-plus"></i>
                    Добавить пару
                </button>

                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="export_csv">
                    <input type="hidden" name="group_name" value="<?php echo htmlspecialchars($selectedGroup); ?>">
                    <input type="hidden" name="week_number" value="<?php echo $selectedWeek; ?>">
                    <button type="submit" class="btn btn-secondary">
                        <i class="fas fa-download"></i>
                        Экспорт CSV
                    </button>
                </form>
            </div>
        </div>

        <!-- Расписание -->
        <div class="card">
            <h2>
                <i class="fas fa-calendar-week"></i>
                Расписание: <?php echo htmlspecialchars($selectedGroup); ?> - <?php echo $selectedWeek; ?> неделя - <?php echo $dayNames[$selectedDay]; ?>
            </h2>

            <?php if (empty($schedule)): ?>
                <p style="text-align: center; color: var(--text-muted); padding: 2rem;">
                    <i class="fas fa-calendar-times" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
                    На выбранный день расписания нет
                </p>
            <?php else: ?>
                <table class="schedule-table">
                    <thead>
                        <tr>
                            <th>Пара</th>
                            <th>Время</th>
                            <th>Предмет</th>
                            <th>Аудитория</th>
                            <th>Преподаватель</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schedule as $lesson): ?>
                            <tr>
                                <td>
                                    <div class="lesson-number"><?php echo $lesson['lesson_number']; ?></div>
                                </td>
                                <td>
                                    <div class="lesson-time">
                                        <?php echo substr($lesson['start_time'], 0, 5); ?> - <?php echo substr($lesson['end_time'], 0, 5); ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($lesson['subject_name']); ?></td>
                                <td><?php echo htmlspecialchars($lesson['room_number']); ?></td>
                                <td><?php echo htmlspecialchars($lesson['teacher_name']); ?></td>
                                <td>
                                    <div class="actions">
                                        <button onclick="openEditModal(<?php echo $lesson['id']; ?>)" class="btn btn-warning btn-sm">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="deleteLesson(<?php echo $lesson['id']; ?>)" class="btn btn-danger btn-sm">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>

    <!-- Модальное окно добавления/редактирования -->
    <div id="lessonModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Добавить пару</h3>
                <button class="close" onclick="closeModal()">&times;</button>
            </div>
            <form id="lessonForm" method="POST">
                <input type="hidden" name="action" id="formAction" value="add_lesson">
                <input type="hidden" name="lesson_id" id="lessonId">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="modal_group_name">Группа:</label>
                        <select name="group_name" id="modal_group_name" required>
                            <option value="Исип-05">Исип-05</option>
                            <option value="Исип-06">Исип-06</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="modal_week_number">Неделя:</label>
                        <select name="week_number" id="modal_week_number" required>
                            <option value="1">1 неделя</option>
                            <option value="2">2 неделя</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="modal_day_of_week">День недели:</label>
                        <select name="day_of_week" id="modal_day_of_week" required>
                            <?php foreach ($dayNames as $num => $name): ?>
                                <option value="<?php echo $num; ?>"><?php echo $name; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="modal_lesson_number">Номер пары:</label>
                        <select name="lesson_number" id="modal_lesson_number" required>
                            <?php for ($i = 1; $i <= 7; $i++): ?>
                                <option value="<?php echo $i; ?>"><?php echo $i; ?> пара</option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="modal_subject_name">Предмет:</label>
                        <input type="text" name="subject_name" id="modal_subject_name" required>
                    </div>

                    <div class="form-group">
                        <label for="modal_room_number">Аудитория:</label>
                        <input type="text" name="room_number" id="modal_room_number" required>
                    </div>

                    <div class="form-group">
                        <label for="modal_teacher_name">Преподаватель:</label>
                        <input type="text" name="teacher_name" id="modal_teacher_name" required>
                    </div>

                    <div class="form-group">
                        <label for="modal_start_time">Время начала:</label>
                        <input type="time" name="start_time" id="modal_start_time" required>
                    </div>

                    <div class="form-group">
                        <label for="modal_end_time">Время окончания:</label>
                        <input type="time" name="end_time" id="modal_end_time" required>
                    </div>
                </div>

                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                    <button type="button" onclick="closeModal()" class="btn btn-secondary">
                        Отмена
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Сохранить
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Кнопка "Вверх" -->
    <button id="scrollTopBtn" class="scroll-top-btn">
        <i class="fas fa-arrow-up"></i>
    </button>

    <script>
        // Обновление московского времени
        function updateMoscowTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('ru-RU', {
                timeZone: 'Europe/Moscow',
                hour12: false,
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            document.getElementById('moscow-time').textContent = timeString;
        }
        updateMoscowTime();
        setInterval(updateMoscowTime, 1000);

        // Автоматическое обновление времени каждую минуту
        setInterval(async function() {
            try {
                const response = await fetch('api/auto_update_schedule_time.php');
                const data = await response.json();
                
                if (data.success) {
                    // Обновляем отображение времени
                    const timeDisplay = document.getElementById('current-time-display');
                    if (timeDisplay) {
                        timeDisplay.textContent = data.settings.current_time;
                    }
                    
                    // Обновляем отображение дня недели
                    const dayDisplay = document.querySelector('.current-value');
                    if (dayDisplay) {
                        const dayNames = ['', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота', 'Воскресенье'];
                        dayDisplay.textContent = dayNames[data.settings.current_day];
                    }
                    
                    // Обновляем отображение недели
                    const weekDisplay = document.querySelector('.form-group:first-child .current-value');
                    if (weekDisplay) {
                        weekDisplay.textContent = data.settings.current_week + ' неделя';
                    }
                    
                    // Обновляем время последнего обновления
                    const lastUpdate = document.getElementById('last-update');
                    if (lastUpdate) {
                        const now = new Date();
                        lastUpdate.textContent = now.toLocaleTimeString('ru-RU', {
                            hour12: false,
                            hour: '2-digit',
                            minute: '2-digit',
                            second: '2-digit'
                        });
                    }
                }
            } catch (error) {
                console.error('Ошибка автоматического обновления времени:', error);
            }
        }, 60000); // Каждую минуту

        // Ручное обновление времени
        async function updateTimeManually() {
            try {
                const response = await fetch('api/auto_update_schedule_time.php');
                const data = await response.json();
                
                if (data.success) {
                    location.reload(); // Перезагружаем страницу для отображения новых данных
                } else {
                    alert('Ошибка обновления времени: ' + data.error);
                }
            } catch (error) {
                console.error('Ошибка обновления времени:', error);
                alert('Ошибка обновления времени');
            }
        }

        // Обновление фильтров
        function updateFilters() {
            const group = document.getElementById('groupFilter').value;
            const week = document.getElementById('weekFilter').value;
            const day = document.getElementById('dayFilter').value;
            
            window.location.href = `schedule_management?group=${group}&week=${week}&day=${day}`;
        }

        // Модальные окна
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Добавить пару';
            document.getElementById('formAction').value = 'add_lesson';
            document.getElementById('lessonId').value = '';
            document.getElementById('lessonForm').reset();
            document.getElementById('lessonModal').style.display = 'flex';
        }

        function openEditModal(lessonId) {
            // Показываем модалку с индикатором загрузки
            document.getElementById('modalTitle').textContent = 'Загрузка...';
            document.getElementById('lessonModal').style.display = 'flex';
            
            // AJAX запрос для получения данных пары
            fetch('api/get_lesson.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'lesson_id=' + encodeURIComponent(lessonId)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const lesson = data.lesson;
                    
                    // Заполняем форму данными пары
                    document.getElementById('modal_group_name').value = lesson.group_name;
                    document.getElementById('modal_week_number').value = lesson.week_number;
                    document.getElementById('modal_day_of_week').value = lesson.day_of_week;
                    document.getElementById('modal_lesson_number').value = lesson.lesson_number;
                    document.getElementById('modal_subject_name').value = lesson.subject_name;
                    document.getElementById('modal_room_number').value = lesson.room_number;
                    document.getElementById('modal_teacher_name').value = lesson.teacher_name;
                    document.getElementById('modal_start_time').value = lesson.start_time;
                    document.getElementById('modal_end_time').value = lesson.end_time;
                    
                    // Устанавливаем заголовок и действие формы
                    document.getElementById('modalTitle').textContent = 'Редактировать пару';
                    document.getElementById('formAction').value = 'update_lesson';
                    document.getElementById('lessonId').value = lessonId;
                } else {
                    alert('Ошибка при загрузке данных пары: ' + (data.message || 'Неизвестная ошибка'));
                    document.getElementById('lessonModal').style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Ошибка:', error);
                alert('Ошибка при загрузке данных пары');
                document.getElementById('lessonModal').style.display = 'none';
            });
        }

        function closeModal() {
            document.getElementById('lessonModal').style.display = 'none';
        }

        // Удаление пары
        function deleteLesson(lessonId) {
            if (confirm('Вы уверены, что хотите удалить эту пару?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_lesson">
                    <input type="hidden" name="lesson_id" value="${lessonId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Кнопка "Вверх"
        (function(){
            const btn = document.getElementById('scrollTopBtn');
            function onScroll(){
                if (window.scrollY > 300) {
                    btn.classList.add('visible');
                } else {
                    btn.classList.remove('visible');
                }
            }
            window.addEventListener('scroll', onScroll, { passive: true });
            btn.addEventListener('click', function(){
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
            onScroll();
        })();

        // Закрытие модального окна по клику вне его
        document.getElementById('lessonModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Закрытие по Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>
