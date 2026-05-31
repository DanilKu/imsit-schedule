<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Интерактивная карта корпусов ИМСИТ">
    <meta name="keywords" content="карта, корпуса, ИМСИТ, интерактивная карта, карта корпусов">
    <meta name="author" content="ИМСИТ">
    <meta name="robots" content="index, follow">
    <meta name="googlebot" content="index, follow">
    <meta name="google" content="notranslate">
    <meta name="google" content="notranslate">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/maps.css?v=<?php echo file_exists('../cache_version.txt') ? file_get_contents('../cache_version.txt') : time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>imsitMaps</title>
</head>
<body>
    <div class="map-layout">
        <div class="map">
            <!-- Единый SVG: фон-карта как <image>, поверх — ваши пути/области -->
            <div class="map-viewport">
                <div class="map-canvas">
                    <svg id="mapSvg" class="map-svg" viewBox="0 0 750 1100" preserveAspectRatio="xMidYMid meet">
                        <image id="mapFloorImage" href="../assets/maps/floor1.svg" x="0" y="0" width="750" height="1100"/>
                        <g id="mapMarkersLayer"></g>
                    </svg>
                </div>
            </div>
        </div>
        <aside class="map-panel">
            <div class="map-panel__section">
                <div class="floor-switcher">
                    <button class="floor-btn active" data-floor="1">1</button>
                    <button class="floor-btn" data-floor="2">2</button>
                    <button class="floor-btn" data-floor="3">3</button>
                </div>
            </div>
            
        </aside>
    </div>
    
    <!-- Side modal for room details -->
    <div id="roomModal" class="room-modal" style="display:none;">
        <div class="room-modal__content">
            <div class="room-modal__header">
                <div class="room-title"><i class="fa-solid fa-door-open" style="margin-right:6px"></i><span id="roomTitle">Кабинет</span></div>
                <button id="roomModalClose" class="room-modal__close"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="room-meta">
                <img id="roomPhoto" alt="Фото кабинета" onerror="this.style.display='none'" />
                <div class="room-controls">
                    <div class="segmented">
                        <button data-week="1" class="seg-week active">1 неделя</button>
                        <button data-week="2" class="seg-week">2 неделя</button>
                    </div>
                </div>
            </div>
            <div class="room-scroll">
                <div class="room-section" data-section="now">
                    <div class="room-cards">
                        <div class="room-card" id="roomNowCard" data-state="none">
                            <div class="room-card__head"><span class="chip chip--emerald"><i class="fa-solid fa-circle"></i> Сейчас</span><span id="roomNowTime" class="small"></span></div>
                            <div class="room-card__body"><div id="roomNowTitle" class="h2"></div><div id="roomNowMeta" class="small"></div></div>
                        </div>
                        <div class="room-card" id="roomNextCard" data-state="none">
                            <div class="room-card__head"><span class="chip chip--sky"><i class="fa-solid fa-arrow-right"></i> Далее</span><span id="roomNextTime" class="small"></span></div>
                            <div class="room-card__body"><div id="roomNextTitle" class="h2"></div><div id="roomNextMeta" class="small"></div></div>
                        </div>
                    </div>
                </div>
                
                <div class="room-section" data-section="week">
                    <div class="room-section__title"><i class="fa-solid fa-calendar-week"></i> Неделя</div>
                    <div class="days">
                        <div class="days__row">
                            <button class="day-btn" data-day="1">Пн</button>
                            <button class="day-btn" data-day="2">Вт</button>
                            <button class="day-btn" data-day="3">Ср</button>
                            <button class="day-btn" data-day="4">Чт</button>
                            <button class="day-btn" data-day="5">Пт</button>
                            <button class="day-btn" data-day="6">Сб</button>
                        </div>
                    </div>
                    <div id="roomDayList" class="room-list"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function(){
        const modal = document.getElementById('roomModal');
        const closeBtn = document.getElementById('roomModalClose');
        const titleEl = document.getElementById('roomTitle');
        const photoEl = document.getElementById('roomPhoto');
        
        const mapFloorImage = document.getElementById('mapFloorImage');
        const mapMarkersLayer = document.getElementById('mapMarkersLayer');
        const floorButtons = Array.from(document.querySelectorAll('.floor-btn'));
        const dayListEl = document.getElementById('roomDayList');
        const nowCard = document.getElementById('roomNowCard');
        const nextCard = document.getElementById('roomNextCard');
        const nowTimeEl = document.getElementById('roomNowTime');
        const nowTitleEl = document.getElementById('roomNowTitle');
        const nowMetaEl = document.getElementById('roomNowMeta');
        const nextTimeEl = document.getElementById('roomNextTime');
        const nextTitleEl = document.getElementById('roomNextTitle');
        const nextMetaEl = document.getElementById('roomNextMeta');
        const sectionNow = modal.querySelector('[data-section="now"]');
        const sectionWeek = modal.querySelector('[data-section="week"]');
        const weekButtonsEls = Array.from(modal.querySelectorAll('.seg-week'));
        const dayButtons = Array.from(modal.querySelectorAll('.day-btn'));

        const floor1Markers = [
            { id: '101', x: 194, y: 111, width: 158, height: 102 },
            { id: '102', x: 142, y: 110, width: 52, height: 103 },
            { id: '103', x: 141, y: 41, width: 75, height: 40 },
            { id: '104', x: 217, y: 41, width: 71, height: 40 },
            { id: '105', x: 288, y: 41, width: 64, height: 40 },
            { id: '106', x: 352, y: 41, width: 62, height: 40 },
            { id: '107', x: 414, y: 42, width: 55, height: 40 },
            { id: '109', x: 502, y: 42, width: 105, height: 68 },
            { id: '110', x: 427, y: 110, width: 46, height: 69 },
            { id: '111', x: 378, y: 110, width: 49, height: 69 },
            { id: '112', x: 517, y: 764, width: 88, height: 52 },
            { id: '113', x: 542, y: 718, width: 63, height: 46 },
            { id: '114', x: 542, y: 671, width: 63, height: 47 },
            { id: '114a', x: 542, y: 606, width: 63, height: 65 },
            { id: '115', x: 542, y: 538, width: 63, height: 68 },
            { id: '119', x: 371, y: 567, width: 76, height: 53 },
            { id: '120', x: 301, y: 567, width: 70, height: 53 },
            { id: '121', x: 242, y: 567, width: 59, height: 53 },
            { id: '122', x: 184, y: 567, width: 59, height: 53 },
            { id: '123', x: 113, y: 567, width: 71, height: 53 },
            { id: '125', x: 185, y: 456, width: 47, height: 59 },
            { id: '126', x: 233, y: 456, width: 53, height: 59 },
            { id: '128', x: 352, y: 361, width: 90, height: 94 },
            { id: '130', x: 232, y: 215, width: 120, height: 84 },
            { id: '131', x: 352, y: 214, width: 89, height: 83 }
        ];

        const floor2Markers = [
            { id: '201', x: 200, y: 140, width: 120, height: 80 },
            { id: '202', x: 340, y: 140, width: 130, height: 80 },
            { id: '203', x: 520, y: 140, width: 100, height: 80 },
            { id: '210', x: 155, y: 560, width: 120, height: 60 },
            { id: '214', x: 520, y: 560, width: 120, height: 60 },
            { id: '220', x: 320, y: 360, width: 140, height: 95 }
        ];

        const floor3Markers = [
            { id: '301', x: 192, y: 160, width: 90, height: 70 },
            { id: '302', x: 286, y: 160, width: 90, height: 70 },
            { id: '303', x: 380, y: 160, width: 90, height: 70 },
            { id: '304', x: 474, y: 160, width: 90, height: 70 },
            { id: '312', x: 520, y: 640, width: 120, height: 60 },
            { id: '320', x: 110, y: 640, width: 120, height: 60 }
        ];

        const floors = {
            '1': { name: '1 этаж', image: '../assets/maps/floor1.svg', markers: floor1Markers },
            '2': { name: '2 этаж', image: '../assets/maps/floor2.svg', markers: floor2Markers },
            '3': { name: '3 этаж', image: '../assets/maps/floor3.svg', markers: floor3Markers }
        };

        let currentFloor = '1';

        function getTodayDayIndex(){
            const today = (new Date()).getDay();
            if (today === 0) return 6;
            return Math.min(today, 6);
        }

        const state = { week: 1, day: 1, days: {}, currentLessonNumber: null, currentLessonDay: null, todayDay: getTodayDayIndex() };
        let currentRoom = null;

        function normalizeRoom(roomId){
            const s = String(roomId || '').trim();
            const parts = s.split('-');
            const last = parts[parts.length - 1];
            // Извлекаем цифры из начала и первую букву после них (если есть)
            // Работает с форматами: "114", "114a", "114а", "114-1", "114 а" и т.д.
            const match = last.match(/^(\d+)\s*([a-zа-яё]?)/i);
            if (match) {
                let letter = match[2] ? match[2].toLowerCase() : '';
                // Преобразуем кириллические буквы в латинские для единообразия
                const cyrToLat = {'а':'a', 'б':'b', 'в':'v', 'г':'g', 'д':'d', 'е':'e', 'ё':'e', 'ж':'zh', 'з':'z', 'и':'i', 'й':'y', 'к':'k', 'л':'l', 'м':'m', 'н':'n', 'о':'o', 'п':'p', 'р':'r', 'с':'s', 'т':'t', 'у':'u', 'ф':'f', 'х':'h', 'ц':'c', 'ч':'ch', 'ш':'sh', 'щ':'sch', 'ъ':'', 'ы':'y', 'ь':'', 'э':'e', 'ю':'yu', 'я':'ya'};
                if (letter && cyrToLat[letter]) {
                    letter = cyrToLat[letter];
                }
                return match[1] + letter;
            }
            // Fallback: если не соответствует паттерну, возвращаем как есть
            return last || s;
        }

        function formatMeta(lesson){
            if (!lesson) return '';
            const parts = [];
            if (lesson.teacher_name) parts.push(lesson.teacher_name);
            if (lesson.group_name) parts.push(lesson.group_name);
            return parts.join(' • ');
        }

        function parseTimeToSeconds(value){
            if (!value) return null;
            const parts = value.split(':').map(part => parseInt(part, 10));
            if (!parts.length || isNaN(parts[0])) return null;
            const h = parts[0];
            const m = parts[1] || 0;
            const s = parts[2] || 0;
            return h * 3600 + m * 60 + s;
        }

        function openModal(){ modal.style.display = 'block'; requestAnimationFrame(()=>{ modal.classList.add('open'); }); }
        function closeModal(){ modal.classList.remove('open'); setTimeout(()=>{ modal.style.display = 'none'; }, 160); }
        if (closeBtn) closeBtn.addEventListener('click', closeModal);

        function renderNow(lesson){
            sectionNow.classList.remove('hidden');
            if (!lesson) {
                nowCard.setAttribute('data-state', 'empty');
                nowTimeEl.textContent = '';
                nowTitleEl.textContent = 'Пар сейчас нет';
                nowMetaEl.textContent = '';
                return;
            }
            nowCard.setAttribute('data-state', 'active');
            nowTimeEl.textContent = lesson.time || '';
            nowTitleEl.textContent = lesson.subject_name || '';
            nowMetaEl.textContent = formatMeta(lesson);
        }
        function renderNext(lesson){
            if (!lesson) {
                nextCard.setAttribute('data-state', 'empty');
                nextTimeEl.textContent = '';
                nextTitleEl.textContent = 'Следующих пар нет';
                nextMetaEl.textContent = '';
                return;
            }
            nextCard.setAttribute('data-state', 'active');
            nextTimeEl.textContent = lesson.time || '';
            nextTitleEl.textContent = lesson.subject_name || '';
            nextMetaEl.textContent = formatMeta(lesson);
        }
        function renderMarkers(markers){
            const markerSvg = (markers || []).map(marker => `
                <rect class="marker" id="${escapeHtml(marker.id)}"
                    x="${marker.x}" y="${marker.y}" width="${marker.width}" height="${marker.height}"
                    fill="#d9d9d9" fill-opacity="0.5" stroke="#ffffff" stroke-opacity="0.7" stroke-width="1.5" />
            `).join('');
            mapMarkersLayer.innerHTML = markerSvg;
        }

        function renderFloor(floorId){
            const target = floors[floorId] || floors['1'];
            currentFloor = String(floorId);
            if (mapFloorImage) {
                mapFloorImage.setAttribute('href', target.image);
            }
            renderMarkers(target.markers);
            floorButtons.forEach(btn => {
                const btnFloor = btn.getAttribute('data-floor');
                btn.classList.toggle('active', btnFloor === currentFloor);
            });
        }

        floorButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const targetFloor = btn.getAttribute('data-floor');
                if (!targetFloor || targetFloor === currentFloor) return;
                renderFloor(targetFloor);
            });
        });

        renderFloor(currentFloor);

        function updateWeekButtons(){
            weekButtonsEls.forEach(btn => {
                const w = Number(btn.getAttribute('data-week')) || 1;
                btn.classList.toggle('active', w === state.week);
            });
        }

        weekButtonsEls.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const w = Number(btn.getAttribute('data-week')) || 1;
                if (state.week === w && state.days) return;
                state.week = w;
                updateWeekButtons();
                if (currentRoom) loadRoom(currentRoom, w);
            });
        });

        dayButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const d = Number(btn.getAttribute('data-day'));
                state.day = d;
                updateDayButtons();
                updateCardsForCurrentDay();
            });
        });

        updateWeekButtons();
        updateDayButtons();
        updateCardsForCurrentDay();

        function updateDayButtons(){
            dayButtons.forEach(btn => {
                const d = Number(btn.getAttribute('data-day'));
                const hasData = state.days[d] && state.days[d].length;
                btn.classList.toggle('active', d === state.day);
                btn.classList.toggle('day-btn--empty', !hasData);
            });
        }

        function updateCardsForCurrentDay(){
            const dayForCards = state.todayDay;
            const items = state.days[dayForCards] || [];
            const now = new Date();
            const nowSeconds = now.getHours() * 3600 + now.getMinutes() * 60 + now.getSeconds();
            let currentItem = null;
            let nextItem = null;

            for (const item of items) {
                const start = parseTimeToSeconds(item.start_time);
                let end = parseTimeToSeconds(item.end_time);
                if (start === null) continue;
                if (end === null) end = start + 80 * 60;

                if (!currentItem && start <= nowSeconds && nowSeconds < end) {
                    currentItem = item;
                } else if (!nextItem && start > nowSeconds) {
                    nextItem = item;
                }
                if (currentItem && nextItem) break;
            }

            if (!currentItem && items.length) {
                const firstStart = parseTimeToSeconds(items[0].start_time);
                if (firstStart !== null && nowSeconds < firstStart) {
                    nextItem = items[0];
                }
            }

            state.currentLessonNumber = currentItem ? Number(currentItem.lesson_number) : null;
            state.currentLessonDay = currentItem ? dayForCards : null;

            renderNow(currentItem);
            renderNext(nextItem);
            renderDayList();
        }

        function setWeekData(days, preferredDay){
            const normalized = {};
            if (Array.isArray(days)) {
                days.forEach((items, idx) => {
                    const dayIndex = idx + 1;
                    if (dayIndex >= 1 && dayIndex <= 6) {
                        normalized[dayIndex] = items || [];
                    }
                });
            } else if (days && typeof days === 'object') {
                Object.keys(days).forEach(key => {
                    const num = parseInt(key, 10);
                    if (num >= 1 && num <= 6) {
                        normalized[num] = days[key] || [];
                    }
                });
            }
            state.days = normalized;
            const available = [];
            for (let d = 1; d <= 6; d++) {
                if (state.days[d] && state.days[d].length) available.push(d);
            }
            if (!available.length) {
                available.push(state.day >=1 && state.day <=6 ? state.day : 1);
            }
            if (preferredDay && available.includes(preferredDay)) {
                state.day = preferredDay;
            } else if (!available.includes(state.day)) {
                state.day = available[0];
            }
            updateDayButtons();
            updateCardsForCurrentDay();
        }

        function renderDayList(){
            const items = state.days[state.day] || [];
            const currentLessonNumber = state.currentLessonDay === state.day ? Number(state.currentLessonNumber) : null;
            if (!items.length) {
                dayListEl.innerHTML = '<div class="small" style="opacity:.65">—</div>';
                return;
            }
            dayListEl.innerHTML = items.map(l => {
                const lessonNo = Number(l.lesson_number);
                const isCurrent = currentLessonNumber && lessonNo === currentLessonNumber;
                return `
                <div class="room-item room-item--compact${isCurrent ? ' current' : ''}">
                    <div class="room-item__time">${escapeHtml(l.time)}</div>
                    <div class="room-item__title">${escapeHtml(l.subject_name)}</div>
                    <div class="room-item__meta small">${escapeHtml(formatMeta(l))}</div>
                </div>`;
            }).join('');
        }

        updateWeekButtons();
        updateDayButtons();

        function escapeHtml(s){ return String(s||'').replace(/[&<>"']/g, m=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[m])); }

        async function loadRoom(roomId, week){
            const r = normalizeRoom(roomId);
            titleEl.textContent = `Кабинет ${r}`;
            photoEl.style.display = '';
            photoEl.src = `../assets/rooms/${r}.jpg`;
            try {
                const targetWeek = week || state.week || 1;
                const res = await fetch(`../api/get_room_schedule.php?room=${encodeURIComponent(r)}&week=${encodeURIComponent(targetWeek)}`);
                if (!res.ok) throw new Error('Network');
                const data = await res.json();
                state.week = Number(data.week_number || targetWeek) || targetWeek;
                state.currentLessonNumber = data.current ? Number(data.current.lesson_number) : null;
                state.currentLessonDay = data.current ? Number(data.current.day ?? state.day) : null;
                updateWeekButtons();
                const today = (new Date()).getDay();
                const defaultDay = today === 0 ? 6 : Math.min(today, 6);
                const preferDay = typeof week === 'number' ? state.day : defaultDay;
                setWeekData(data.week || {}, preferDay);
            } catch (e) {
                state.currentLessonNumber = null;
                state.currentLessonDay = null;
                sectionNow.classList.remove('hidden');
                nowCard.setAttribute('data-state', 'empty');
                nowTimeEl.textContent = '';
                nowTitleEl.textContent = 'Ошибка загрузки';
                nowMetaEl.textContent = '';
                nextCard.setAttribute('data-state', 'empty');
                nextTimeEl.textContent = '';
                nextTitleEl.textContent = '';
                nextMetaEl.textContent = '';
                state.week = week || state.week || 1;
                updateWeekButtons();
                setWeekData({}, state.day);
            }
        }

        // Делегирование клика по кабинетам: реагируем только на маркеры карты
        document.addEventListener('click', function(e){
            const marker = e.target.closest && e.target.closest('.marker');
            if (!marker) return;
            const id = marker.getAttribute('id');
            if (!id) return;
            currentRoom = id;
            openModal();
            loadRoom(currentRoom);
        }, false);

        // Панорамирование и масштабирование карты
        (function(){
            const viewport = document.querySelector('.map-viewport');
            const canvas = document.querySelector('.map-canvas');
            if (!viewport || !canvas) return;

            let scale = 1;
            let panX = 0;
            let panY = 0;
            let isPanning = false;
            let pointerId = null;
            let startX = 0;
            let startY = 0;
            const minScale = 0.6;
            const maxScale = 3;

            function clamp(value, min, max){ return Math.min(Math.max(value, min), max); }
            function applyTransform(){
                canvas.style.transform = `translate(${panX}px, ${panY}px) scale(${scale})`;
            }
            applyTransform();
            viewport.style.cursor = 'grab';

            viewport.addEventListener('pointerdown', (e) => {
                if (e.button !== 0) return;
                if (e.target && e.target.closest('.marker')) return; // не мешаем клику по кабинету
                isPanning = true;
                pointerId = e.pointerId;
                startX = e.clientX - panX;
                startY = e.clientY - panY;
                viewport.setPointerCapture(pointerId);
                viewport.style.cursor = 'grabbing';
            });

            viewport.addEventListener('pointermove', (e) => {
                if (!isPanning || e.pointerId !== pointerId) return;
                panX = e.clientX - startX;
                panY = e.clientY - startY;
                applyTransform();
            });

            function endPan(e){
                if (!isPanning || (e && e.pointerId !== pointerId)) return;
                isPanning = false;
                pointerId = null;
                viewport.style.cursor = 'grab';
                try { viewport.releasePointerCapture(e.pointerId); } catch (_) {}
            }

            viewport.addEventListener('pointerup', endPan);
            viewport.addEventListener('pointerleave', endPan);

            viewport.addEventListener('wheel', (e) => {
                if (e.ctrlKey || e.metaKey) return; // даём браузеру зум страницы
                e.preventDefault();
                const rect = viewport.getBoundingClientRect();
                const offsetX = e.clientX - rect.left;
                const offsetY = e.clientY - rect.top;
                const zoomFactor = e.deltaY < 0 ? 1.12 : 0.88;
                const newScale = clamp(scale * zoomFactor, minScale, maxScale);
                if (newScale === scale) return;
                const scaleRatio = newScale / scale;
                panX = (panX - offsetX) * scaleRatio + offsetX;
                panY = (panY - offsetY) * scaleRatio + offsetY;
                scale = newScale;
                applyTransform();
            }, { passive: false });
        })();
    })();
    </script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</body>
</html>