<?php
require_once 'config/database.php';

try {
    // Читаем SQL файл
    $sql = file_get_contents('create_teacher_schedule_table.sql');
    
    // Разбиваем на отдельные запросы
    $queries = array_filter(array_map('trim', explode(';', $sql)));
    
    $pdo->beginTransaction();
    
    foreach ($queries as $query) {
        if (!empty($query)) {
            $pdo->exec($query);
        }
    }
    
    $pdo->commit();
    
    echo "✅ Таблица расписания преподавателей создана успешно!<br>";
    
    // Проверяем структуру таблицы
    $stmt = $pdo->query("DESCRIBE teacher_schedule");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📊 Структура таблицы teacher_schedule:<br>";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Поле</th><th>Тип</th><th>Null</th><th>Ключ</th><th>По умолчанию</th><th>Дополнительно</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>{$column['Field']}</td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Key']}</td>";
        echo "<td>{$column['Default']}</td>";
        echo "<td>{$column['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "❌ Ошибка: " . $e->getMessage();
}
?>
