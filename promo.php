<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ImsitID — Твой цифровой помощник</title>
    <meta name="description" content="Умное расписание, навигация и личный кабинет для студентов Академии ИМСИТ. Присоединяйся к 800+ студентам!">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js"></script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #0B1220;
            color: #ffffff;
            overflow-x: hidden;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #0B1220;
        }
        ::-webkit-scrollbar-thumb {
            background: #3B82F6;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #2563EB;
        }

        /* Background Gradient Blob */
        .blob {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 1000px;
            height: 1000px;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.15) 0%, rgba(11, 18, 32, 0) 70%);
            z-index: -1;
            pointer-events: none;
        }

        /* Glassmorphism */
        .glass {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        /* Hero Text Gradient */
        .text-gradient {
            background: linear-gradient(135deg, #60A5FA 0%, #A78BFA 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Feature Card Hover */
        .feature-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
            border-color: rgba(59, 130, 246, 0.3);
        }
        .features-grid {
            display: grid;
            grid-auto-rows: 1fr;
        }

        /* Floating Elements Animation */
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
            100% { transform: translateY(0px); }
        }
        .float {
            animation: float 6s ease-in-out infinite;
        }
        .float-delayed {
            animation: float 7s ease-in-out infinite;
            animation-delay: 1s;
        }

        /* Mouse Trail Effect */
        .cursor-glow {
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.08) 0%, rgba(11, 18, 32, 0) 70%);
            position: fixed;
            pointer-events: none;
            z-index: 0;
            transform: translate(-50%, -50%);
            transition: width 0.2s, height 0.2s;
        }

        .counter::after {
            content: '+';
            margin-left: 4px;
            font-weight: inherit;
        }
    </style>
</head>
<body class="antialiased selection:bg-blue-500 selection:text-white">

    <!-- Custom Cursor Glow -->
    <div id="cursor-glow" class="cursor-glow"></div>
    <div class="blob"></div>

    <!-- Navbar -->
    <nav class="fixed top-0 left-0 right-0 z-50 glass border-b border-white/5">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-bold">ID</div>
                    <span class="text-xl font-bold tracking-tight text-white">imsitID</span>
                </div>
                <div class="hidden md:flex items-center gap-8">
                    <a href="#features" class="text-sm font-medium text-gray-300 hover:text-white transition-colors">Возможности</a>
                    <a href="#stats" class="text-sm font-medium text-gray-300 hover:text-white transition-colors">Статистика</a>
                    <a href="#reviews" class="text-sm font-medium text-gray-300 hover:text-white transition-colors">Отзывы</a>
                    <a href="main_test.php" class="px-4 py-2 rounded-full bg-white/10 hover:bg-white/20 text-white text-sm font-medium transition-all border border-white/10">
                        Открыть приложение
                    </a>
                </div>
                <button class="md:hidden text-white p-2">
                    <i data-lucide="menu"></i>
                </button>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="relative pt-32 pb-20 lg:pt-48 lg:pb-32 overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-400 text-xs font-medium mb-8 animate-fade-in-up">
                <span class="w-2 h-2 rounded-full bg-blue-500 animate-pulse"></span>
                Уже 800+ студентов с нами
            </div>
            
            <h1 class="text-5xl md:text-7xl font-extrabold tracking-tight mb-6 leading-tight">
                Твой цифровой <br>
                <span class="text-gradient">помощник в учебе</span>
            </h1>
            
            <p class="text-lg md:text-xl text-gray-400 mb-10 max-w-2xl mx-auto leading-relaxed">
                Забудь про фото расписания в галерее. ImsitID — это умное расписание, навигация по корпусам и личный кабинет в одном удобном приложении.
            </p>
            
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="main_test.php" class="w-full sm:w-auto px-8 py-4 rounded-full bg-blue-600 hover:bg-blue-700 text-white font-semibold text-lg transition-all shadow-lg shadow-blue-600/25 flex items-center justify-center gap-2 group">
                    Начать пользоваться
                    <i data-lucide="arrow-right" class="w-5 h-5 group-hover:translate-x-1 transition-transform"></i>
                </a>
                <a href="#features" class="w-full sm:w-auto px-8 py-4 rounded-full bg-white/5 hover:bg-white/10 text-white font-semibold text-lg transition-all border border-white/10 backdrop-blur-sm">
                    Узнать больше
                </a>
            </div>

            <!-- Mockup / Visual -->
            <div class="mt-20 relative max-w-5xl mx-auto perspective-1000">
                <!-- Main Phone -->
                <div class="relative z-20 mx-auto w-[280px] md:w-[320px] rounded-[40px] border-8 border-gray-800 bg-gray-900 shadow-2xl float">
                    <div class="h-[600px] w-full rounded-[32px] overflow-hidden bg-[#0B1220] relative">
                        <!-- Header -->
                        <div class="absolute top-0 left-0 right-0 h-24 bg-gradient-to-b from-blue-900/20 to-transparent z-10"></div>
                        <!-- Content Mockup -->
                        <div class="p-6 pt-12 space-y-4">
                            <!-- Current Lesson Card -->
                            <div class="w-full p-4 rounded-2xl bg-gradient-to-r from-indigo-600 to-blue-600 shadow-lg">
                                <div class="flex justify-between items-start mb-2">
                                    <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center"><div class="w-3 h-3 bg-green-400 rounded-full animate-pulse"></div></div>
                                    <div class="text-right">
                                        <div class="text-white/80 text-xs">11:30 - 13:00</div>
                                        <div class="text-white font-bold">Проектирование</div>
                                    </div>
                                </div>
                                <div class="w-full bg-black/20 rounded-full h-1.5 mt-4">
                                    <div class="bg-white h-1.5 rounded-full w-[60%]"></div>
                                </div>
                            </div>
                            <!-- Grid -->
                            <div class="grid grid-cols-2 gap-3">
                                <div class="h-24 rounded-2xl bg-white/5 border border-white/10 flex flex-col items-center justify-center gap-2">
                                    <i data-lucide="calendar" class="text-blue-400"></i>
                                    <span class="text-xs font-medium text-gray-300">Расписание</span>
                                </div>
                                <div class="h-24 rounded-2xl bg-white/5 border border-white/10 flex flex-col items-center justify-center gap-2">
                                    <i data-lucide="map" class="text-purple-400"></i>
                                    <span class="text-xs font-medium text-gray-300">Карта</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Decorative Elements -->
                <div class="absolute top-1/2 left-0 md:-left-12 -translate-y-1/2 w-64 h-40 glass rounded-2xl p-4 z-10 float-delayed hidden lg:block transform -rotate-6">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-full bg-green-500/20 flex items-center justify-center text-green-400">
                            <i data-lucide="check-circle" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <div class="text-sm font-bold">Пара началась</div>
                            <div class="text-xs text-gray-400">Ауд. 305 • Корпус 1</div>
                        </div>
                    </div>
                    <div class="w-full h-2 bg-white/5 rounded-full overflow-hidden">
                        <div class="h-full bg-green-500 w-[30%]"></div>
                    </div>
                </div>

                <div class="absolute bottom-20 right-0 md:-right-12 w-64 glass rounded-2xl p-4 z-10 float hidden lg:block transform rotate-3">
                    <div class="text-xs text-gray-400 mb-2">Популярность групп</div>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="text-blue-400 font-bold">1</span>
                                <span class="text-sm">22-СПО-ИСиП-06</span>
                            </div>
                            <span class="text-xs bg-white/10 px-2 py-1 rounded-full">67 🔥</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="text-purple-400 font-bold">2</span>
                                <span class="text-sm">22-СПО-ГД-01</span>
                            </div>
                            <span class="text-xs bg-white/10 px-2 py-1 rounded-full">49 🔥</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section id="stats" class="py-20 border-y border-white/5 bg-white/[0.02]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
                <div class="space-y-2">
                    <div class="text-4xl md:text-5xl font-bold text-white counter" data-target="800">0</div>
                    <div class="text-sm text-gray-400 uppercase tracking-wider">Студентов</div>
                </div>
                <div class="space-y-2">
                    <div class="text-4xl md:text-5xl font-bold text-white counter" data-target="90">0</div>
                    <div class="text-sm text-gray-400 uppercase tracking-wider">Групп</div>
                </div>
                <div class="space-y-2">
                    <div class="text-4xl md:text-5xl font-bold text-white counter" data-target="120">0</div>
                    <div class="text-sm text-gray-400 uppercase tracking-wider">Преподавателей</div>
                </div>
                <div class="space-y-2">
                    <div class="text-4xl md:text-5xl font-bold text-white counter" data-target="100">0</div>
                    <div class="text-sm text-gray-400 uppercase tracking-wider">Аудиторий</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-32 relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-20">
                <h2 class="text-3xl md:text-4xl font-bold mb-6">Всё, что нужно для учебы</h2>
                <p class="text-gray-400 text-lg">
                    Мы собрали все необходимые инструменты в одном месте, чтобы сделать твою студенческую жизнь проще и комфортнее.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 features-grid">
                <!-- Feature 1 -->
                <div class="glass rounded-3xl p-8 feature-card relative overflow-hidden group">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-blue-500/10 rounded-full blur-2xl group-hover:bg-blue-500/20 transition-colors"></div>
                    <div class="w-14 h-14 rounded-2xl bg-blue-500/10 flex items-center justify-center text-blue-400 mb-6">
                        <i data-lucide="calendar-clock" class="w-7 h-7"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-3 text-white">Умное расписание</h3>
                    <p class="text-gray-400 leading-relaxed">
                        Актуальное расписание с учетом замен. Показывает текущую пару, время до конца и следующую аудиторию.
                    </p>
                </div>

                <!-- Feature 2 -->
                <div class="glass rounded-3xl p-8 feature-card relative overflow-hidden group">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-purple-500/10 rounded-full blur-2xl group-hover:bg-purple-500/20 transition-colors"></div>
                    <div class="w-14 h-14 rounded-2xl bg-purple-500/10 flex items-center justify-center text-purple-400 mb-6">
                        <i data-lucide="map-pin" class="w-7 h-7"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-3 text-white">Навигация</h3>
                    <p class="text-gray-400 leading-relaxed">
                        Интерактивная карта корпусов поможет найти нужную аудиторию и не опоздать на пару.
                    </p>
                </div>

                <!-- Feature 3 -->
                <div class="glass rounded-3xl p-8 feature-card relative overflow-hidden group">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-orange-500/10 rounded-full blur-2xl group-hover:bg-orange-500/20 transition-colors"></div>
                    <div class="w-14 h-14 rounded-2xl bg-orange-500/10 flex items-center justify-center text-orange-400 mb-6">
                        <i data-lucide="star" class="w-7 h-7"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-3 text-white">Избранное</h3>
                    <p class="text-gray-400 leading-relaxed">
                        Сохраняй расписание своей группы, друзей или любимых преподавателей для быстрого доступа.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-20 relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-b from-blue-900/10 to-transparent pointer-events-none"></div>
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10">
            <h2 class="text-4xl md:text-5xl font-bold mb-8">Готов сделать учебу проще?</h2>
            <p class="text-xl text-gray-400 mb-10">
                Присоединяйся к сообществу студентов ИМСИТ, которые уже используют ImsitID каждый день.
            </p>
            <a href="main_test.php" class="inline-flex items-center gap-3 px-10 py-5 rounded-full bg-white text-blue-900 font-bold text-lg hover:bg-gray-100 transition-all transform hover:scale-105 shadow-xl shadow-white/10">
                Открыть в браузере
                <i data-lucide="external-link" class="w-5 h-5"></i>
            </a>
            <div class="mt-8 text-sm text-gray-500">
                Доступно на любом устройстве • Регистрация не обязательна
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="border-t border-white/5 py-12 bg-black/20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row justify-between items-center gap-6">
            <div class="flex items-center gap-2">
                <div class="w-6 h-6 rounded bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white text-xs font-bold">ID</div>
                <span class="font-semibold">imsitID</span>
            </div>
            <div class="text-gray-500 text-sm">
                © <?php echo date('Y'); ?> ImsitID. Все права защищены.
            </div>
            <div class="flex gap-6">
                <a href="#" class="text-gray-400 hover:text-white transition-colors"><i data-lucide="message-circle"></i></a>
                <a href="#" class="text-gray-400 hover:text-white transition-colors"><i data-lucide="github"></i></a>
            </div>
        </div>
    </footer>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Custom Cursor
        const cursor = document.getElementById('cursor-glow');
        document.addEventListener('mousemove', (e) => {
            cursor.style.left = e.clientX + 'px';
            cursor.style.top = e.clientY + 'px';
        });

        // GSAP Animations
        gsap.registerPlugin(ScrollTrigger);

        // Counters Animation
        const counters = document.querySelectorAll('.counter');
        counters.forEach(counter => {
            const target = +counter.getAttribute('data-target');
            gsap.to(counter, {
                innerText: target,
                duration: 2,
                snap: { innerText: 1 },
                scrollTrigger: {
                    trigger: counter,
                    start: "top 85%",
                    once: true
                }
            });
        });

        // Features Stagger
        gsap.from('.feature-card', {
            y: 50,
            opacity: 0,
            duration: 0.8,
            stagger: 0.2,
            scrollTrigger: {
                trigger: '#features',
                start: "top 75%"
            }
        });

        // Navbar Blur on Scroll
        window.addEventListener('scroll', () => {
            const nav = document.querySelector('nav');
            if (window.scrollY > 50) {
                nav.classList.add('shadow-lg');
                nav.style.background = 'rgba(11, 18, 32, 0.8)';
            } else {
                nav.classList.remove('shadow-lg');
                nav.style.background = 'rgba(255, 255, 255, 0.03)';
            }
        });
    </script>
</body>
</html>

