<?php
// Принудительно отключаем кеширование
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Технические работы - imsitID</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/main.css?v=<?php echo time(); ?>">
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <style>
      .g-class {
        background: linear-gradient(180deg, #0B1220 0%, #353535 100%) !important;
        background-repeat: no-repeat !important;
        background-attachment: fixed !important;
        background-size: 100% 200vh !important;
        background-position: top center !important;
        margin: 0 !important;
        min-height: 100vh !important;
        height: 100% !important;
        font-family: 'Montserrat', system-ui, -apple-system, Segoe UI, Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Apple Color Emoji', 'Segoe UI Emoji';
      }
      
      /* Исправление для мобильных */
      @media (max-width: 768px) {
        .g-class {
          background-attachment: scroll !important;
          background-size: 100% 200vh !important;
          background-position: top center !important;
          min-height: -webkit-fill-available !important;
        }
      }

      .FullScreen {
        display: flex;
        min-height: 100vh;
        min-height: -webkit-fill-available;
        width: 100%;
        justify-content: center;
      }

      .On-header {
        width: 100%;
        max-width: 420px;
        padding-left: 1rem;
        padding-right: 1rem;
        padding-bottom: calc(80px + env(safe-area-inset-bottom));
      }

      .Header {
        margin-left: -1rem;
        margin-right: -1rem;
      }

      .Header-container {
        position: relative;
        width: 100%;
        overflow: visible;
        border-radius: 25px;
        background: none;
        min-height: 83px;
      }

      .Def-Header {
        display: flex;
        height: 83px;
        align-items: center;
        justify-content: space-between;
        padding-left: 1rem;
        padding-right: 1.5rem;
      }

      .Def-Header-Container {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        user-select: none;
      }

      .Def-Header-Text {
        background-clip: text;
        font-size: 16px;
        font-weight: 600;
        line-height: 19.5px;
        color: transparent;
        background-image: linear-gradient(90deg, #FFFFFF 0%, #999999 100%);
      }

      /* Maintenance Content */
      .maintenance-container {
        margin-top: 2rem;
        padding: 2rem 1rem;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        min-height: 60vh;
      }

      .maintenance-icon {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.2) 0%, rgba(139, 92, 246, 0.2) 100%);
        border: 2px solid rgba(99, 102, 241, 0.3);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 2rem;
        animation: pulse-icon 2s ease-in-out infinite;
      }

      @keyframes pulse-icon {
        0%, 100% {
          transform: scale(1);
          opacity: 1;
        }
        50% {
          transform: scale(1.05);
          opacity: 0.9;
        }
      }

      .maintenance-icon i {
        font-size: 48px;
        color: #6366f1;
      }

      .maintenance-title {
        font-size: 24px;
        font-weight: 600;
        color: #ffffff;
        margin-bottom: 1rem;
        line-height: 1.3;
      }

      .maintenance-message {
        font-size: 16px;
        color: rgba(255, 255, 255, 0.8);
        margin-bottom: 2rem;
        line-height: 1.6;
        max-width: 320px;
      }

      .maintenance-bot-info {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 16px;
        padding: 1.5rem;
        margin-top: 1.5rem;
        max-width: 360px;
        width: 100%;
      }

      .maintenance-bot-title {
        font-size: 18px;
        font-weight: 600;
        color: #ffffff;
        margin-bottom: 0.75rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
      }

      .maintenance-bot-title i {
        color: #60a5fa;
      }

      .maintenance-bot-text {
        font-size: 14px;
        color: rgba(255, 255, 255, 0.7);
        line-height: 1.6;
        margin-bottom: 1rem;
      }

      .maintenance-bot-features {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        margin-top: 1rem;
      }

      .maintenance-bot-feature {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-size: 14px;
        color: rgba(255, 255, 255, 0.8);
        text-align: left;
      }

      .maintenance-bot-feature i {
        color: #10b981;
        font-size: 16px;
        width: 20px;
        flex-shrink: 0;
      }

      .maintenance-status {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        background: rgba(16, 185, 129, 0.1);
        border: 1px solid rgba(16, 185, 129, 0.3);
        border-radius: 12px;
        font-size: 13px;
        color: #10b981;
        margin-top: 1rem;
      }

      .maintenance-status i {
        font-size: 12px;
      }

      /* Bottom Navigation */
      .bottom-navigation {
        position: fixed;
        bottom: 0;
        left: 50%;
        transform: translateX(-50%);
        width: 100%;
        max-width: 420px;
        background: #1a1a1a;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 25px;
        display: flex;
        justify-content: space-around;
        align-items: center;
        padding: 0.5rem 0;
        padding-bottom: calc(0.5rem + env(safe-area-inset-bottom));
        z-index: 1000;
        box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.3);
      }

      .nav-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.25rem;
        padding: 0.5rem 0.75rem;
        text-decoration: none;
        color: rgba(255, 255, 255, 0.6);
        transition: all 0.2s ease;
        position: relative;
        flex: 1;
        max-width: 25%;
      }

      .nav-item:hover {
        color: rgba(255, 255, 255, 0.8);
      }

      .nav-item.active {
        color: #3b82f6;
      }

      .nav-item.active .nav-icon {
        color: #3b82f6;
      }

      .nav-icon {
        font-size: 20px;
        color: inherit;
        transition: color 0.2s ease;
      }

      .nav-label {
        font-size: 11px;
        font-weight: 500;
        color: inherit;
        text-align: center;
        line-height: 1.2;
      }

      .nav-avatar {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        position: relative;
        flex-shrink: 0;
      }

      .nav-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 50%;
        display: block;
      }

      .nav-icon-fallback {
        font-size: 14px;
        color: rgba(255, 255, 255, 0.6);
        display: block;
      }

      .nav-item.active .nav-avatar {
        background: rgba(59, 130, 246, 0.2);
        border: 2px solid #3b82f6;
      }

      .nav-item.active .nav-icon-fallback {
        color: #3b82f6;
      }

      @media (max-width: 480px) {
        .maintenance-icon {
          width: 100px;
          height: 100px;
        }

        .maintenance-icon i {
          font-size: 40px;
        }

        .maintenance-title {
          font-size: 20px;
        }

        .maintenance-message {
          font-size: 14px;
        }

        .nav-label {
          font-size: 10px;
        }

        .nav-icon {
          font-size: 18px;
        }

        .nav-item {
          padding: 0.4rem 0.5rem;
        }
      }
    </style>
</head>
<body class="g-class">
    <div class="FullScreen" style="box-sizing: border-box;">
        <div class="On-header">
            <!-- Maintenance Content -->
            <div class="maintenance-container">
                <div class="maintenance-icon">
                    <i class="fas fa-tools"></i>
                </div>

                <h1 class="maintenance-title">
                    Проводятся технические работы
                </h1>

                <p class="maintenance-message">
                    Мы обновляем расписание, скоро вернемся.
                </p>

                <div class="maintenance-status">
                    <i class="fas fa-circle"></i>
                    <span>Расписание работает</span>
                </div>

                <div class="maintenance-bot-info">
                    <div class="maintenance-bot-title">
                        <i class="fab fa-telegram"></i>
                        <span>Расписание в боте</span>
                    </div>
                    <p class="maintenance-bot-text">
                        Расписание продолжает работать через команды и кнопки в Telegram-боте.
                    </p>
                    <div class="maintenance-bot-features">
                        <div class="maintenance-bot-feature">
                            <i class="fas fa-check-circle"></i>
                            <span>Просмотр расписания по командам</span>
                        </div>
                        <div class="maintenance-bot-feature">
                            <i class="fas fa-check-circle"></i>
                            <span>Выбор группы через кнопки</span>
                        </div>
                        <div class="maintenance-bot-feature">
                            <i class="fas fa-check-circle"></i>
                            <span>Расписание преподавателей</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Загрузка аватара Telegram пользователя
        function loadTelegramAvatar() {
            const navAvatar = document.getElementById('navAvatar');
            if (!navAvatar) return;

            if (navAvatar.querySelector('img')) {
                return;
            }

            let photoUrl = null;

            if (typeof window.Telegram !== 'undefined' && window.Telegram.WebApp) {
                const webApp = window.Telegram.WebApp;
                const initData = webApp.initDataUnsafe;
                
                if (initData) {
                    if (initData.user && initData.user.photo_url) {
                        photoUrl = initData.user.photo_url;
                    } else if (initData.user && initData.user.photo) {
                        photoUrl = initData.user.photo;
                    } else if (initData.photo_url) {
                        photoUrl = initData.photo_url;
                    }
                }
            }

            if (!photoUrl) {
                try {
                    const savedAvatar = localStorage.getItem('telegram_avatar_url');
                    if (savedAvatar) {
                        photoUrl = savedAvatar;
                    }
                } catch (e) {}
            }

            if (photoUrl) {
                const img = document.createElement('img');
                img.src = photoUrl;
                img.alt = 'Profile';
                img.style.width = '100%';
                img.style.height = '100%';
                img.style.objectFit = 'cover';
                img.style.borderRadius = '50%';
                img.onload = function() {
                    const fallbackIcon = navAvatar.querySelector('.nav-icon-fallback');
                    if (fallbackIcon) {
                        fallbackIcon.style.display = 'none';
                    }
                };
                img.onerror = function() {
                    this.style.display = 'none';
                };
                navAvatar.appendChild(img);
                
                if (photoUrl && photoUrl !== localStorage.getItem('telegram_avatar_url')) {
                    try {
                        localStorage.setItem('telegram_avatar_url', photoUrl);
                    } catch (e) {}
                }
                return;
            }

            if (typeof window.Telegram !== 'undefined' && window.Telegram.WebApp) {
                fetch('api/check_telegram_auth.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        initDataUnsafe: window.Telegram.WebApp.initDataUnsafe
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.user && data.user.photo_url) {
                        const img = document.createElement('img');
                        img.src = data.user.photo_url;
                        img.alt = 'Profile';
                        img.style.width = '100%';
                        img.style.height = '100%';
                        img.style.objectFit = 'cover';
                        img.style.borderRadius = '50%';
                        img.onload = function() {
                            const fallbackIcon = navAvatar.querySelector('.nav-icon-fallback');
                            if (fallbackIcon) {
                                fallbackIcon.style.display = 'none';
                            }
                        };
                        img.onerror = function() {
                            this.style.display = 'none';
                        };
                        navAvatar.appendChild(img);
                        
                        try {
                            localStorage.setItem('telegram_avatar_url', data.user.photo_url);
                        } catch (e) {}
                    }
                })
                .catch(error => {
                    console.log('Avatar load error:', error);
                });
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            if (typeof window.Telegram !== 'undefined' && window.Telegram.WebApp) {
                window.Telegram.WebApp.ready();
                window.Telegram.WebApp.expand();
            }
            loadTelegramAvatar();
            setTimeout(loadTelegramAvatar, 500);
            setTimeout(loadTelegramAvatar, 1500);
        });
    </script>
</body>
</html>

