<?php
/**
 * Пример конфигурации базы данных.
 * Скопируйте в database.php и укажите свои параметры:
 *   cp config/database.example.php config/database.php
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'imsit_schedule');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $pdo->exec('SET NAMES utf8mb4');
    $pdo->exec('SET CHARACTER SET utf8mb4');
    $pdo->exec('SET character_set_connection=utf8mb4');
    $pdo->exec('SET collation_connection=utf8mb4_unicode_ci');
} catch (PDOException $e) {
    die('Ошибка подключения к базе данных: ' . $e->getMessage());
}
