/* Локальная версия Telegram Web App API */

// Базовый объект Telegram WebApp
window.Telegram = window.Telegram || {};

// WebApp объект с основными методами
window.Telegram.WebApp = window.Telegram.WebApp || {
    // Основные свойства
    initData: '',
    initDataUnsafe: {},
    version: '6.0',
    platform: 'web',
    colorScheme: 'light',
    themeParams: {},
    isExpanded: true,
    viewportHeight: window.innerHeight,
    viewportStableHeight: window.innerHeight,
    headerColor: '#ffffff',
    backgroundColor: '#ffffff',
    isClosingConfirmationEnabled: false,
    isVerticalSwipesEnabled: true,
    
    // Основные методы
    ready: function() {
        console.log('Telegram WebApp ready');
    },
    
    expand: function() {
        console.log('Telegram WebApp expand');
    },
    
    close: function() {
        console.log('Telegram WebApp close');
        // В обычном браузере просто закрываем вкладку
        if (window.close) {
            window.close();
        }
    },
    
    sendData: function(data) {
        console.log('Telegram WebApp sendData:', data);
    },
    
    openLink: function(url, options) {
        console.log('Telegram WebApp openLink:', url, options);
        window.open(url, '_blank');
    },
    
    openTelegramLink: function(url) {
        console.log('Telegram WebApp openTelegramLink:', url);
        window.open(url, '_blank');
    },
    
    openInvoice: function(url, callback) {
        console.log('Telegram WebApp openInvoice:', url);
        if (callback) callback('success');
    },
    
    showPopup: function(params, callback) {
        console.log('Telegram WebApp showPopup:', params);
        if (callback) callback('ok');
    },
    
    showAlert: function(message, callback) {
        console.log('Telegram WebApp showAlert:', message);
        alert(message);
        if (callback) callback();
    },
    
    showConfirm: function(message, callback) {
        console.log('Telegram WebApp showConfirm:', message);
        const result = confirm(message);
        if (callback) callback(result);
    },
    
    showScanQrPopup: function(params, callback) {
        console.log('Telegram WebApp showScanQrPopup:', params);
        if (callback) callback('cancelled');
    },
    
    closeScanQrPopup: function() {
        console.log('Telegram WebApp closeScanQrPopup');
    },
    
    readTextFromClipboard: function(callback) {
        console.log('Telegram WebApp readTextFromClipboard');
        if (navigator.clipboard && navigator.clipboard.readText) {
            navigator.clipboard.readText().then(callback).catch(() => callback(''));
        } else {
            callback('');
        }
    },
    
    requestWriteAccess: function(callback) {
        console.log('Telegram WebApp requestWriteAccess');
        if (callback) callback(true);
    },
    
    requestContact: function(callback) {
        console.log('Telegram WebApp requestContact');
        if (callback) callback(false);
    },
    
    // События
    onEvent: function(eventType, eventHandler) {
        console.log('Telegram WebApp onEvent:', eventType);
    },
    
    offEvent: function(eventType, eventHandler) {
        console.log('Telegram WebApp offEvent:', eventType);
    },
    
    // Дополнительные методы
    enableClosingConfirmation: function() {
        this.isClosingConfirmationEnabled = true;
    },
    
    disableClosingConfirmation: function() {
        this.isClosingConfirmationEnabled = false;
    },
    
    enableVerticalSwipes: function() {
        this.isVerticalSwipesEnabled = true;
    },
    
    disableVerticalSwipes: function() {
        this.isVerticalSwipesEnabled = false;
    },
    
    setHeaderColor: function(color) {
        this.headerColor = color;
    },
    
    setBackgroundColor: function(color) {
        this.backgroundColor = color;
    },
    
    // Инициализация
    init: function() {
        console.log('Telegram WebApp init');
        this.ready();
    }
};

// Автоматическая инициализация
document.addEventListener('DOMContentLoaded', function() {
    if (window.Telegram && window.Telegram.WebApp) {
        window.Telegram.WebApp.init();
    }
});

// Экспорт для использования в других скриптах
if (typeof module !== 'undefined' && module.exports) {
    module.exports = window.Telegram;
}
