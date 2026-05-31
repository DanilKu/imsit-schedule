// js/telegram-webapp.js
// JavaScript для работы с Telegram Web App

class TelegramWebApp {
    constructor() {
        this.tg = window.Telegram?.WebApp;
        this.isTelegram = !!this.tg;
        this.isInitialized = false;
        
        if (this.isTelegram) {
            this.init();
        }
    }
    
    init() {
        try {
            // Настройка Web App
            this.tg.ready();
            this.tg.expand();
            
            // Отправляем данные на сервер для проверки
            this.sendInitData();
            
            this.isInitialized = true;
            console.log('Telegram Web App инициализирован');
        } catch (error) {
            console.error('Ошибка инициализации Telegram Web App:', error);
        }
    }
    
    sendInitData() {
        if (!this.isTelegram) {
            return;
        }
        
        // Подготавливаем данные для отправки
        const dataToSend = {};
        
        // Отправляем initData если есть
        if (this.tg.initData) {
            dataToSend.initData = this.tg.initData;
        }
        
        // Отправляем initDataUnsafe если есть
        if (this.tg.initDataUnsafe) {
            dataToSend.initDataUnsafe = this.tg.initDataUnsafe;
        }
        
        // Отправляем данные пользователя напрямую
        if (this.tg.initDataUnsafe && this.tg.initDataUnsafe.user) {
            dataToSend.user = this.tg.initDataUnsafe.user;
        }
        
        // Если нет данных для отправки, не отправляем запрос
        if (Object.keys(dataToSend).length === 0) {
            console.log('Нет данных для отправки на сервер');
            return;
        }
        
        // Отправляем данные на сервер для проверки
        fetch('api/check_telegram_auth.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(dataToSend)
        })
        .then(response => response.json())
        .then(data => {
            this.handleAuthResponse(data);
        })
        .catch(error => {
            console.error('Ошибка проверки Telegram авторизации:', error);
            this.showError('Ошибка подключения к серверу');
        });
    }
    
    handleAuthResponse(data) {
        if (data.success) {
            if (data.autoLogin) {
                // Автоматический вход успешен
                this.showSuccess('Вход выполнен. Пожалуйста подождите, это может занять некоторое время.');
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else if (data.needLink) {
                // Нужна привязка аккаунта
                this.showLinkForm(data.telegramUser);
            }
        } else {
            this.showError(data.message || 'Ошибка авторизации');
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
                        </div>
                    </div>
                `;
            }
        }
    }
    
    linkAccount(username, password) {
        const linkButton = document.getElementById('link-account-btn');
        const originalText = linkButton.innerHTML;
        
        // Показываем индикатор загрузки
        linkButton.innerHTML = '<span class="loading-spinner"></span> Привязка...';
        linkButton.disabled = true;
        
        return fetch('api/link_telegram_account.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                username: username,
                password: password
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showSuccess('Аккаунт успешно привязан!');
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                this.showError(data.message || 'Ошибка привязки аккаунта');
            }
        })
        .catch(error => {
            console.error('Ошибка привязки аккаунта:', error);
            this.showError('Ошибка подключения к серверу');
        })
        .finally(() => {
            // Восстанавливаем кнопку
            linkButton.innerHTML = originalText;
            linkButton.disabled = false;
        });
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
    window.telegramWebApp = new TelegramWebApp();
    
    // Обработчик формы привязки аккаунта
    const linkForm = document.getElementById('link-form');
    if (linkForm) {
        linkForm.addEventListener('submit', (e) => {
            e.preventDefault();
            
            const username = document.getElementById('link-username').value;
            const password = document.getElementById('link-password').value;
            
            if (username && password && window.telegramWebApp) {
                window.telegramWebApp.linkAccount(username, password);
            }
        });
    }
});

// Экспорт для использования в других модулях
if (typeof module !== 'undefined' && module.exports) {
    module.exports = TelegramWebApp;
}
