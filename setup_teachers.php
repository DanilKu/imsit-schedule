<?php
require_once 'config/database.php';

try {
    // Читаем SQL файл
    $sql = file_get_contents('create_teachers_table.sql');
    
    // Разбиваем на отдельные запросы
    $queries = array_filter(array_map('trim', explode(';', $sql)));
    
    $pdo->beginTransaction();
    
    foreach ($queries as $query) {
        if (!empty($query)) {
            $pdo->exec($query);
        }
    }
    
    $pdo->commit();
    
    echo "✅ Таблица преподавателей создана успешно!<br>";
    echo "✅ Базовые преподаватели добавлены!<br>";
    
    // Проверяем количество преподавателей
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM teachers");
    $count = $stmt->fetch()['count'];
    echo "📊 Всего преподавателей в базе: {$count}<br>";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "❌ Ошибка: " . $e->getMessage();
}
?>
