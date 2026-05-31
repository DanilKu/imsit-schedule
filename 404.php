<?php
// Получение темы из cookie
$theme = $_COOKIE['theme'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="ru" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Страница не найдена</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .error-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-primary) 100%);
            padding: 20px;
        }
        
        .error-content {
            text-align: center;
            max-width: 600px;
            background: var(--bg-primary);
            border-radius: 20px;
            padding: 60px 40px;
            box-shadow: var(--shadow-hover);
            animation: slideInUp 0.8s ease;
        }
        
        .error-number {
            font-size: 8rem;
            font-weight: 900;
            color: var(--accent-color);
            margin-bottom: 20px;
            text-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
            animation: bounce 2s infinite;
        }
        
        .error-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 20px;
        }
        
        .error-description {
            font-size: 1.1rem;
            color: var(--text-secondary);
            margin-bottom: 40px;
            line-height: 1.6;
        }
        
        .error-actions {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn-home {
            background: linear-gradient(135deg, var(--accent-color), var(--accent-hover));
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .btn-home:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(102, 126, 234, 0.4);
        }
        
        .btn-back {
            background: var(--bg-secondary);
            color: var(--text-primary);
            padding: 15px 30px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
        }
        
        .btn-back:hover {
            background: var(--bg-tertiary);
            border-color: var(--accent-color);
            transform: translateY(-2px);
        }
        
        .error-illustration {
            margin-bottom: 30px;
            font-size: 4rem;
            color: var(--accent-color);
            opacity: 0.8;
            animation: float 3s ease-in-out infinite;
        }
        
        .error-details {
            background: var(--bg-secondary);
            border-radius: 12px;
            padding: 20px;
            margin-top: 30px;
            border-left: 4px solid var(--accent-color);
        }
        
        .error-details h3 {
            color: var(--text-primary);
            margin-bottom: 10px;
            font-size: 1.2rem;
        }
        
        .error-details p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin: 5px 0;
        }
        
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-10px);
            }
            60% {
                transform: translateY(-5px);
            }
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-10px);
            }
        }
        
        @media (max-width: 768px) {
            .error-content {
                padding: 40px 20px;
            }
            
            .error-number {
                font-size: 6rem;
            }
            
            .error-title {
                font-size: 2rem;
            }
            
            .error-actions {
                flex-direction: column;
                align-items: center;
            }
            
            .btn-home, .btn-back {
                width: 100%;
                max-width: 300px;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-content">
            <div class="error-illustration">
                <i class="fas fa-search"></i>
            </div>
            
            <div class="error-number">404</div>
            
            <h1 class="error-title">Страница не найдена</h1>
            
            <p class="error-description">
                К сожалению, запрашиваемая страница не существует или была перемещена. 
                Возможно, вы перешли по устаревшей ссылке или допустили ошибку в адресе.
            </p>
            
            <div class="error-actions">
                <a href="id" class="btn-home">
                    <i class="fas fa-home"></i>
                    На главную
                </a>
                
                <a href="id" class="btn-back">
                    <i class="fas fa-arrow-left"></i>
                    Назад
                </a>
            </div>
            
            <div class="error-details">
                <h3>Что можно сделать:</h3>
                <p><i class="fas fa-check"></i> Проверьте правильность URL адреса</p>
                <p><i class="fas fa-check"></i> Вернитесь на главную страницу</p>
                <p><i class="fas fa-check"></i> Используйте навигационное меню</p>
                <p><i class="fas fa-check"></i> Обратитесь к администратору, если проблема повторяется</p>
            </div>
        </div>
    </div>

    <script>
        // Функция переключения темы (если нужно)
        function toggleTheme() {
            const html = document.documentElement;
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            html.setAttribute('data-theme', newTheme);
            document.cookie = `theme=${newTheme}; path=/; max-age=31536000`;
        }
        
        // Добавляем обработчик клавиш
        document.addEventListener('keydown', function(e) {
            // Enter для перехода на главную
            if (e.key === 'Enter') {
                window.location.href = 'index.php';
            }
            
            // Escape для возврата назад
            if (e.key === 'Escape') {
                history.back();
            }
        });
        
        // Анимация появления элементов
        document.addEventListener('DOMContentLoaded', function() {
            const elements = document.querySelectorAll('.error-number, .error-title, .error-description, .error-actions, .error-details');
            
            elements.forEach((element, index) => {
                element.style.opacity = '0';
                element.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    element.style.transition = 'all 0.6s ease';
                    element.style.opacity = '1';
                    element.style.transform = 'translateY(0)';
                }, index * 200);
            });
        });
    </script>
</body>
</html> 