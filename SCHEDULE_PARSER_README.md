# Парсер расписания PDF

Этот набор скриптов предназначен для парсинга PDF файлов расписания высшего образования (курсы 1-4) и генерации SQL INSERT statements для заполнения таблицы `schedule_all`.

## ✅ Статус

Парсер работает и успешно извлекает данные из PDF файлов расписания. При последнем запуске было извлечено **1964 записи** из 4 PDF файлов.

**Основной способ:** Используйте `parse_schedule_pdf_advanced.php` для автоматического парсинга.

**Альтернативный способ:** Если автоматический парсинг не работает для ваших файлов, используйте конвертацию через CSV (см. раздел "Вариант 3" ниже).

## Структура файлов

- `parse_schedule_pdf.php` - Базовый PHP парсер
- `parse_schedule_pdf_advanced.php` - Улучшенный PHP парсер (рекомендуется)
- `parse_schedule_pdf.py` - Python парсер (более точное извлечение таблиц)
- `extract_teacher_schedule.sql` - SQL скрипт для извлечения расписания преподавателей
- `schedule_insert.sql` - Генерируемый SQL файл с INSERT statements

## Требования

### Для PHP скриптов:

1. **Установить библиотеку smalot/pdfparser:**
   ```bash
   composer require smalot/pdfparser
   ```

2. **Или установить poppler-utils (для pdftotext):**
   - macOS: `brew install poppler`
   - Ubuntu/Debian: `sudo apt-get install poppler-utils`
   - Windows: Скачать с https://github.com/oschwartz10612/poppler-windows/releases

### Для Python скрипта:

```bash
pip3 install pdfplumber pandas
```

Или:

```bash
pip3 install tabula-py pandas
```

## Использование

### Вариант 1: PHP скрипт (рекомендуется для начала)

```bash
php parse_schedule_pdf_advanced.php
```

Скрипт обработает все PDF файлы в папке `schedule/` и создаст файл `schedule_insert.sql`.

### Вариант 2: Python скрипт (более точное извлечение таблиц)

```bash
# Сначала установите зависимости
pip3 install pdfplumber pandas

# Затем запустите скрипт
python3 parse_schedule_pdf.py
```

### Вариант 3: Конвертация через CSV (РЕКОМЕНДУЕТСЯ)

Если автоматический парсинг не работает:

1. **Конвертируйте PDF в Excel/CSV:**
   - Используйте онлайн-конвертер: https://www.ilovepdf.com/pdf-to-excel
   - Или любой другой конвертер PDF → Excel/CSV

2. **Подготовьте CSV файл** со следующими колонками:
   ```csv
   group_name,week_number,day_of_week,lesson_number,subject_name,room_number,teacher_name,start_time,end_time
   25-ОЗИБ-01,1,4,4,л.Основы брендинга,1-301,Плотников А.В.,13:10:00,14:40:00
   25-ОЗИБ-01,2,4,4,лаб.Компьютерное моделирование в дизайне,2-410,Андреев Н.В.,13:10:00,14:40:00
   ```

3. **Генерируйте SQL:**
   ```bash
   php csv_to_sql.php schedule.csv schedule_insert.sql
   ```

**Важно:**
- Если в одной ячейке 1 пара → создайте 2 строки (для недели 1 и недели 2)
- Если в одной ячейке 2 разные пары → первая для недели 1, вторая для недели 2
- День недели: 1=Понедельник, 2=Вторник, 3=Среда, 4=Четверг, 5=Пятница, 6=Суббота, 7=Воскресенье

## Логика обработки недель

Парсер автоматически определяет недели для пар:

- **Если в одной ячейке 1 пара** → она добавляется для обеих недель (неделя 1 и неделя 2)
- **Если в одной ячейке 2 разные пары** → первая пара для недели 1, вторая для недели 2

Пример из скриншота:
- В четверг в 13:10-14:40:
  - Неделя 1: л.Основы брендинга (Плотников А.В., 1-301)
  - Неделя 2: лаб.Компьютерное моделирование в дизайне (Андреев Н.В., 2-410)

## Структура базы данных

Таблица `schedule_all` имеет следующую структуру:

```sql
CREATE TABLE `schedule_all` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `group_name` varchar(100) NOT NULL COMMENT 'Название группы',
    `week_number` tinyint(1) NOT NULL COMMENT '1 или 2 неделя',
    `day_of_week` tinyint(1) NOT NULL COMMENT '1-7 (Пн-Вс)',
    `lesson_number` tinyint(1) NOT NULL COMMENT '1-7 пара',
    `subject_name` varchar(255) NOT NULL COMMENT 'Название предмета',
    `room_number` varchar(50) NOT NULL COMMENT 'Номер аудитории',
    `teacher_name` varchar(100) NOT NULL COMMENT 'ФИО преподавателя',
    `start_time` time NOT NULL COMMENT 'Время начала пары',
    `end_time` time NOT NULL COMMENT 'Время окончания пары',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_group_week_day` (`group_name`, `week_number`, `day_of_week`),
    KEY `idx_lesson` (`group_name`, `week_number`, `day_of_week`, `lesson_number`),
    UNIQUE KEY `unique_lesson` (`group_name`, `week_number`, `day_of_week`, `lesson_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Время пар

Стандартное время пар:

1. 08:00 - 09:30
2. 09:40 - 11:10
3. 11:30 - 13:00
4. 13:10 - 14:40
5. 14:50 - 16:20
6. 16:30 - 18:00
7. 18:10 - 19:40

## Извлечение расписания преподавателей

После заполнения таблицы `schedule_all`, выполните SQL скрипт для извлечения расписания преподавателей:

```bash
mysql -u username -p database_name < extract_teacher_schedule.sql
```

Или выполните вручную в MySQL клиенте:

```sql
INSERT INTO teacher_schedule (
    teacher_id,
    week_number,
    day_of_week,
    lesson_number,
    subject_name,
    room_number,
    group_name,
    start_time,
    end_time,
    created_at,
    updated_at
)
SELECT 
    t.id AS teacher_id,
    s.week_number,
    s.day_of_week,
    s.lesson_number,
    s.subject_name,
    s.room_number,
    s.group_name,
    s.start_time AS start_time,
    s.end_time,
    NOW() AS created_at,
    NOW() AS updated_at
FROM schedule_all s
INNER JOIN teachers t ON s.teacher_name COLLATE utf8mb4_unicode_ci = t.full_name COLLATE utf8mb4_unicode_ci;
```

## Проверка данных

После выполнения SQL скрипта проверьте:

1. **Нет дубликатов:**
   ```sql
   SELECT group_name, week_number, day_of_week, lesson_number, COUNT(*) 
   FROM schedule_all 
   GROUP BY group_name, week_number, day_of_week, lesson_number 
   HAVING COUNT(*) > 1;
   ```

2. **Все недели заполнены:**
   ```sql
   SELECT group_name, week_number, COUNT(*) as lessons_count
   FROM schedule_all
   GROUP BY group_name, week_number
   ORDER BY group_name, week_number;
   ```

3. **Проверка расписания преподавателей:**
   ```sql
   SELECT COUNT(*) as total_records,
          COUNT(DISTINCT teacher_id) as unique_teachers,
          COUNT(DISTINCT group_name) as unique_groups
   FROM teacher_schedule;
   ```

## Фильтрация групп

Парсер автоматически фильтрует группы высшего образования (без "СПО" в названии). Группы с "СПО" в названии игнорируются.

## Устранение неполадок

### Проблема: Не извлекаются данные из PDF

1. Убедитесь, что PDF файлы находятся в папке `schedule/`
2. Проверьте, что установлена необходимая библиотека (smalot/pdfparser или poppler-utils)
3. Попробуйте использовать Python скрипт для более точного извлечения таблиц

### Проблема: Неправильно определяются недели

Проверьте структуру PDF файла. Если в одной ячейке 2 строки, они должны быть разными парами для разных недель.

### Проблема: Дубликаты в базе данных

Парсер автоматически удаляет дубликаты по уникальному ключу `(group_name, week_number, day_of_week, lesson_number)`. Если дубликаты все же появляются, проверьте данные вручную.

## Примечания

- Парсинг PDF таблиц - сложная задача. Результаты могут варьироваться в зависимости от структуры PDF файла.
- Рекомендуется проверить сгенерированный SQL файл перед выполнением в базе данных.
- При необходимости можно вручную отредактировать SQL файл для исправления ошибок парсинга.

