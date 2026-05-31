// js/telegram-webapp-direct.js
// JavaScript для прямой работы с Telegram Web App через API

class TelegramWebAppDirect {
    constructor() {
        this.tg = window.Telegram?.WebApp;
        this.isTelegram = !!this.tg;
        this.isInitialized = false;
        
        if (this.isTelegram) {
            this.init();
        }
    }
    
    // Cookie functions for group persistence
    setCookie(name, value, days) {
        const expires = new Date();
        expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
        document.cookie = name + '=' + value + ';expires=' + expires.toUTCString() + ';path=/';
    }

    getCookie(name) {
        const nameEQ = name + "=";
        const ca = document.cookie.split(';');
        for (let i = 0; i < ca.length; i++) {
            let c = ca[i];
            while (c.charAt(0) === ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
        }
        return null;
    }
    
    init() {
        try {
            // Настройка Web App
            this.tg.ready();
            this.tg.expand();
            
            // Проверяем автоматический вход
            this.checkAutoLogin();
            
            this.isInitialized = true;
            console.log('Telegram Web App Direct инициализирован');
        } catch (error) {
            console.error('Ошибка инициализации Telegram Web App Direct:', error);
        }
    }
    
    async checkAutoLogin() {
        if (!this.isTelegram || !this.tg.initDataUnsafe?.user) {
            return;
        }
        
        const user = this.tg.initDataUnsafe.user;
        const telegramId = user.id;
        
        try {
            const response = await fetch('api/auto_login_telegram.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    telegram_id: telegramId
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Автоматический вход успешен
                this.showSuccess('Вход выполнен. Пожалуйста подождите, это может занять некоторое время.');
                setTimeout(() => {
                    window.location.href = data.data.redirect_url;
                }, 1000);
            } else {
                // Нужна привязка аккаунта
                this.showLinkForm(user);
            }
        } catch (error) {
            console.error('Ошибка проверки автоматического входа:', error);
            this.showError('Ошибка подключения к серверу');
        }
    }
    
    showLinkForm(telegramUser) {
        // Показываем форму привязки аккаунта
        const linkForm = document.getElementById('telegram-link-form');
        const telegramInfo = document.getElementById('telegram-user-info');
        
        if (linkForm) {
            linkForm.style.display = 'block';
            
            // Заполняем информацию о Telegram пользователе
            if (telegramInfo && telegramUser) {
                const fullName = [telegramUser.first_name, telegramUser.last_name].filter(Boolean).join(' ');
                telegramInfo.innerHTML = `
                    <div class="telegram-user-card">
                        <div class="telegram-avatar">
                            <span>${telegramUser.first_name?.[0] || '?'}</span>
                        </div>
                        <div class="telegram-user-details">
                            <div class="telegram-name">${fullName || 'Пользователь Telegram'}</div>
                            <div class="telegram-username">@${telegramUser.username || 'username'}</div>
                            <div class="telegram-id">ID: ${telegramUser.id}</div>
                        </div>
                    </div>
                `;
            }
        }
    }
    
    async linkAccount(username, password) {
        if (!this.isTelegram || !this.tg.initDataUnsafe?.user) {
            this.showError('Ошибка: нет данных Telegram пользователя');
            return;
        }
        
        const linkButton = document.getElementById('link-account-btn');
        const originalText = linkButton.innerHTML;
        
        // Показываем индикатор загрузки
        linkButton.innerHTML = '<span class="loading-spinner"></span> Привязка...';
        linkButton.disabled = true;
        
        const user = this.tg.initDataUnsafe.user;
        
        try {
            const response = await fetch('api/save_telegram_id.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    telegram_id: user.id,
                    telegram_username: user.username || '',
                    username: username,
                    password: password
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess('Аккаунт успешно привязан к Telegram!');
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                this.showError(data.message || 'Ошибка привязки аккаунта');
            }
        } catch (error) {
            console.error('Ошибка привязки аккаунта:', error);
            this.showError('Ошибка подключения к серверу');
        } finally {
            // Восстанавливаем кнопку
            linkButton.innerHTML = originalText;
            linkButton.disabled = false;
        }
    }
    
    getUserData() {
        if (!this.isTelegram) return null;
        
        return this.tg.initDataUnsafe?.user || null;
    }
    
    showSuccess(message) {
        this.showNotification(message, 'success');
    }
    
    showError(message) {
        this.showNotification(message, 'error');
    }
    
    showNotification(message, type = 'info') {
        // Создаем уведомление
        const notification = document.createElement('div');
        notification.className = `telegram-notification telegram-notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <span class="notification-icon">${type === 'success' ? '✅' : type === 'error' ? '❌' : 'ℹ️'}</span>
                <span class="notification-message">${message}</span>
            </div>
        `;
        
        // Добавляем на страницу
        document.body.appendChild(notification);
        
        // Показываем с анимацией
        setTimeout(() => {
            notification.classList.add('show');
        }, 100);
        
        // Убираем через 3 секунды
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, 3000);
    }
    
    // Метод для проверки, находимся ли мы в Telegram Web App
    static isTelegramWebApp() {
        return !!(window.Telegram?.WebApp);
    }
}

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', () => {
    window.telegramWebAppDirect = new TelegramWebAppDirect();
    
    // Обработчик формы привязки аккаунта
    const linkForm = document.getElementById('link-form');
    if (linkForm) {
        linkForm.addEventListener('submit', (e) => {
            e.preventDefault();
            
            const username = document.getElementById('link-username').value;
            const password = document.getElementById('link-password').value;
            
            if (username && password && window.telegramWebAppDirect) {
                window.telegramWebAppDirect.linkAccount(username, password);
            }
        });
    }
});

// Экспорт для использования в других модулях
if (typeof module !== 'undefined' && module.exports) {
    module.exports = TelegramWebAppDirect;
}
