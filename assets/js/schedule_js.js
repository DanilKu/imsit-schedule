// Локальный JS для shedule2.php. Мобильный приоритет и минимум зависимостей
(function() {
    'use strict';

    // Утилиты
    const $ = (s, el = document) => el.querySelector(s);
    const $$ = (s, el = document) => Array.from(el.querySelectorAll(s));

    const DAYS = [
        { key: "Пн", full: "Понедельник" },
        { key: "Вт", full: "Вторник" },
        { key: "Ср", full: "Среда" },
        { key: "Чт", full: "Четверг" },
        { key: "Пт", full: "Пятница" },
        { key: "Сб", full: "Суббота" },
        { key: "Вс", full: "Воскресенье" }
    ];

    function pad(n) { return String(n).padStart(2, '0'); }
    function nowString() { const d = new Date(); return `${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`; }

    // Форматируем оставшееся время до конца пары: ~5м, ~10м, ... или "меньше минуты"
    function computeRemainingLabel(endTimeStr) {
        if (!endTimeStr || typeof endTimeStr !== 'string') return '';
        const parts = endTimeStr.split(':');
        const h = Number(parts[0] || 0);
        const m = Number(parts[1] || 0);
        const s = Number(parts[2] || 0);
        const now = new Date();
        const end = new Date();
        end.setHours(h, m, s || 0, 0);
        let diffMs = end.getTime() - now.getTime();
        if (diffMs <= 60000) return 'меньше минуты';
        const minutesLeft = Math.ceil(diffMs / 60000);
        const roundedToFive = Math.ceil(minutesLeft / 5) * 5;
        return `~${roundedToFive}м`;
    }

    // Бутстрап данные с сервера
    const BOOT = window.SCHEDULE_BOOTSTRAP || { week: 1, day: 1, group: '', teacher: null, viewMode: 'group', daySchedule: [], allSchedules: {} };

    const state = {
        week: Number(BOOT.week) || 1,
        day: Number(BOOT.day) || 1,
        group: BOOT.group || '',
        teacher: BOOT.teacher || null,
        viewMode: BOOT.viewMode || 'group',
        allSchedules: BOOT.allSchedules || {} // [week][day] = расписание
    };

    // Куки
    function setCookie(name, value, days) {
        const expires = new Date();
        expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
        document.cookie = name + '=' + value + ';expires=' + expires.toUTCString() + ';path=/';
    }
    function delCookie(name){ document.cookie = name + '=; Max-Age=0; path=/'; }

    function updateGroupInState(newGroup) {
        state.group = newGroup;
        state.teacher = null; // сбрасываем преподавателя
        state.viewMode = 'group';
        setCookie('selected_group', newGroup, 30);
        setCookie('view_mode', 'group', 30);
        delCookie('selected_teacher');
    }

    function updateTeacherInState(teacherName) {
        state.teacher = teacherName;
        state.group = ''; // сбрасываем группу
        state.viewMode = 'teacher';
        setCookie('selected_teacher', teacherName, 30);
        setCookie('view_mode', 'teacher', 30);
        delCookie('selected_group');
    }

    // Выбор группы
    window.showGroupSelectionModal = function showGroupSelectionModal() {
        // Закрываем модалку настроек если она открыта
        const settingsModal = $('#settingsModal');
        if (settingsModal && settingsModal.style.display === 'flex') {
            settingsModal.style.display = 'none';
        }
        
        const m = $('#groupSelectionModal');
        if (m) m.style.display = 'flex';
    };

    window.selectGroup = function selectGroup(group) {
        updateGroupInState(group);
        setCookie('selected_teacher', '', -1); // удаляем выбор преподавателя
        window.location.href = `?week=${state.week}&day=${state.day}&group=${group}`;
    };

    // Настройки
    window.showSettingsModal = function showSettingsModal() {
        console.log('showSettingsModal called');
        const m = document.getElementById('settingsModal');
        console.log('Modal element:', m);
        if (m) {
            m.style.display = 'flex';
            console.log('Modal display set to flex');
        } else {
            console.error('Settings modal not found');
        }
    };

    window.closeSettingsModal = function closeSettingsModal() {
        console.log('closeSettingsModal called');
        const m = document.getElementById('settingsModal');
        if (m) {
            m.style.display = 'none';
            console.log('Settings modal closed');
        } else {
            console.error('Settings modal not found for closing');
        }
    };

    window.switchToNewDesign = function switchToNewDesign() {
        setCookie('design_preference', 'new', 365);
        window.location.href = 'schedule-new.php';
    };

    // Выбор преподавателя
    window.showTeacherSelectionModal = function showTeacherSelectionModal() {
        // Закрываем модалку настроек если она открыта
        const settingsModal = $('#settingsModal');
        if (settingsModal && settingsModal.style.display === 'flex') {
            settingsModal.style.display = 'none';
        }
        
        const m = $('#teacherSelectionModal');
        if (m) {
            m.style.display = 'flex';
            loadTeachers();
        }
    };

    window.selectTeacher = function selectTeacher(teacherName) {
        updateTeacherInState(teacherName);
        document.getElementById('currentTeacher').textContent = teacherName;
        // Закрываем модалку и обновляем страницу
        const modal = document.getElementById('teacherSelectionModal');
        if (modal) modal.style.display = 'none';
        window.location.href = `?week=${state.week}&day=${state.day}&teacher=${encodeURIComponent(teacherName)}`;
    };

    async function loadTeachers() {
        try {
            const response = await fetch('api/get_teachers.php');
            const data = await response.json();
            
            const list = document.getElementById('teachersList');
            if (data.success && data.teachers) {
                list.innerHTML = data.teachers.map(teacher => `
                    <button onclick="selectTeacher(${teacher.id}, '${teacher.full_name}')" class="group-btn">
                        <div style="display:flex; align-items:center; gap:0.75rem;">
                            <div class="group-icon" style="background: rgba(168,85,247,0.2);"><span style="color:#d8b4fe;font-weight:700;">👨‍🏫</span></div>
                            <div>
                                <div style="color:#fff;font-weight:600;">${teacher.full_name}</div>
                                <div class="small">${teacher.department || 'Преподаватель'}</div>
                            </div>
                        </div>
                    </button>
                `).join('');
            } else {
                list.innerHTML = '<div style="text-align:center; color:var(--muted);">Преподаватели не найдены</div>';
            }
        } catch (error) {
            console.error('Ошибка загрузки преподавателей:', error);
            document.getElementById('teachersList').innerHTML = '<div style="text-align:center; color:var(--muted);">Ошибка загрузки</div>';
        }
    }

    // Дни недели
    function buildDays() {
        const row = document.getElementById('daysRow');
        if (!row) return;
        row.innerHTML = '';
        DAYS.forEach((d, idx) => {
            if (idx === 6) return; // 1-6 без воскресенья
            const b = document.createElement('button');
            b.type = 'button';
            b.dataset.day = String(idx + 1);
            b.className = 'day-btn';
            b.innerHTML = `<span class="font-medium">${d.key}</span>`;
            b.addEventListener('click', () => {
                // Мгновенное переключение без перезагрузки страницы
                switchSchedule(state.week, idx + 1);
            });
            row.appendChild(b);
        });
    }

    function setActiveSegments() {
        $$('.seg-week').forEach(el => {
            const active = Number(el.dataset.week) === state.week;
            el.classList.toggle('active', active);
        });
        $$('.day-btn').forEach(el => {
            const active = Number(el.dataset.day) === state.day;
            el.classList.toggle('active', active);
        });
    }

    function renderContext() {
        const weekLabel = `${state.week} неделя`;
        const dayLabel = DAYS[state.day - 1].full;
        const ctx = document.getElementById('contextLine');
        if (ctx) {
            if (state.viewMode === 'teacher') {
                ctx.textContent = `Преподаватель • ${weekLabel} • ${dayLabel}`;
            } else {
                ctx.textContent = `${weekLabel} • ${dayLabel}`;
            }
        }
    }

    function updateHeaderClock() {
        const el = document.getElementById('updatedAt');
        if (el) el.textContent = nowString();
    }

    async function updateCurrentLesson() {
        try {
            let url = '';
            if (state.viewMode === 'teacher' && state.teacher) {
                url = `api/get_teacher_current_lesson.php?teacher_name=${encodeURIComponent(state.teacher)}`;
            } else if (state.viewMode === 'group' && state.group) {
                url = `api/get_current_lesson.php?group=${encodeURIComponent(state.group)}`;
            } else {
                return;
            }
            
            const response = await fetch(url);
            const data = await response.json();
            const nowCard = document.getElementById('nowCard');
            const container = nowCard ? nowCard.parentElement : document.querySelector('[data-cards]');

            if (data.success && data.currentLesson) {
                const remainingTextInitial = computeRemainingLabel(data.currentLesson.end_time);
                const html = `
                    <div id="nowCard" class="card card__inner">
                        <div class="flex-row">
                            <div class="flex-row gap">
                                <span class="btn btn--emerald">Сейчас</span>
                            </div>
                            <span id="nowTimeRange" class="small">${data.currentLesson.start_time.substring(0,5)}–${data.currentLesson.end_time.substring(0,5)}</span>
                        </div>
                        <div class="mt-4">
                            <div id="nowTitle" class="h2 truncate">${data.currentLesson.subject_name}</div>
                            <div id="nowMeta" class="lesson-meta">${data.currentLesson.room_number} • ${state.viewMode === 'teacher' && data.currentLesson.groups && data.currentLesson.groups.length > 0 ? data.currentLesson.groups.join(', ') : data.currentLesson.teacher_name}</div>
                        </div>
                        <div class="mt-4">
                            <div class="progress"><div id="nowProgress" class="progress__bar" style="width:${data.progress}%;"></div></div>
                            <div class="progress__meta"><span id="nowProgressLabel"><i class=\"fas fa-clock\" style=\"margin-right: 4px;\"></i>до конца пары: ${remainingTextInitial}</span><span id="nowRemaining"></span></div>
                        </div>
                    </div>`;
                if (!nowCard && container) {
                    container.insertAdjacentHTML('afterbegin', html);
                    container.classList.add('grid', 'grid--two');
                } else if (nowCard) {
                    document.getElementById('nowTimeRange').textContent = `${data.currentLesson.start_time.substring(0,5)}–${data.currentLesson.end_time.substring(0,5)}`;
                    document.getElementById('nowTitle').textContent = data.currentLesson.subject_name;
                    const metaText = state.viewMode === 'teacher' && data.currentLesson.groups && data.currentLesson.groups.length > 0 
                        ? `${data.currentLesson.room_number} • ${data.currentLesson.groups.join(', ')}`
                        : `${data.currentLesson.room_number} • ${data.currentLesson.teacher_name}`;
                    document.getElementById('nowMeta').textContent = metaText;
                    document.getElementById('nowProgress').style.width = `${data.progress}%`;
                    const remainingTextUpdate = computeRemainingLabel(data.currentLesson.end_time);
                    document.getElementById('nowProgressLabel').innerHTML = `<i class=\"fas fa-clock\" style=\"margin-right: 4px;\"></i>до конца пары: ${remainingTextUpdate}`;
                }
                // Дополнительный спан справа теперь пустой
                const remEl = document.getElementById('nowRemaining');
                if (remEl) remEl.textContent = '';
            } else if (nowCard) {
                nowCard.remove();
                if (container) container.classList.remove('grid--two');
            }
        } catch (e) {
            console.error('Ошибка обновления текущей пары:', e);
        }
    }

    function updateScheduleData(lessons = null) {
        const list = document.getElementById('list');
        const empty = document.getElementById('emptyState');
        const lessonsData = lessons !== null ? lessons : (Array.isArray(BOOT.daySchedule) ? BOOT.daySchedule : []);
        const dayTitle = document.getElementById('dayTitle');
        if (dayTitle) dayTitle.textContent = DAYS[state.day - 1].full;

        if (!lessonsData.length) {
            if (list) list.innerHTML = '';
            if (empty) empty.classList.remove('hidden');
            return;
        }
        if (empty) empty.classList.add('hidden');
        if (!list) return;

        list.innerHTML = lessonsData.map(lesson => {
            const metaText = state.viewMode === 'teacher' && Array.isArray(lesson.groups) && lesson.groups.length 
                ? lesson.groups.join(', ')
                : (state.viewMode === 'teacher' && lesson.group_name && lesson.group_name.trim() 
                    ? lesson.group_name 
                    : lesson.teacher_name);
            
            return `
            <article class="card card--hover card__inner lesson-card" style="position: relative; background: linear-gradient(135deg, rgba(255,255,255,0.06) 0%, rgba(255,255,255,0.04) 100%); border: 1px solid rgba(255,255,255,0.12); border-radius: 12px; backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); box-shadow: 0 2px 12px rgba(0,0,0,0.1); transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); overflow: hidden; padding: 0.75rem !important;" onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 20px rgba(0,0,0,0.15)'; this.style.borderColor='rgba(255,255,255,0.2)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 12px rgba(0,0,0,0.1)'; this.style.borderColor='rgba(255,255,255,0.12)'">
                <div style="min-width:0; padding-left: 8px; position: relative; z-index: 1;">
                    <div class="small muted" style="color: rgba(255,255,255,0.75); font-weight: 500; letter-spacing: 0.3px; text-transform: uppercase; font-size: 0.7rem; margin-bottom: 0.3rem; display: flex; align-items: center; gap: 0.5rem;">
                        
                        <span style="display: inline-flex; align-items: center; gap: 0.25rem;">
                            <i class="fas fa-clock" style="font-size: 10px;"></i>
                            ${lesson.start_time.substring(0,5)}–${lesson.end_time.substring(0,5)}
                        </span>
                    </div>
                    <h3 class="h2" style="margin-top:0.3rem; margin-bottom: 0.5rem; line-height:1.2; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; color: #fff; text-shadow: 0 1px 2px rgba(0,0,0,0.25); font-weight: 600; font-size: 1rem;">
                        ${lesson.subject_name}
                    </h3>
                    <div class="lesson-meta" style="margin-top: 0.5rem; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                        <span style="background: rgba(255,255,255,0.15); padding: 0.2rem 0.5rem; border-radius: 12px; font-size: 0.7rem; font-weight: 500; color: #fff; border: 1px solid rgba(255,255,255,0.2); display: inline-flex; align-items: center; gap: 0.25rem;">
                            <i class="fas fa-door-open" style="font-size: 10px;"></i>
                            ${lesson.room_number}
                        </span>
                        <span style="background: rgba(255,255,255,0.1); padding: 0.2rem 0.5rem; border-radius: 12px; font-size: 0.7rem; font-weight: 500; color: rgba(255,255,255,0.9); border: 1px solid rgba(255,255,255,0.15); display: inline-flex; align-items: center; gap: 0.25rem;">
                            ${state.viewMode === 'teacher' && Array.isArray(lesson.groups) && lesson.groups.length 
                                ? `<i class="fas fa-users" style="font-size: 10px;"></i>${lesson.groups.join(', ')}`
                                : (state.viewMode === 'teacher' && lesson.group_name && lesson.group_name.trim() 
                                    ? `<i class="fas fa-users" style="font-size: 10px;"></i>${lesson.group_name}`
                                    : `<i class="fas fa-chalkboard-teacher" style="font-size: 10px;"></i>${lesson.teacher_name}`)}
                        </span>
                    </div>
                </div>
            </article>
            `;
        }).join('');
        
        // Применяем цветовые акценты к новым карточкам
        setTimeout(() => {
            if (typeof addColorAccents === 'function') {
                addColorAccents();
            }
        }, 100);
    }
    
    // Мгновенное переключение расписания из уже загруженных данных
    function switchSchedule(week, day) {
        // Обновляем состояние
        state.week = week;
        state.day = day;
        
        // Получаем расписание из уже загруженных данных
        const schedule = state.allSchedules[week] && state.allSchedules[week][day] 
            ? state.allSchedules[week][day] 
            : [];
        
        // Обновляем активные кнопки
        setActiveSegments();
        
        // Обновляем заголовок дня
        const dayTitle = document.getElementById('dayTitle');
        if (dayTitle) dayTitle.textContent = DAYS[day - 1].full;
        
        // Обновляем расписание
        updateScheduleData(schedule);
        
        // Обновляем состояние кнопки избранного (если функция доступна)
        if (typeof updateFavoriteButton === 'function') {
            updateFavoriteButton();
        }
    }

    function refreshSchedule() {
        const btn = document.getElementById('refreshBtn');
        if (!btn) return;
        const prev = btn.textContent;
        btn.textContent = 'Обновляю...';
        btn.disabled = true;
        // Просто перезагружаем страницу
        setTimeout(() => window.location.reload(), 300);
    }

    function initWeekControls() {
        $$('.seg-week').forEach(el => {
            el.addEventListener('click', () => {
                const newWeek = Number(el.dataset.week);
                // Мгновенное переключение без перезагрузки страницы
                switchSchedule(newWeek, state.day);
            });
        });
    }

    function scrollIntoViewIfNeeded(el, container) {
        const elLeft = el.offsetLeft, elRight = elLeft + el.offsetWidth;
        const cLeft = container.scrollLeft, cRight = cLeft + container.clientWidth;
        if (elLeft < cLeft) container.scrollTo({ left: elLeft - 16, behavior: 'smooth' });
        else if (elRight > cRight) container.scrollTo({ left: elRight - container.clientWidth + 16, behavior: 'smooth' });
    }

    function onReady() {
        // Проверяем, есть ли выбранная группа или преподаватель
        const hasGroup = !!(state.group && state.group.trim() !== '');
        const hasTeacher = (state.viewMode === 'teacher') && !!(state.teacher && String(state.teacher).trim() !== '');
        
        if (!hasGroup && !hasTeacher) { 
            showGroupSelectionModal(); 
        }

        // Контролы
        initWeekControls();
        buildDays();
        const todayBtn = document.querySelector(`.day-btn[data-day="${state.day}"]`);
        const row = document.getElementById('daysRow');
        if (todayBtn && row) setTimeout(() => scrollIntoViewIfNeeded(todayBtn, row), 50);

        // Кнопка обновления
        const refreshBtn = document.getElementById('refreshBtn');
        if (refreshBtn) refreshBtn.addEventListener('click', refreshSchedule);

        // Кнопка настроек
        const settingsBtn = document.getElementById('settingsBtn');
        if (settingsBtn) {
            settingsBtn.addEventListener('click', showSettingsModal);
            console.log('Settings button event listener added');
        } else {
            console.error('Settings button not found');
        }

        // Крестик закрытия модалки настроек
        const closeSettingsBtn = document.getElementById('closeSettingsBtn');
        if (closeSettingsBtn) {
            closeSettingsBtn.addEventListener('click', closeSettingsModal);
            console.log('Close settings button event listener added');
        } else {
            console.error('Close settings button not found');
        }

        // Закрытие модальных окон по клику вне их
        setupModalCloseHandlers();

        // Рендер
        setActiveSegments();
        renderContext();
        updateScheduleData();
        updateCurrentLesson();
        updateHeaderClock();

        // Тики
        setInterval(updateHeaderClock, 1000);
        setInterval(() => { if (new Date().getSeconds() === 0) updateCurrentLesson(); }, 1000);
        setInterval(() => { fetch('api/auto_update_schedule_time.php').catch(()=>{}); }, 300000);
    }

    function setupModalCloseHandlers() {
        // Закрытие модальных окон по клику вне их области
        const modals = ['groupSelectionModal', 'settingsModal', 'teacherSelectionModal'];
        modals.forEach(modalId => {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        this.style.display = 'none';
                    }
                });
            }
        });

        // Закрытие по Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                modals.forEach(modalId => {
                    const modal = document.getElementById(modalId);
                    if (modal && modal.style.display === 'flex') {
                        modal.style.display = 'none';
                    }
                });
            }
        });
    }

    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        onReady();
    } else {
        document.addEventListener('DOMContentLoaded', onReady);
    }
})();


