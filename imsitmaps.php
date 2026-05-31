<!DOCTYPE html>
<html lang="ru">
<head>
        <!-- Yandex.Metrika counter -->
    <script type="text/javascript">
        (function(m,e,t,r,i,k,a){
            m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
            m[i].l=1*new Date();
            for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
            k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)
        })(window, document,'script','https://mc.yandex.ru/metrika/tag.js?id=104848631', 'ym');

        ym(104848631, 'init', {ssr:true, webvisor:true, clickmap:true, ecommerce:"dataLayer", accurateTrackBounce:true, trackLinks:true});
    </script>
    <noscript><div><img src="https://mc.yandex.ru/watch/104848631" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
    <!-- /Yandex.Metrika counter -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>imsitMaps — интерактивная карта ИМСИТ</title>
    <meta name="description" content="imsitMaps — интерактивная карта главного корпуса ИМСИТ с привязкой к расписанию. Находите аудитории, преподавателей и пары на карте в режиме реального времени.">
    <meta name="theme-color" content="#0b1220">
    <link rel="icon" href="assets/icons/favicon.svg" type="image/svg+xml">
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
        min-height: 60px;
      }

      .Def-Header {
        display: flex;
        height: 60px;
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
        font-size: 15px;
        font-weight: 600;
        line-height: 18px;
        color: transparent;
        background-image: linear-gradient(90deg, #FFFFFF 0%, #999999 100%);
      }

      .Def-Header-Icons {
        display: flex;
        align-items: center;
        gap: 1.75rem;
      }

      .Def-Header-Button {
        cursor: pointer;
        transition-property: opacity;
        transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
        transition-duration: 150ms;
        opacity: 0.8;
        background: transparent;
        border: none;
        padding: 0;
      }

      .Def-Header-Button:hover {
        opacity: 1;
      }

      /* Content Area */
      .content-area {
        margin-top: -1.5rem;
        padding-bottom: 1rem;
      }

      /* Hero Section */
      .hero {
        margin-top: 0.5rem;
        padding: 1.5rem;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        display: grid;
        grid-template-columns: 1fr;
        gap: 1.25rem;
        align-items: center;
      }

      .hero h2 {
        margin: 0 0 0.6rem;
        font-size: 1.5rem;
        letter-spacing: -0.02em;
        color: #ffffff;
        font-weight: 600;
        line-height: 1.3;
      }

      .hero p {
        margin: 0 0 1.25rem;
        color: rgba(255, 255, 255, 0.7);
        line-height: 1.6;
        font-size: 14px;
      }

      .hero .actions {
        display: flex;
        gap: 0.6rem;
        flex-wrap: wrap;
      }

      .preview {
        position: relative;
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 16px;
        background: linear-gradient(180deg, rgba(56,189,248,0.08), rgba(124,58,237,0.08));
        min-height: 200px;
        overflow: hidden;
        box-shadow: inset 0 1px 0 rgba(255,255,255,.06), 0 16px 50px rgba(0,0,0,.25);
      }

      .preview .badge {
        position: absolute;
        top: 12px;
        left: 12px;
        background: rgba(15,23,42,.9);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 9999px;
        padding: 0.35rem 0.6rem;
        font-size: 0.75rem;
        color: rgba(255,255,255,0.6);
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        z-index: 2;
      }

      .preview .map {
        position: absolute;
        inset: 0;
        display: grid;
        place-items: center;
        color: #93c5fd;
        font-size: 70px;
        opacity: 0.8;
        filter: drop-shadow(0 8px 30px rgba(56,189,248,.35));
      }

      .btn {
        border: 1px solid rgba(255,255,255,0.1);
        color: #ffffff;
        background: rgba(255,255,255,0.05);
        padding: 0.6rem 1rem;
        border-radius: 12px;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.9rem;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
      }

      .btn:hover {
        background: rgba(255,255,255,0.08);
        border-color: rgba(255,255,255,0.2);
        transform: translateY(-1px);
      }

      .btn--accent {
        background: linear-gradient(135deg, #f59e0b, #f97316);
        color: #0b0f16;
        border-color: rgba(255,255,255,0.25);
        box-shadow: 0 10px 30px rgba(249,115,22,.35);
      }

      .btn--accent:hover {
        filter: brightness(1.06);
      }

      /* Section */
      .section {
        margin-top: 2rem;
      }

      .section h3 {
        margin: 0 0 0.9rem;
        font-size: 1.25rem;
        color: #ffffff;
        font-weight: 600;
      }

      .grid {
        display: grid;
        gap: 0.9rem;
        grid-template-columns: 1fr;
      }

      .card {
        background: rgba(255,255,255,0.05);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 16px;
        padding: 1rem;
        transition: background 0.2s ease, transform 0.2s ease;
      }

      .card:hover {
        background: rgba(255,255,255,0.08);
        transform: translateY(-2px);
      }

      .card .title {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        font-weight: 700;
        margin-bottom: 0.4rem;
        color: #ffffff;
        font-size: 15px;
      }

      .card .title i {
        color: #38bdf8;
      }

      .card p {
        margin: 0;
        color: rgba(255,255,255,0.7);
        font-size: 0.95rem;
        line-height: 1.6;
      }

      .steps {
        display: grid;
        gap: 0.9rem;
        grid-template-columns: 1fr;
      }

      .step {
        background: rgba(255,255,255,0.05);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 16px;
        padding: 1rem;
        display: flex;
        gap: 0.9rem;
        align-items: flex-start;
      }

      .step .num {
        width: 34px;
        height: 34px;
        border-radius: 10px;
        background: linear-gradient(135deg, #7c3aed, #38bdf8);
        display: grid;
        place-items: center;
        font-weight: 800;
        color: #ffffff;
        flex-shrink: 0;
      }

      .step h4 {
        margin: 0 0 0.3rem;
        font-size: 1rem;
        color: #ffffff;
        font-weight: 600;
      }

      .step p {
            margin: 0;
        color: rgba(255,255,255,0.7);
        font-size: 14px;
        line-height: 1.5;
      }

      .cta-block {
        margin-top: 2rem;
        display: flex;
        flex-direction: column;
        gap: 1rem;
        border: 1px solid rgba(255,255,255,0.1);
        background: rgba(255,255,255,0.05);
        border-radius: 16px;
        padding: 1rem 1.25rem;
      }

      .cta-block .text {
        color: rgba(255,255,255,0.7);
        font-size: 14px;
        line-height: 1.6;
      }

      .cta-block .text strong {
        color: #ffffff;
        font-weight: 600;
      }

      footer {
        margin-top: 2rem;
        color: rgba(255,255,255,0.6);
        text-align: center;
        font-size: 0.9rem;
        padding-bottom: 1rem;
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
        .hero h2 {
          font-size: 1.25rem;
        }

        .hero p {
          font-size: 13px;
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
            <!-- Header -->
            <div class="Header">
                <div class="Header-container">
                    <div class="Def-Header">
                        <div class="Def-Header-Container">
                            <div class="Def-Header-Text">
                                imsitMaps
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="content-area">
        <section class="hero">
            <div>
                <h2>Интерактивная карта главного корпуса с привязкой к занятиям</h2>
                <p>
                    imsitMaps покажет, в какой аудитории прямо сейчас идет пара, где преподаватель читает лекцию,
                    и как быстрее всего добраться до нужного кабинета. Все данные синхронизируются с расписанием imsitID.
                </p>
                <div class="actions">
                    <a class="btn btn--accent" href="#features"><i class="fa-solid fa-bolt"></i> Что внутри</a>
                    <a class="btn" href="#how"><i class="fa-solid fa-circle-play"></i> Как это работает</a>
                </div>
            </div>
            <div class="preview">
                <span class="badge"><i class="fa-solid fa-signal"></i> Карта загружается...</span>
                <div class="map"><i class="fa-solid fa-location-crosshairs"></i></div>
            </div>
        </section>

        <section id="features" class="section">
            <h3>Возможности</h3>
            <div class="grid">
                <div class="card">
                    <div class="title"><i class="fa-solid fa-layer-group"></i> Этажи и зоны</div>
                    <p>Чистая визуализация этажей: аудитории, кабинеты, переходы и лестничные клетки.</p>
                </div>
                <div class="card">
                    <div class="title"><i class="fa-solid fa-person-chalkboard"></i> Привязка к расписанию</div>
                    <p>Текущие и следующие занятия на карте. Фильтры по группе, преподавателю и времени.</p>
                </div>
                <div class="card">
                    <div class="title"><i class="fa-solid fa-magnifying-glass"></i> Умный поиск</div>
                    <p>Находите аудитории, предметы, группы и преподавателей — мгновенно и с подсказками.</p>
                </div>
                <div class="card">
                    <div class="title"><i class="fa-solid fa-route"></i> Навигация внутри здания</div>
                    <p>Пошаговые подсказки: куда повернуть и на какой этаж подняться, чтобы успеть на пару.</p>
                </div>
                <div class="card">
                    <div class="title"><i class="fa-solid fa-sliders"></i> Фильтры и режимы</div>
                    <p>Слои по типам аудиторий, цветовые акценты для лекций и практик, режим «тихий этаж».</p>
                </div>
                <div class="card">
                    <div class="title"><i class="fa-solid fa-mobile-screen"></i> Мобильность</div>
                    <p>Отлично работает на смартфонах. Полная адаптация к каждому устройству.</p>
                </div>
            </div>
        </section>

        <section id="how" class="section">
            <h3>Как это будет работать</h3>
            <div class="steps">
                <div class="step">
                    <div class="num">1</div>
                    <div>
                        <h4>Данные</h4>
                        <p>Импорт и синхронизация аудитории ↔ расписание из imsitID. Обновления в реальном времени.</p>
                    </div>
                </div>
                <div class="step">
                    <div class="num">2</div>
                    <div>
                        <h4>Карта</h4>
                        <p>Точная схема этажей с интерактивными элементами: кабинеты, служебные зоны, маршрутные точки.</p>
                    </div>
                </div>
                <div class="step">
                    <div class="num">3</div>
                    <div>
                        <h4>Интерфейс</h4>
                        <p>Поиск, фильтры, подсказки навигации и карточки занятий — всё в одном экране.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="cta-block">
            <div class="text">
                <strong>imsitMaps</strong> — часть экосистемы imsitID. Хотите подключиться к бета-тесту или помочь с планами этажей?
            </div>
            <div class="actions">
                <a class="btn btn--accent" href="https://t.me/cowgivesmilk" target="_blank" rel="noopener"><i class="fa-brands fa-telegram"></i>Telegram</a>
            </div>
        </section>

        <footer>
            <div>© imsitID · 2026</div>
            <div style="margin-top:.25rem">Сделано с <i class="fa-solid fa-heart" style="color:#ef4444"></i> для студентов и преподавателей</div>
        </footer>
            </div>
        </div>
    </div>

    <!-- Bottom Navigation -->
    <div class="bottom-navigation">
        <a href="main_test.php" class="nav-item<?php echo basename($_SERVER['PHP_SELF']) === 'main_test.php' ? ' active' : ''; ?>">
            <i class="fas fa-home nav-icon"></i>
            <span class="nav-label">Главная</span>
        </a>
        <a href="id.php" class="nav-item<?php echo basename($_SERVER['PHP_SELF']) === 'id.php' ? ' active' : ''; ?>">
            <i class="fas fa-calendar-alt nav-icon"></i>
            <span class="nav-label">Расписание</span>
        </a>
        <a href="imsitmaps.php" class="nav-item<?php echo basename($_SERVER['PHP_SELF']) === 'imsitmaps.php' ? ' active' : ''; ?>">
            <i class="fas fa-map nav-icon"></i>
            <span class="nav-label">Карта</span>
        </a>
        <a href="profile" class="nav-item<?php echo basename($_SERVER['PHP_SELF']) === 'client_dashboard.php' ? ' active' : ''; ?>">
            <div class="nav-avatar" id="navAvatar">
                <i class="fas fa-user nav-icon-fallback"></i>
            </div>
            <span class="nav-label">Профиль</span>
        </a>
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

        // Анимация заголовка
        (function(){
            const hero = document.querySelector('.hero h2');
            if (!hero) return;
            hero.style.opacity = '0';
            hero.style.transform = 'translateY(6px)';
            requestAnimationFrame(() => {
                hero.style.transition = 'all .6s cubic-bezier(.2,.8,.2,1)';
                hero.style.opacity = '1';
                hero.style.transform = 'translateY(0)';
            });
        })();
    </script>
</body>
</html>
