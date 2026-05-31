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
            case 'add':
                $full_name = trim($_POST['full_name'] ?? '');
                $short_name = trim($_POST['short_name'] ?? '');
                $department = trim($_POST['department'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                
                if (empty($full_name)) {
                    throw new Exception('ФИО обязательно для заполнения');
                }
                
                $stmt = $pdo->prepare("INSERT INTO teachers (full_name, short_name, department, email, phone) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$full_name, $short_name, $department, $email, $phone]);
                
                echo json_encode(['success' => true, 'message' => 'Преподаватель добавлен']);
                break;
                
            case 'edit':
                $id = (int)($_POST['id'] ?? 0);
                $full_name = trim($_POST['full_name'] ?? '');
                $short_name = trim($_POST['short_name'] ?? '');
                $department = trim($_POST['department'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $is_active = (int)($_POST['is_active'] ?? 1);
                
                if (empty($full_name)) {
                    throw new Exception('ФИО обязательно для заполнения');
                }
                
                $stmt = $pdo->prepare("UPDATE teachers SET full_name = ?, short_name = ?, department = ?, email = ?, phone = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$full_name, $short_name, $department, $email, $phone, $is_active, $id]);
                
                echo json_encode(['success' => true, 'message' => 'Преподаватель обновлен']);
                break;
                
            case 'delete':
                $id = (int)($_POST['id'] ?? 0);
                
                $stmt = $pdo->prepare("UPDATE teachers SET is_active = 0 WHERE id = ?");
                $stmt->execute([$id]);
                
                echo json_encode(['success' => true, 'message' => 'Преподаватель деактивирован']);
                break;
                
            default:
                throw new Exception('Неизвестное действие');
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

// Получение списка преподавателей
try {
    $stmt = $pdo->query("SELECT * FROM teachers ORDER BY full_name");
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $teachers = [];
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление преподавателями - Админ панель</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0f172a; color: #e2e8f0; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; cursor: pointer; font-weight: 500; transition: all 0.2s; }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-primary:hover { background: #2563eb; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; }
        .btn-success { background: #10b981; color: white; }
        .btn-success:hover { background: #059669; }
        .table { width: 100%; border-collapse: collapse; background: #1e293b; border-radius: 0.5rem; overflow: hidden; }
        .table th, .table td { padding: 1rem; text-align: left; border-bottom: 1px solid #334155; }
        .table th { background: #334155; font-weight: 600; }
        .table tr:hover { background: #334155; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; }
        .modal-content { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #1e293b; padding: 2rem; border-radius: 0.5rem; width: 90%; max-width: 500px; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
        .form-group input, .form-group select { width: 100%; padding: 0.75rem; border: 1px solid #475569; border-radius: 0.25rem; background: #334155; color: #e2e8f0; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #3b82f6; }
        .status-active { color: #10b981; }
        .status-inactive { color: #ef4444; }
        .actions { display: flex; gap: 0.5rem; }
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .alert-success { background: #065f46; color: #d1fae5; }
        .alert-error { background: #7f1d1d; color: #fecaca; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-chalkboard-teacher"></i> Управление преподавателями</h1>
            <div>
                <a href="admin" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Назад в админку</a>
                <button onclick="showAddModal()" class="btn btn-success"><i class="fas fa-plus"></i> Добавить преподавателя</button>
            </div>
        </div>

        <div id="alertContainer"></div>

        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>ФИО</th>
                    <th>Краткое имя</th>
                    <th>Кафедра</th>
                    <th>Email</th>
                    <th>Телефон</th>
                    <th>Статус</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($teachers as $teacher): ?>
                <tr>
                    <td><?php echo $teacher['id']; ?></td>
                    <td><?php echo htmlspecialchars($teacher['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($teacher['short_name'] ?: '-'); ?></td>
                    <td><?php echo htmlspecialchars($teacher['department'] ?: '-'); ?></td>
                    <td><?php echo htmlspecialchars($teacher['email'] ?: '-'); ?></td>
                    <td><?php echo htmlspecialchars($teacher['phone'] ?: '-'); ?></td>
                    <td>
                        <span class="<?php echo $teacher['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo $teacher['is_active'] ? 'Активен' : 'Неактивен'; ?>
                        </span>
                    </td>
                    <td class="actions">
                        <button onclick="editTeacher(<?php echo htmlspecialchars(json_encode($teacher)); ?>)" class="btn btn-primary">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="deleteTeacher(<?php echo $teacher['id']; ?>)" class="btn btn-danger">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Модальное окно добавления/редактирования -->
    <div id="teacherModal" class="modal">
        <div class="modal-content">
            <h2 id="modalTitle">Добавить преподавателя</h2>
            <form id="teacherForm">
                <input type="hidden" id="teacherId" name="id">
                <input type="hidden" id="action" name="action" value="add">
                
                <div class="form-group">
                    <label for="full_name">ФИО *</label>
                    <input type="text" id="full_name" name="full_name" required>
                </div>
                
                <div class="form-group">
                    <label for="short_name">Краткое имя</label>
                    <input type="text" id="short_name" name="short_name">
                </div>
                
                <div class="form-group">
                    <label for="department">Кафедра</label>
                    <input type="text" id="department" name="department">
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email">
                </div>
                
                <div class="form-group">
                    <label for="phone">Телефон</label>
                    <input type="tel" id="phone" name="phone">
                </div>
                
                <div class="form-group" id="statusGroup" style="display: none;">
                    <label for="is_active">Статус</label>
                    <select id="is_active" name="is_active">
                        <option value="1">Активен</option>
                        <option value="0">Неактивен</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" onclick="closeModal()" class="btn">Отмена</button>
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showAddModal() {
            document.getElementById('modalTitle').textContent = 'Добавить преподавателя';
            document.getElementById('action').value = 'add';
            document.getElementById('teacherForm').reset();
            document.getElementById('teacherId').value = '';
            document.getElementById('statusGroup').style.display = 'none';
            document.getElementById('teacherModal').style.display = 'block';
        }

        function editTeacher(teacher) {
            document.getElementById('modalTitle').textContent = 'Редактировать преподавателя';
            document.getElementById('action').value = 'edit';
            document.getElementById('teacherId').value = teacher.id;
            document.getElementById('full_name').value = teacher.full_name;
            document.getElementById('short_name').value = teacher.short_name || '';
            document.getElementById('department').value = teacher.department || '';
            document.getElementById('email').value = teacher.email || '';
            document.getElementById('phone').value = teacher.phone || '';
            document.getElementById('is_active').value = teacher.is_active;
            document.getElementById('statusGroup').style.display = 'block';
            document.getElementById('teacherModal').style.display = 'block';
        }

        function deleteTeacher(id) {
            if (confirm('Вы уверены, что хотите деактивировать этого преподавателя?')) {
                fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=delete&id=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showAlert(data.error, 'error');
                    }
                });
            }
        }

        function closeModal() {
            document.getElementById('teacherModal').style.display = 'none';
        }

        function showAlert(message, type) {
            const container = document.getElementById('alertContainer');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.textContent = message;
            container.appendChild(alert);
            setTimeout(() => alert.remove(), 5000);
        }

        document.getElementById('teacherForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    closeModal();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert(data.error, 'error');
                }
            });
        });

        // Закрытие модального окна по клику вне его
        document.getElementById('teacherModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>
