<?php
// Настройки кодировки для корректной работы с русскими символами

// Установка кодировки для PHP
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
mb_http_input('P'); // POST данные
mb_http_input('G'); // GET данные
mb_language('uni');
mb_regex_encoding('UTF-8');

// Установка заголовков для правильной кодировки
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

// Функция для безопасного вывода русских символов
function safe_echo($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Функция для очистки и валидации русских символов
function clean_russian_text($text) {
    $text = trim($text);
    $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    return $text;
} 