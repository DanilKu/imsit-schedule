// Функция переключения темы
function toggleTheme() {
    const html = document.documentElement;
    const themeIcon = document.getElementById('theme-icon');
    const currentTheme = html.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    
    html.setAttribute('data-theme', newTheme);
    
    // Обновление иконки
    if (newTheme === 'dark') {
        themeIcon.className = 'fas fa-sun';
    } else {
        themeIcon.className = 'fas fa-moon';
    }
    
    // Сохранение в cookie
    document.cookie = `theme=${newTheme}; path=/; max-age=31536000`;
}

// Функция фильтрации
function applyFilters() {
    const search = document.getElementById('search').value;
    const workType = document.getElementById('work-type-filter').value;
    const payment = document.getElementById('payment-filter').value;
    const priority = document.getElementById('priority-filter').value;
    const semester = document.getElementById('semester-filter').value;

    const params = new URLSearchParams();

    if (search) params.append('search', search);
    if (workType) params.append('work_type', workType);
    if (payment) params.append('is_paid', payment);
    if (priority) params.append('priority', priority);
    if (semester) params.append('semester', semester);

    const base = `${window.location.origin}/admin`;
    const target = params.toString() ? `${base}?${params.toString()}` : base;
    window.location.assign(target);
}

// Функция переключения семестров
function toggleSemester() {
    const currentSemester = new URLSearchParams(window.location.search).get('semester');
    let newSemester;
    
    if (!currentSemester) {
        newSemester = '6';
    } else if (currentSemester === '6') {
        newSemester = '7';
    } else if (currentSemester === '7') {
        newSemester = '8';
    } else {
        newSemester = null; // Все время
    }
    
    const params = new URLSearchParams(window.location.search);
    if (newSemester) {
        params.set('semester', newSemester);
    } else {
        params.delete('semester');
    }
    
    const base = `${window.location.origin}/admin`;
    const target = params.toString() ? `${base}?${params.toString()}` : base;
    window.location.assign(target);
}

// Функция удаления заказа
function deleteOrder(orderId) {
    if (confirm('Вы уверены, что хотите удалить этот заказ? Он будет перемещен в корзину.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/admin';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_order';
        
        const orderIdInput = document.createElement('input');
        orderIdInput.type = 'hidden';
        orderIdInput.name = 'order_id';
        orderIdInput.value = orderId;
        
        form.appendChild(actionInput);
        form.appendChild(orderIdInput);
        document.body.appendChild(form);
        form.submit();
    }
}

// Функция переключения приоритета
function togglePriority(orderId) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/admin';
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'toggle_priority';
    
    const orderIdInput = document.createElement('input');
    orderIdInput.type = 'hidden';
    orderIdInput.name = 'order_id';
    orderIdInput.value = orderId;
    
    form.appendChild(actionInput);
    form.appendChild(orderIdInput);
    document.body.appendChild(form);
    form.submit();
}



// Функция показа уведомлений
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            <span>${message}</span>
        </div>
        <button class="notification-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    document.body.appendChild(notification);
    
    // Автоматическое удаление через 5 секунд
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

// Функция форматирования чисел
function formatNumber(num) {
    return new Intl.NumberFormat('ru-RU').format(num);
}

// Функция форматирования даты
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('ru-RU', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

// Функция экспорта в Excel
function exportToExcel() {
    const table = document.querySelector('.orders-table');
    const rows = Array.from(table.querySelectorAll('tr'));
    
    let csv = [];
    
    // Заголовки
    const headers = Array.from(rows[0].querySelectorAll('th')).map(th => th.textContent.trim());
    csv.push(headers.join(','));
    
    // Данные
    for (let i = 1; i < rows.length; i++) {
        const cells = Array.from(rows[i].querySelectorAll('td'));
        const row = cells.map(cell => {
            let text = cell.textContent.trim();
            // Экранирование запятых
            if (text.includes(',')) {
                text = `"${text}"`;
            }
            return text;
        });
        csv.push(row.join(','));
    }
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    
    if (link.download !== undefined) {
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', `заказы_${new Date().toISOString().split('T')[0]}.csv`);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}

// Функция поиска с задержкой
let searchTimeout;
function debouncedSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 500);
}

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    // Обработчики событий для фильтров
    const searchInput = document.getElementById('search');
    const workTypeFilter = document.getElementById('work-type-filter');
    const paymentFilter = document.getElementById('payment-filter');
    const priorityFilter = document.getElementById('priority-filter');
    const semesterFilter = document.getElementById('semester-filter');
    
    if (searchInput) {
        searchInput.addEventListener('input', debouncedSearch);
        // Предотвращаем отправку формы по Enter, чтобы не попасть на index из-за браузерного поведения
        searchInput.addEventListener('keydown', function(e){
            if (e.key === 'Enter') {
                e.preventDefault();
                applyFilters();
            }
        });
    }
    
    if (workTypeFilter) {
        workTypeFilter.addEventListener('change', applyFilters);
    }
    
    if (paymentFilter) {
        paymentFilter.addEventListener('change', applyFilters);
    }
    
    if (priorityFilter) {
        priorityFilter.addEventListener('change', applyFilters);
    }
    
    if (semesterFilter) {
        semesterFilter.addEventListener('change', applyFilters);
    }
    
    // Анимация появления элементов
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);
    
    // Наблюдение за карточками статистики
    document.querySelectorAll('.stat-card').forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'all 0.5s ease';
        observer.observe(card);
    });
    
    // Наблюдение за строками таблицы
    document.querySelectorAll('.orders-table tbody tr').forEach((row, index) => {
        row.style.opacity = '0';
        row.style.transform = 'translateX(-20px)';
        row.style.transition = 'all 0.3s ease';
        row.style.transitionDelay = `${index * 0.05}s`;
        observer.observe(row);
    });
    
    // Обработка клавиш
    document.addEventListener('keydown', function(e) {
        // Ctrl + K для поиска
        if (e.ctrlKey && e.key === 'k') {
            e.preventDefault();
            if (searchInput) {
                searchInput.focus();
            }
        }
        
        // Ctrl + N для нового заказа
        if (e.ctrlKey && e.key === 'n') {
            e.preventDefault();
            window.location.href = 'add_order.php';
        }
        
        // Escape для очистки поиска
        if (e.key === 'Escape') {
            if (searchInput && searchInput.value) {
                searchInput.value = '';
                applyFilters();
            }
        }
    });
    
    // Подсказки для горячих клавиш
    if (searchInput) {
        searchInput.placeholder = 'Поиск по имени... (Ctrl+K)';
    }
});

// Функция для работы с модальными окнами
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        modal.style.opacity = '0';
        setTimeout(() => {
            modal.style.opacity = '1';
        }, 10);
        
        // Закрытие по клику вне модального окна
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeModal(modalId);
            }
        });
        
        // Закрытие по Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal(modalId);
            }
        });
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.opacity = '0';
        setTimeout(() => {
            modal.style.display = 'none';
        }, 300);
    }
}

// Функция для подтверждения действий
function confirmAction(message, callback) {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-content">
            <h3>Подтверждение</h3>
            <p>${message}</p>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="this.closest('.modal').remove()">Отмена</button>
                <button class="btn btn-danger" onclick="this.closest('.modal').remove(); ${callback}()">Подтвердить</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    modal.style.display = 'flex';
    modal.style.opacity = '0';
    setTimeout(() => {
        modal.style.opacity = '1';
    }, 10);
}

// Добавление стилей для уведомлений и модальных окон
const additionalStyles = `
    .notification {
        position: fixed;
        top: 20px;
        right: 20px;
        background: var(--bg-primary);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        padding: 15px 20px;
        box-shadow: var(--shadow);
        z-index: 10000;
        display: flex;
        align-items: center;
        gap: 10px;
        max-width: 400px;
        animation: slideIn 0.3s ease;
    }
    
    .notification-success {
        border-left: 4px solid var(--success-color);
    }
    
    .notification-error {
        border-left: 4px solid var(--danger-color);
    }
    
    .notification-info {
        border-left: 4px solid var(--info-color);
    }
    
    .notification-content {
        display: flex;
        align-items: center;
        gap: 10px;
        flex: 1;
    }
    
    .notification-close {
        background: none;
        border: none;
        color: var(--text-muted);
        cursor: pointer;
        padding: 5px;
        border-radius: 4px;
        transition: all 0.3s ease;
    }
    
    .notification-close:hover {
        background: var(--bg-secondary);
        color: var(--text-primary);
    }
    
    .modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
        transition: opacity 0.3s ease;
    }
    
    .modal-content {
        background: var(--bg-primary);
        border-radius: 12px;
        padding: 30px;
        max-width: 500px;
        width: 90%;
        box-shadow: var(--shadow-hover);
    }
    
    .modal-content h3 {
        margin-bottom: 15px;
        color: var(--text-primary);
    }
    
    .modal-content p {
        margin-bottom: 20px;
        color: var(--text-secondary);
    }
    
    .modal-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
    }
    
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
`;

// Добавление стилей в head
const styleSheet = document.createElement('style');
styleSheet.textContent = additionalStyles;
document.head.appendChild(styleSheet); 