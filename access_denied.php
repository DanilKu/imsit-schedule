<?php
require_once 'config/auth.php';

// Проверка авторизации
requireAuth();

// Получение информации о текущем пользователе
$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ImsitShop - Загрузка сайта</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0a0a0a;
            --bg-secondary: #1a1a1a;
            --text-primary: #ffffff;
            --text-secondary: #b0b0b0;
            --accent-blue: #3b82f6;
            --accent-purple: #8b5cf6;
            --accent-fuchsia: #d946ef;
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
            --backdrop-blur: blur(20px);
            --glass-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            overflow: hidden;
        }

        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 25%, #334155 50%, #475569 75%, #64748b 100%);
            background-attachment: fixed;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text-primary);
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 20% 80%, rgba(59, 130, 246, 0.3) 0%, transparent 50%),
                        radial-gradient(circle at 80% 20%, rgba(139, 92, 246, 0.3) 0%, transparent 50%),
                        radial-gradient(circle at 40% 40%, rgba(217, 70, 239, 0.2) 0%, transparent 50%);
            pointer-events: none;
            z-index: -1;
        }

        /* Loading Animation */
        .loading-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--bg-primary);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            transition: opacity 0.5s ease, visibility 0.5s ease;
        }

        .loading-container.hidden {
            opacity: 0;
            visibility: hidden;
        }

        .loading-logo {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-purple), var(--accent-fuchsia));
            border-radius: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 2rem;
            position: relative;
            animation: logoFloat 3s ease-in-out infinite;
            box-shadow: 0 20px 60px rgba(59, 130, 246, 0.4);
        }

        .loading-logo::before {
            content: '';
            position: absolute;
            top: -5px;
            left: -5px;
            right: -5px;
            bottom: -5px;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-purple), var(--accent-fuchsia));
            border-radius: 35px;
            z-index: -1;
            animation: logoGlow 2s ease-in-out infinite alternate;
        }

        .loading-logo i {
            font-size: 3rem;
            color: white;
            animation: logoSpin 2s linear infinite;
        }

        .loading-text {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 2rem;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-purple));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: textPulse 2s ease-in-out infinite;
        }

        .loading-progress {
            width: 300px;
            height: 6px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 1rem;
            position: relative;
        }

        .loading-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--accent-blue), var(--accent-purple), var(--accent-fuchsia));
            border-radius: 3px;
            width: 0%;
            animation: progressFill 3s ease-in-out forwards;
            position: relative;
        }

        .loading-progress-bar::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            animation: progressShine 1.5s ease-in-out infinite;
        }

        .loading-percentage {
            font-size: 1rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        /* Main Content */
        .main-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.8s ease;
        }

        .main-container.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .access-denied-card {
            background: var(--glass-bg);
            backdrop-filter: var(--backdrop-blur);
            -webkit-backdrop-filter: var(--backdrop-blur);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 3rem;
            max-width: 600px;
            width: 100%;
            text-align: center;
            box-shadow: var(--glass-shadow);
            position: relative;
            overflow: hidden;
        }

        .access-denied-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }

        .access-denied-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-purple));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            position: relative;
            animation: iconBounce 2s ease-in-out infinite;
        }

        .access-denied-icon i {
            font-size: 2.5rem;
            color: white;
            animation: iconSpin 2s linear infinite;
        }

        .access-denied-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-purple));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .access-denied-message {
            color: var(--text-secondary);
            font-size: 1.2rem;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .user-info {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        .user-info p {
            color: var(--text-secondary);
            margin: 0.5rem 0;
            font-size: 1rem;
        }

        .user-info strong {
            color: var(--text-primary);
            font-weight: 600;
        }

        .back-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 2rem;
        }

        .btn-back {
            padding: 1rem 2rem;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            position: relative;
            overflow: hidden;
        }

        .btn-back::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .btn-back:hover::before {
            left: 100%;
        }

        .btn-back-primary {
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-purple));
            color: white;
            box-shadow: 0 4px 20px rgba(59, 130, 246, 0.3);
        }

        .btn-back-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(59, 130, 246, 0.4);
        }

        .btn-back-secondary {
            background: var(--glass-bg);
            color: var(--text-primary);
            border: 1px solid var(--glass-border);
            backdrop-filter: var(--backdrop-blur);
            -webkit-backdrop-filter: var(--backdrop-blur);
        }

        .btn-back-secondary:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
        }

        .countdown-info {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 1rem;
            margin-top: 1rem;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        .countdown-info p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin: 0;
        }

        .countdown-info strong {
            color: white;
            font-weight: 600;
        }

        /* Internet Problem Message */
        .internet-problem-message {
            background: rgba(239, 68, 68, 0.15);
            border: 2px solid rgba(239, 68, 68, 0.4);
            border-radius: 16px;
            padding: 1.5rem;
            margin: 2rem 0;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.5s ease;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(239, 68, 68, 0.2);
            display: none;
            height: 0;
            padding: 0;
            margin: 0;
            border: none;
        }

        .internet-problem-message.show {
            display: block;
            opacity: 1;
            transform: translateY(0);
            height: auto;
            padding: 1.5rem;
            margin: 2rem 0;
            border: 2px solid rgba(239, 68, 68, 0.4);
        }

        .internet-problem-message::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }

        .internet-problem-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            animation: problemPulse 2s ease-in-out infinite;
        }

        .internet-problem-icon i {
            font-size: 1.5rem;
            color: white;
        }

        .internet-problem-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #fca5a5;
            margin-bottom: 1rem;
            text-align: center;
        }

        .internet-problem-text {
            color: #fecaca;
            font-size: 1rem;
            line-height: 1.6;
            text-align: center;
            margin-bottom: 1rem;
        }

        .internet-problem-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-internet {
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .btn-internet-primary {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }

        .btn-internet-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
        }

        .btn-internet-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: #fecaca;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .btn-internet-secondary:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
        }

        /* Animations */
        @keyframes logoFloat {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        @keyframes logoGlow {
            0% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        @keyframes logoSpin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes textPulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        @keyframes progressFill {
            0% { width: 0%; }
            100% { width: 100%; }
        }

        @keyframes progressShine {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        @keyframes iconBounce {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        @keyframes iconSpin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes problemPulse {
            0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
            50% { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
        }

        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .access-denied-card {
                padding: 2rem 1.5rem;
                margin: 1rem;
            }

            .access-denied-title {
                font-size: 2rem;
            }

            .access-denied-message {
                font-size: 1.1rem;
            }

            .back-buttons {
                flex-direction: column;
                align-items: center;
            }

            .btn-back {
                width: 100%;
                max-width: 250px;
                justify-content: center;
            }

            .loading-logo {
                width: 100px;
                height: 100px;
            }

            .loading-logo i {
                font-size: 2.5rem;
            }

            .loading-text {
                font-size: 1.3rem;
            }

            .loading-progress {
                width: 250px;
            }
        }

        @media (max-width: 480px) {
            .access-denied-card {
                padding: 1.5rem 1rem;
            }

            .access-denied-title {
                font-size: 1.8rem;
            }

            .loading-logo {
                width: 80px;
                height: 80px;
            }

            .loading-logo i {
                font-size: 2rem;
            }

            .loading-text {
                font-size: 1.1rem;
            }

            .loading-progress {
                width: 200px;
            }

            .internet-problem-message {
                padding: 1rem;
                margin-top: 1rem;
            }

            .internet-problem-title {
                font-size: 1.1rem;
            }

            .internet-problem-text {
                font-size: 0.9rem;
            }

            .internet-problem-actions {
                flex-direction: column;
                align-items: center;
            }

            .btn-internet {
                width: 100%;
                max-width: 200px;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Loading Animation -->
    <div class="loading-container" id="loadingContainer">
        <div class="loading-logo">
            <i class="fas fa-rocket"></i>
        </div>
        <div class="loading-text">Загрузка ImsitShop</div>
        <div class="loading-progress">
            <div class="loading-progress-bar"></div>
        </div>
        <div class="loading-percentage" id="loadingPercentage">0%</div>
    </div>

    <!-- Main Content -->
    <div class="main-container" id="mainContainer">
        <div class="access-denied-card">
            <div class="access-denied-icon">
                <i class="fas fa-spinner"></i>
            </div>
            
            <h1 class="access-denied-title">Загрузка сайта</h1>
            
            <p class="access-denied-message">
                Пожалуйста, подождите. Вы будете перенаправлены на главную страницу.
            </p>

            <!-- Internet Problem Message -->
            <div class="internet-problem-message" id="internetProblemMessage">
                <div class="internet-problem-icon">
                    <i class="fas fa-wifi"></i>
                </div>
                <div class="internet-problem-title">Проблемы с подключением</div>
                <div class="internet-problem-text">
                    Похоже интернет опять глушат(( Подождите немного, или попробуйте подключиться к Wi-Fi. 
                    Если вы уверены что с вашим соединением все в порядке, обратитесь к администратору.
                </div>
                <div class="internet-problem-actions">
                    <button class="btn-internet btn-internet-primary" onclick="location.reload()">
                        <i class="fas fa-redo"></i>
                        Попробовать снова
                    </button>
                    <a href="client_dashboard.php" class="btn-internet btn-internet-secondary">
                        <i class="fas fa-home"></i>
                        В личный кабинет
                    </a>
                </div>
            </div>
            
            <div class="user-info">
                <p><strong>Текущий пользователь:</strong> <?php echo htmlspecialchars($currentUser['username']); ?></p>
                <p><strong>Роль:</strong> <?php echo $currentUser['role'] === 'admin' ? 'Администратор' : 'Клиент'; ?></p>
                <?php if ($currentUser['client_name']): ?>
                    <p><strong>Имя клиента:</strong> <?php echo htmlspecialchars($currentUser['client_name']); ?></p>
                <?php endif; ?>
            </div>
            
            <div class="back-buttons">
                <a href="client_dashboard.php" class="btn-back btn-back-primary">
                    <i class="fas fa-home"></i>
                    Личный кабинет
                </a>
                
                <form method="POST" action="logout.php" style="display: inline;">
                    <button type="submit" class="btn-back btn-back-secondary">
                        <i class="fas fa-sign-out-alt"></i>
                        Выйти
                    </button>
                </form>
            </div>
            
            <div class="countdown-info">
                <p><strong id="countdownText">Автоматическое перенаправление через 3 секунд...</strong></p>
            </div>
        </div>
    </div>
    
    <script>
        // Loading Animation
        let progress = 0;
        const loadingPercentage = document.getElementById('loadingPercentage');
        const loadingContainer = document.getElementById('loadingContainer');
        const mainContainer = document.getElementById('mainContainer');
        
        const progressInterval = setInterval(() => {
            progress += Math.random() * 15;
            if (progress > 100) progress = 100;
            
            loadingPercentage.textContent = Math.round(progress) + '%';
            
            if (progress >= 100) {
                clearInterval(progressInterval);
                setTimeout(() => {
                    loadingContainer.classList.add('hidden');
                    mainContainer.classList.add('visible');
                }, 500);
            }
        }, 100);

        // Countdown Timer
        let countdown = 3;
        const countdownText = document.getElementById('countdownText');
        
        const countdownInterval = setInterval(() => {
            countdown--;
            if (countdown > 0) {
                countdownText.textContent = `Автоматическое перенаправление через ${countdown} секунд...`;
            } else {
                countdownText.textContent = 'Перенаправление...';
                clearInterval(countdownInterval);
            }
        }, 1000);

        // Проблемы с интернетом if >10s на странице
        setTimeout(() => {
            console.log('Проверка что модалка появилась после загрузки');
            if (mainContainer.classList.contains('visible')) {
                const internetProblemMessage = document.getElementById('internetProblemMessage');
                if (internetProblemMessage) {
                    internetProblemMessage.classList.add('show');
                    console.log('Модалка появилась');
                } else {
                    console.log('Модалка не появилась(((');
                }
            } else {
                console.log('Модалка не появилась((( Повторная проверка через 2 секунды');
                setTimeout(() => {
                    const internetProblemMessage = document.getElementById('internetProblemMessage');
                    if (internetProblemMessage) {
                        internetProblemMessage.classList.add('show');
                        console.log('Модалка появилась (с задержкой)');
                    }
                }, 2000);
            }
        }, 10000);

        // Редирект после анимки
        setTimeout(() => {
            window.location.href = 'schedule-new.php';
        }, 4000);
    </script>
</body>
</html>