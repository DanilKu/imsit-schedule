<?php
// Упрощённая система уведомлений без логирования
// Все функции логирования отключены

// Заглушки для совместимости
function sendNotification($subject, $message, $type = 'all') {
    // Ничего не делаем
    return true;
}

function logAction($action, $details = '') {
    // Ничего не делаем
    return true;
}

function sendTelegramNotification($message) {
    // Заглушка - ничего не делаем
    return false;
}

// Заглушки для функций, которые могут вызываться
function writeToLog($message) {
    return true;
}

function getRecentLogs($lines = 50) {
    return [];
}

function cleanupOldLogs($days = 30) {
    return true;
}