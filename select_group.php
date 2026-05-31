<?php
require_once 'config/auth.php';
require_once 'config/database.php';

// Проверка авторизации
if (!isAuthenticated()) {
    header('Location: login');
    exit;
}

$currentUser = getCurrentUser();

// Если пользователь уже выбрал группу, перенаправляем на расписание
if ($currentUser['group']) {
    header('Location: schedule-new.php');
    exit;
}

$error = '';
$success = '';

// Обработка выбора группы
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['group'])) {
    $selectedGroup = $_POST['group'];
    
    if (in_array($selectedGroup, ['Исип-05', 'Исип-06'])) {
        try {
            // Обновляем группу пользователя в базе данных
            $stmt = $pdo->prepare("UPDATE users SET `group` = ? WHERE id = ?");
            $success = $stmt->execute([$selectedGroup, $currentUser['id']]);
            
            if ($success) {
                // Обновляем сессию
                $_SESSION['group'] = $selectedGroup;
                
                // Перенаправляем на расписание
                header('Location: schedule-new.php');
                exit;
            } else {
                $error = 'Ошибка сохранения группы';
            }
        } catch (Exception $e) {
            $error = 'Ошибка: ' . $e->getMessage();
        }
    } else {
        $error = 'Неверная группа';
    }
}
?>
<!DOCTYPE html>
<html lang="ru" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Выбор группы - ImsitShop</title>
    <link rel="icon" href="assets/icons/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3b82f6;
            --primary-hover: #2563eb;
            --gradient-blue: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            --gradient-bg: linear-gradient(135deg, #0f172a 20%,rgb(96, 30, 167) 100%);
            --text-color: #f1f5f9;
            --text-muted: #94a3b8;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            background: var(--gradient-bg);
            min-height: 100vh;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--gradient-bg);
            color: var(--text-color);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .container {
            max-width: 500px;
            width: 90%;
            padding: 2rem;
        }

        .card {
            background: rgba(30, 41, 59, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 2rem;
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow-lg);
            text-align: center;
        }

        .logo {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }

        .title {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: var(--text-color);
        }

        .subtitle {
            color: var(--text-muted);
            margin-bottom: 2rem;
        }

        .group-options {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .group-option {
            padding: 1rem;
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.05);
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .group-option:hover {
            border-color: var(--primary-color);
            background: rgba(59, 130, 246, 0.1);
            transform: translateY(-2px);
        }

        .group-option.selected {
            border-color: var(--primary-color);
            background: var(--gradient-blue);
        }

        .group-icon {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .group-name {
            font-size: 1.1rem;
            font-weight: 600;
        }

        .group-description {
            font-size: 0.9rem;
            color: var(--text-muted);
        }

        .btn {
            width: 100%;
            padding: 1rem;
            background: var(--gradient-blue);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn:hover {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            transform: translateY(-2px);
        }

        .btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #ef4444;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .card {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="logo">
                <i class="fas fa-users"></i>
            </div>
            
            <h1 class="title">Выберите вашу группу</h1>
            <p class="subtitle">Это поможет показывать вам актуальное расписание</p>

            <?php if ($error): ?>
                <div class="error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="groupForm">
                <div class="group-options">
                    <div class="group-option" onclick="selectGroup('Исип-05')">
                        <div class="group-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <div>
                            <div class="group-name">Исип-05</div>
                            <div class="group-description">22-СПО-ИСИП-05</div>
                        </div>
                    </div>
                    
                    <div class="group-option" onclick="selectGroup('Исип-06')">
                        <div class="group-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <div>
                            <div class="group-name">Исип-06</div>
                            <div class="group-description">22-СПО-ИСИП-06</div>
                        </div>
                    </div>
                </div>

                <input type="hidden" name="group" id="selectedGroup" required>
                <button type="submit" class="btn" id="submitBtn" disabled>
                    <i class="fas fa-check"></i>
                    Продолжить
                </button>
            </form>
        </div>
    </div>

    <script>
        function selectGroup(group) {
            // Убираем выделение со всех опций
            document.querySelectorAll('.group-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Выделяем выбранную опцию
            event.currentTarget.classList.add('selected');
            
            // Устанавливаем значение в скрытое поле
            document.getElementById('selectedGroup').value = group;
            
            // Активируем кнопку
            document.getElementById('submitBtn').disabled = false;
        }
    </script>
</body>
</html>
