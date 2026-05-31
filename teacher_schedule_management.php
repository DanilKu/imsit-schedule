<?php
session_start();
require_once 'config/auth.php';
require_once 'config/database.php';

// Проверка авторизации
if (!isAuthenticated() || !isAdmin()) {
    header('Location: access_denied.php');
    exit();
}

$currentUser = getCurrentUser();

// Обработка AJAX запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'add_lesson':
                $teacherId = (int)($_POST['teacher_id'] ?? 0);
                $dayOfWeek = (int)($_POST['day_of_week'] ?? 0);
                $lessonNumber = (int)($_POST['lesson_number'] ?? 0);
                $subjectName = trim($_POST['subject_name'] ?? '');
                $roomNumber = trim($_POST['room_number'] ?? '');
                $groupName = trim($_POST['group_name'] ?? '');
                $weeks = $_POST['weeks'] ?? []; // массив [1, 2] или [1] или [2]
                
                if ($teacherId <= 0 || $dayOfWeek < 1 || $dayOfWeek > 6 || $lessonNumber < 1 || $lessonNumber > 7) {
                    throw new Exception('Неверные параметры');
                }
                
                if (empty($subjectName) || empty($roomNumber)) {
                    throw new Exception('Название предмета и аудитория обязательны');
                }
                
                // Проверяем существование преподавателя
                $stmt = $pdo->prepare("SELECT id FROM teachers WHERE id = ? AND is_active = 1");
                $stmt->execute([$teacherId]);
                $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$teacher) {
                    throw new Exception('Преподаватель не найден');
                }
                
                // Время пар
                $lessonTimes = [
                    1 => ['08:00:00', '08:30:00'],
                    2 => ['09:40:00', '11:10:00'],
                    3 => ['11:30:00', '13:00:00'],
                    4 => ['13:10:00', '14:40:00'],
                    5 => ['14:50:00', '16:20:00'],
                    6 => ['16:30:00', '18:00:00'],
                    7 => ['18:10:00', '19:40:00']
                ];
                
                $startTime = $lessonTimes[$lessonNumber][0];
                $endTime = $lessonTimes[$lessonNumber][1];
                
                $pdo->beginTransaction();
                
                foreach ($weeks as $week) {
                    $week = (int)$week;
                    if ($week < 1 || $week > 2) continue;
                    
                    // Проверяем, не существует ли уже такая пара
                    $stmt = $pdo->prepare("
                        SELECT id FROM teacher_schedule 
                        WHERE teacher_id = ? AND week_number = ? AND day_of_week = ? AND lesson_number = ?
                    ");
                    $stmt->execute([$teacherId, $week, $dayOfWeek, $lessonNumber]);
                    
                    if ($stmt->fetch()) {
                        // Обновляем существующую пару
                        $stmt = $pdo->prepare("
                            UPDATE teacher_schedule 
                            SET subject_name = ?, room_number = ?, group_name = ?, start_time = ?, end_time = ?
                            WHERE teacher_id = ? AND week_number = ? AND day_of_week = ? AND lesson_number = ?
                        ");
                        $stmt->execute([$subjectName, $roomNumber, $groupName, $startTime, $endTime, $teacherId, $week, $dayOfWeek, $lessonNumber]);
                    } else {
                        // Добавляем новую пару
                        $stmt = $pdo->prepare("
                            INSERT INTO teacher_schedule (teacher_id, week_number, day_of_week, lesson_number, subject_name, room_number, group_name, start_time, end_time) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$teacherId, $week, $dayOfWeek, $lessonNumber, $subjectName, $roomNumber, $groupName, $startTime, $endTime]);
                    }
                }
                
                $pdo->commit();
                
                echo json_encode(['success' => true, 'message' => 'Пары добавлены успешно']);
                break;
                
            case 'delete_lesson':
                $lessonId = (int)($_POST['lesson_id'] ?? 0);
                
                if ($lessonId <= 0) {
                    throw new Exception('Неверный ID пары');
                }
                
                $stmt = $pdo->prepare("DELETE FROM teacher_schedule WHERE id = ?");
                $stmt->execute([$lessonId]);
                
                echo json_encode(['success' => true, 'message' => 'Пара удалена']);
                break;
                
            case 'get_teacher_schedule':
                $teacherId = (int)($_POST['teacher_id'] ?? 0);
                $week = (int)($_POST['week'] ?? 1);
                
                if ($teacherId <= 0) {
                    throw new Exception('Неверный ID преподавателя');
                }
                
                // Проверяем существование преподавателя
                $stmt = $pdo->prepare("SELECT id FROM teachers WHERE id = ? AND is_active = 1");
                $stmt->execute([$teacherId]);
                $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$teacher) {
                    throw new Exception('Преподаватель не найден');
                }
                
                // Получаем расписание преподавателя
                $stmt = $pdo->prepare("
                    SELECT ts.*, t.full_name as teacher_name
                    FROM teacher_schedule ts
                    JOIN teachers t ON ts.teacher_id = t.id
                    WHERE ts.teacher_id = ? AND ts.week_number = ?
                    ORDER BY ts.day_of_week, ts.lesson_number
                ");
                $stmt->execute([$teacherId, $week]);
                $schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'schedule' => $schedule]);
                break;
                
            default:
                throw new Exception('Неизвестное действие');
        }
        
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

// Получение списка преподавателей
try {
    $stmt = $pdo->query("SELECT * FROM teachers WHERE is_active = 1 ORDER BY full_name");
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $teachers = [];
    $error = $e->getMessage();
}

$dayNames = [
    1 => 'Понедельник',
    2 => 'Вторник', 
    3 => 'Среда',
    4 => 'Четверг',
    5 => 'Пятница',
    6 => 'Суббота'
];

$lessonTimes = [
    1 => '08:00-08:30',
    2 => '09:40-11:10',
    3 => '11:30-13:00',
    4 => '13:10-14:40',
    5 => '14:50-16:20',
    6 => '16:30-18:00',
    7 => '18:10-19:40'
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление расписанием преподавателей - Админ панель</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0f172a; color: #e2e8f0; }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; cursor: pointer; font-weight: 500; transition: all 0.2s; }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-primary:hover { background: #2563eb; }
        .btn-success { background: #10b981; color: white; }
        .btn-success:hover { background: #059669; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; }
        .btn-secondary { background: #6b7280; color: white; }
        .btn-secondary:hover { background: #4b5563; }
        
        .grid { display: grid; gap: 2rem; }
        .grid-2 { grid-template-columns: 1fr 1fr; }
        .grid-3 { grid-template-columns: repeat(3, 1fr); }
        
        .card { background: #1e293b; border-radius: 0.5rem; padding: 1.5rem; border: 1px solid #334155; }
        .card h3 { margin-bottom: 1rem; color: #f1f5f9; }
        
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: #cbd5e1; }
        .form-group input, .form-group select { width: 100%; padding: 0.75rem; border: 1px solid #475569; border-radius: 0.25rem; background: #334155; color: #e2e8f0; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #3b82f6; }
        
        .checkbox-group { display: flex; gap: 1rem; margin-top: 0.5rem; }
        .checkbox-item { display: flex; align-items: center; gap: 0.5rem; }
        .checkbox-item input[type="checkbox"] { width: auto; }
        
        .schedule-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 0.5rem; margin-top: 1rem; }
        .schedule-day { background: #334155; border-radius: 0.25rem; padding: 0.5rem; min-height: 200px; }
        .schedule-day h4 { text-align: center; margin-bottom: 0.5rem; font-size: 0.875rem; color: #94a3b8; }
        .lesson-item { background: #475569; border-radius: 0.25rem; padding: 0.5rem; margin-bottom: 0.25rem; font-size: 0.75rem; }
        .lesson-item .lesson-time { color: #94a3b8; font-weight: 500; }
        .lesson-item .lesson-subject { color: #e2e8f0; margin: 0.25rem 0; }
        .lesson-item .lesson-room { color: #94a3b8; }
        .lesson-item .lesson-actions { margin-top: 0.25rem; }
        .lesson-item .btn { padding: 0.25rem 0.5rem; font-size: 0.75rem; }
        
        .week-selector { display: flex; gap: 0.5rem; margin-bottom: 1rem; }
        .week-btn { padding: 0.5rem 1rem; border: 1px solid #475569; background: #334155; color: #e2e8f0; border-radius: 0.25rem; cursor: pointer; }
        .week-btn.active { background: #3b82f6; border-color: #3b82f6; }
        
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .alert-success { background: #065f46; color: #d1fae5; }
        .alert-error { background: #7f1d1d; color: #fecaca; }
        
        .teacher-selector { margin-bottom: 1rem; }
        .teacher-selector select { font-size: 1.125rem; padding: 1rem; }
        
        @media (max-width: 768px) {
            .grid-2, .grid-3 { grid-template-columns: 1fr; }
            .schedule-grid { grid-template-columns: 1fr; }
            .container { padding: 1rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-chalkboard-teacher"></i> Управление расписанием преподавателей</h1>
            <div>
                <a href="admin" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Назад в админку</a>
            </div>
        </div>

        <div id="alertContainer"></div>

        <div class="grid grid-2">
            <!-- Добавление пары -->
            <div class="card">
                <h3><i class="fas fa-plus"></i> Добавить пару</h3>
                <form id="addLessonForm">
                    <div class="form-group">
                        <label for="teacher_id">Преподаватель *</label>
                        <select id="teacher_id" name="teacher_id" required>
                            <option value="">Выберите преподавателя</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="day_of_week">День недели *</label>
                        <select id="day_of_week" name="day_of_week" required>
                            <option value="">Выберите день</option>
                            <?php foreach ($dayNames as $dayNum => $dayName): ?>
                                <option value="<?php echo $dayNum; ?>"><?php echo $dayName; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="lesson_number">Номер пары *</label>
                        <select id="lesson_number" name="lesson_number" required>
                            <option value="">Выберите пару</option>
                            <?php foreach ($lessonTimes as $lessonNum => $time): ?>
                                <option value="<?php echo $lessonNum; ?>"><?php echo $lessonNum; ?> пара (<?php echo $time; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="subject_name">Название предмета *</label>
                        <input type="text" id="subject_name" name="subject_name" required placeholder="Например: Математика">
                    </div>
                    
                    <div class="form-group">
                        <label for="room_number">Аудитория *</label>
                        <input type="text" id="room_number" name="room_number" required placeholder="Например: 1-122">
                    </div>
                    
                    <div class="form-group">
                        <label for="group_name">Группа</label>
                        <input type="text" id="group_name" name="group_name" placeholder="Например: 22-СПО-ИСИП-06, 23-СПО-ИСИП-01 (не обязательно)">
                    </div>
                    
                    <div class="form-group">
                        <label>Недели *</label>
                        <div class="checkbox-group">
                            <div class="checkbox-item">
                                <input type="checkbox" id="week_1" name="weeks[]" value="1" checked>
                                <label for="week_1">1 неделя</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" id="week_2" name="weeks[]" value="2" checked>
                                <label for="week_2">2 неделя</label>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-plus"></i> Добавить пару
                    </button>
                </form>
            </div>

            <!-- Просмотр расписания -->
            <div class="card">
                <h3><i class="fas fa-calendar-alt"></i> Расписание преподавателя</h3>
                
                <div class="teacher-selector">
                    <select id="viewTeacher" onchange="loadTeacherSchedule()">
                        <option value="">Выберите преподавателя для просмотра</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="week-selector">
                    <button class="week-btn active" data-week="1" onclick="switchWeek(1)">1 неделя</button>
                    <button class="week-btn" data-week="2" onclick="switchWeek(2)">2 неделя</button>
                </div>
                
                <div id="scheduleContainer">
                    <div style="text-align: center; color: #94a3b8; padding: 2rem;">
                        Выберите преподавателя для просмотра расписания
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentWeek = 1;
        let currentTeacherId = null;

        function showAlert(message, type) {
            const container = document.getElementById('alertContainer');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.textContent = message;
            container.appendChild(alert);
            setTimeout(() => alert.remove(), 5000);
        }

        function switchWeek(week) {
            currentWeek = week;
            document.querySelectorAll('.week-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.week == week);
            });
            if (currentTeacherId) {
                loadTeacherSchedule();
            }
        }

        function loadTeacherSchedule() {
            const teacherId = document.getElementById('viewTeacher').value;
            if (!teacherId) {
                document.getElementById('scheduleContainer').innerHTML = 
                    '<div style="text-align: center; color: #94a3b8; padding: 2rem;">Выберите преподавателя для просмотра расписания</div>';
                return;
            }

            currentTeacherId = teacherId;

            const formData = new FormData();
            formData.append('action', 'get_teacher_schedule');
            formData.append('teacher_id', teacherId);
            formData.append('week', currentWeek);

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderSchedule(data.schedule);
                } else {
                    showAlert(data.error, 'error');
                }
            })
            .catch(error => {
                showAlert('Ошибка загрузки расписания', 'error');
            });
        }

        function renderSchedule(schedule) {
            const container = document.getElementById('scheduleContainer');
            const dayNames = ['', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];
            
            let html = '<div class="schedule-grid">';
            
            for (let day = 1; day <= 6; day++) {
                const dayLessons = schedule.filter(lesson => lesson.day_of_week == day);
                
                html += `
                    <div class="schedule-day">
                        <h4>${dayNames[day]}</h4>
                        ${dayLessons.map(lesson => `
                            <div class="lesson-item">
                                <div class="lesson-time">${lesson.lesson_number} пара</div>
                                <div class="lesson-subject">${lesson.subject_name}</div>
                                <div class="lesson-room">${lesson.room_number}</div>
                                ${lesson.group_name ? `<div class="lesson-group" style="color: #60a5fa; font-size: 0.7rem; margin-top: 0.25rem;">${lesson.group_name}</div>` : ''}
                                <div class="lesson-actions">
                                    <button class="btn btn-danger" onclick="deleteLesson(${lesson.id})">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                `;
            }
            
            html += '</div>';
            container.innerHTML = html;
        }

        function deleteLesson(lessonId) {
            if (confirm('Вы уверены, что хотите удалить эту пару?')) {
                const formData = new FormData();
                formData.append('action', 'delete_lesson');
                formData.append('lesson_id', lessonId);

                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        loadTeacherSchedule(); // Перезагружаем расписание
                    } else {
                        showAlert(data.error, 'error');
                    }
                })
                .catch(error => {
                    showAlert('Ошибка удаления пары', 'error');
                });
            }
        }

        // Обработка формы добавления пары
        document.getElementById('addLessonForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'add_lesson');
            
            // Проверяем, что выбрана хотя бы одна неделя
            const weeks = Array.from(document.querySelectorAll('input[name="weeks[]"]:checked')).map(cb => cb.value);
            if (weeks.length === 0) {
                showAlert('Выберите хотя бы одну неделю', 'error');
                return;
            }
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    this.reset();
                    // Сбрасываем чекбоксы недель
                    document.getElementById('week_1').checked = true;
                    document.getElementById('week_2').checked = true;
                    
                    // Если просматривается расписание этого преподавателя, обновляем его
                    if (currentTeacherId && document.getElementById('teacher_id').value == currentTeacherId) {
                        loadTeacherSchedule();
                    }
                } else {
                    showAlert(data.error, 'error');
                }
            })
            .catch(error => {
                showAlert('Ошибка добавления пары', 'error');
            });
        });
    </script>
</body>
</html>
