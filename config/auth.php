<?php
// Конфигурация авторизации с поддержкой пользователей
// session_start() должен вызываться в файлах, которые подключают этот файл

// Подключение к базе данных
require_once 'database.php';

// Проверка авторизации
function isAuthenticated() {
    return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
}

// Проверка роли пользователя
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Функция входа
function login($username, $password) {
    global $pdo;

    try {
        $sql = "SELECT * FROM users WHERE username = :username AND status = 'active'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['username' => $username]);

        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['authenticated'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['client_name'] = $user['client_name'];
            $_SESSION['telegram_username'] = $user['telegram_username'];
            $_SESSION['group'] = $user['group'] ?? null;

            return true;
        }

        return false;
    } catch (PDOException $e) {
        error_log("Ошибка авторизации: " . $e->getMessage());
        return false;
    }
}

// Функция выхода
function logout() {
    session_destroy();
    header('Location: id');
    exit();
}

// Проверка авторизации для защищенных страниц
function requireAuth() {
    if (!isAuthenticated()) {
        header('Location: id');
        exit();
    }
}

// Проверка прав администратора
function requireAdmin() {
    requireAuth();
    if (!isAdmin()) {
        header('Location: id');
        exit();
    }
}

// Получение информации о текущем пользователе
function getCurrentUser() {
    if (!isAuthenticated()) {
        return null;
    }

    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'role' => $_SESSION['role'],
        'client_name' => $_SESSION['client_name'],
        'telegram_username' => $_SESSION['telegram_username'],
        'group' => $_SESSION['group'] ?? null
    ];
}

// Хеширование пароля
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Проверка пароля
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}