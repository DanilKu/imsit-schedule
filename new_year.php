<?php
// Принудительно отключаем кеширование
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

date_default_timezone_set('Europe/Moscow');
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
    <meta name="theme-color" content="#1f1147">
    <title>С Новым годом! - ImsitID</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(180deg, #0B1220 0%, #353535 100%);
            background-repeat: no-repeat;
            background-attachment: fixed;
            background-size: 100% 200vh;
            background-position: top center;
            min-height: 100vh;
            min-height: -webkit-fill-available;
            font-family: 'Montserrat', system-ui, -apple-system, Segoe UI, Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Apple Color Emoji', 'Segoe UI Emoji';
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            overflow-x: hidden;
            position: relative;
        }

        @media (max-width: 768px) {
            body {
                background-attachment: scroll;
                padding: 0.75rem;
            }
        }

        .snow-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
            overflow: hidden;
        }

        .snowflake {
            position: absolute;
            color: rgba(255, 255, 255, 0.8);
            font-size: 1em;
            font-family: Arial, sans-serif;
            text-shadow: 0 0 5px rgba(255, 255, 255, 0.5);
            animation: snowfall linear;
            user-select: none;
            top: -50px;
            opacity: 0;
        }

        @keyframes snowfall {
            0% {
                transform: translateY(0) rotate(0deg);
                opacity: 0;
            }
            5% {
                opacity: 1;
            }
            95% {
                opacity: 1;
            }
            100% {
                transform: translateY(calc(100vh + 50px)) rotate(360deg);
                opacity: 0;
            }
        }

        .container {
            max-width: 420px;
            width: 100%;
            position: relative;
            z-index: 2;
        }

        .main-card {
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 24px;
            padding: 2.5rem 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3), 0 0 0 1px rgba(255, 255, 255, 0.1) inset;
            position: relative;
            overflow: hidden;
        }

        .main-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.15) 0%, rgba(59, 130, 246, 0.15) 50%, rgba(16, 185, 129, 0.15) 100%);
            animation: gradientShift 10s ease-in-out infinite;
            pointer-events: none;
            z-index: 0;
        }

        .main-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.15) 0%, rgba(16, 185, 129, 0.15) 50%, rgba(139, 92, 246, 0.15) 100%);
            animation: gradientShiftReverse 10s ease-in-out infinite;
            opacity: 0;
            pointer-events: none;
            z-index: 0;
        }

        @keyframes gradientShift {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0;
            }
        }

        @keyframes gradientShiftReverse {
            0%, 100% {
                opacity: 0;
            }
            50% {
                opacity: 1;
            }
        }

        .content {
            position: relative;
            z-index: 1;
            text-align: center;
        }

        .icon-wrapper {
            margin-bottom: 2rem;
            display: flex;
            justify-content: center;
        }

        .icon-wrapper i {
            font-size: 4rem;
            color: #10b981;
            filter: drop-shadow(0 4px 16px rgba(16, 185, 129, 0.4));
            animation: iconBounce 3s cubic-bezier(0.4, 0, 0.2, 1) infinite;
        }

        @keyframes iconBounce {
            0%, 100% {
                transform: translateY(0) rotate(0deg);
            }
            50% {
                transform: translateY(-8px) rotate(5deg);
            }
        }

        .thank-you-text {
            font-size: 1.5rem;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.95);
            margin-bottom: 2.5rem;
            line-height: 1.4;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }

        .countdown-container {
            margin-bottom: 2.5rem;
        }

        .countdown-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 1.5rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .countdown {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.75rem;
            width: 100%;
        }

        .countdown-item {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
        }

        .countdown-box {
            position: relative;
            width: 100%;
            min-height: 80px;
            background: rgba(0, 0, 0, 0.3);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            box-shadow: 
                0 4px 20px rgba(0, 0, 0, 0.3),
                inset 0 0 0 1px rgba(255, 255, 255, 0.05);
            transition: all 0.3s ease;
        }

        .countdown-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.05) 0%, transparent 50%);
            pointer-events: none;
        }

        .countdown-item:nth-child(1) .countdown-box {
            border-color: rgba(139, 92, 246, 0.3);
        }
        .countdown-item:nth-child(2) .countdown-box {
            border-color: rgba(59, 130, 246, 0.3);
        }
        .countdown-item:nth-child(3) .countdown-box {
            border-color: rgba(16, 185, 129, 0.3);
        }
        .countdown-item:nth-child(4) .countdown-box {
            border-color: rgba(236, 72, 153, 0.3);
        }

        .countdown-number {
            font-size: 2.25rem;
            font-weight: 700;
            color: #ffffff;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
            position: relative;
            z-index: 1;
            line-height: 1;
            font-family: 'Montserrat', system-ui, -apple-system, Segoe UI, Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Apple Color Emoji', 'Segoe UI Emoji';
            transition: transform 0.2s ease;
        }

        .countdown-label {
            font-size: 0.65rem;
            color: rgba(255, 255, 255, 0.6);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-family: 'Montserrat', system-ui, -apple-system, Segoe UI, Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Apple Color Emoji', 'Segoe UI Emoji';
            margin-top: 0.25rem;
        }

        .notification-text {
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.6);
            line-height: 1.5;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        @media (max-width: 480px) {
            .main-card {
                padding: 2rem 1.5rem;
            }

            .icon-wrapper i {
                font-size: 3rem;
            }

            .thank-you-text {
                font-size: 1.25rem;
                margin-bottom: 2rem;
            }

            .countdown {
                gap: 0.5rem;
            }

            .countdown-box {
                min-height: 70px;
                border-radius: 12px;
            }

            .countdown-number {
                font-size: 1.75rem;
            }

            .countdown-label {
                font-size: 0.6rem;
            }

            .notification-text {
                font-size: 0.8rem;
            }
        }

        @media (max-width: 360px) {
            .countdown {
                gap: 0.4rem;
            }

            .countdown-box {
                min-height: 65px;
            }

            .countdown-number {
                font-size: 1.5rem;
            }

            .countdown-label {
                font-size: 0.55rem;
            }
        }
    </style>
</head>
<body>
    <div class="snow-container" id="snowContainer"></div>
    
    <div class="container">
        <div class="main-card">
            <div class="content">
                <div class="icon-wrapper">
                    <i class="fas fa-tree"></i>
                </div>
                
                <div class="thank-you-text">
                    Спасибо, что были с нами в этом году!
                </div>
                
                <div class="countdown-container">
                    <div class="countdown-title">До Нового года</div>
                    <div class="countdown" id="countdown">
                        <div class="countdown-item">
                            <div class="countdown-box">
                                <span id="days" class="countdown-number">00</span>
                            </div>
                            <span class="countdown-label">дней</span>
                        </div>
                        <div class="countdown-item">
                            <div class="countdown-box">
                                <span id="hours" class="countdown-number">00</span>
                            </div>
                            <span class="countdown-label">часов</span>
                        </div>
                        <div class="countdown-item">
                            <div class="countdown-box">
                                <span id="minutes" class="countdown-number">00</span>
                            </div>
                            <span class="countdown-label">минут</span>
                        </div>
                        <div class="countdown-item">
                            <div class="countdown-box">
                                <span id="seconds" class="countdown-number">00</span>
                            </div>
                            <span class="countdown-label">секунд</span>
                        </div>
                    </div>
                </div>
                
                <div class="notification-text">
                    Мы уведомим вас, когда загрузим расписание на новый семестр
                </div>
            </div>
        </div>
    </div>

    <script>
        // Создание снежинок
        function createSnowflake() {
            const snowContainer = document.getElementById('snowContainer');
            const snowflake = document.createElement('div');
            snowflake.className = 'snowflake';
            snowflake.innerHTML = '❄';
            snowflake.style.left = Math.random() * 100 + '%';
            snowflake.style.fontSize = (Math.random() * 10 + 10) + 'px';
            const duration = Math.random() * 3 + 3; // 3-6 секунд
            snowflake.style.animationDuration = duration + 's';
            snowflake.style.animationDelay = '0s';
            snowContainer.appendChild(snowflake);
            
            // Удаляем снежинку после завершения анимации
            setTimeout(() => {
                if (snowflake.parentNode) {
                    snowflake.remove();
                }
            }, duration * 1000 + 100);
        }

        // Создаем снежинки периодически
        function createSnowflakes() {
            setInterval(createSnowflake, 300);
        }

        // Таймер обратного отсчета
        function updateCountdown() {
            const now = new Date();
            // 1 января 2026 года, 00:00:00
            const targetDate = new Date('2026-01-01T00:00:00');
            
            const diff = targetDate.getTime() - now.getTime();

            if (diff <= 0) {
                // Новый год наступил
                document.getElementById('days').textContent = '00';
                document.getElementById('hours').textContent = '00';
                document.getElementById('minutes').textContent = '00';
                document.getElementById('seconds').textContent = '00';
                return;
            }

            const totalSeconds = Math.floor(diff / 1000);
            const days = Math.floor(totalSeconds / 86400);
            const hours = Math.floor((totalSeconds % 86400) / 3600);
            const minutes = Math.floor((totalSeconds % 3600) / 60);
            const seconds = totalSeconds % 60;

            document.getElementById('days').textContent = String(days).padStart(2, '0');
            document.getElementById('hours').textContent = String(hours).padStart(2, '0');
            document.getElementById('minutes').textContent = String(minutes).padStart(2, '0');
            document.getElementById('seconds').textContent = String(seconds).padStart(2, '0');
        }

        // Инициализация
        document.addEventListener('DOMContentLoaded', function() {
            createSnowflakes();
            updateCountdown();
            setInterval(updateCountdown, 1000);
        });
    </script>
</body>
</html>

