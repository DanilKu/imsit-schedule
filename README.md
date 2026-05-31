# Информационная система электронного расписания ИМСИТ

Web-приложение для централизованного хранения, отображения и администрирования расписания занятий НАН ЧОУ ВО «Академия маркетинга и социально-информационных технологий – ИМСИТ».

Дипломный проект: **«Разработка информационной системы электронного расписания для учебного заведения»**.

## Возможности

- Просмотр расписания **по учебной группе** и **по преподавателю**
- Поддержка **двухнедельного цикла** (неделя 1 / неделя 2)
- Определение и отображение **текущей и следующей пары**
- Переключение дня недели и номера недели
- **Избранное** (группы и преподаватели, cookie)
- **Ссылка для совместного доступа** к расписанию
- **Администрирование** занятий и настроек (`schedule_settings`)
- **REST API** для мобильного приложения imsitID (JSON)
- **Интеграция с ботом мессенджера**
- Адаптивный интерфейс (Bootstrap, мобильные устройства)
- Экспорт расписания в **CSV**

## Стек технологий

| Компонент | Технология |
|-----------|------------|
| Backend | PHP 8.1+ |
| База данных | MySQL 8.0+ |
| Web-сервер | Apache |
| Frontend | HTML, CSS, JavaScript, Bootstrap |
| Локальная среда | XAMPP |

## Структура проекта

```
├── id.php                          # Главная страница просмотра расписания
├── schedule_management.php         # Админ-панель управления расписанием
├── teacher_schedule_management.php # Управление расписанием преподавателей
├── favorites.php                   # Страница избранного
├── login.php                       # Авторизация
├── includes/
│   └── ScheduleManager.php         # Бизнес-логика расписания
├── config/
│   ├── auth.php                    # Авторизация и роли
│   └── database.example.php        # Пример конфигурации БД
├── api/
│   ├── get_group_schedule.php      # API: расписание группы
│   ├── get_teacher_schedule.php    # API: расписание преподавателя
│   ├── get_current_lesson.php      # API: текущая пара
│   ├── get_available_groups.php    # API: список групп
│   ├── get_available_teachers.php  # API: список преподавателей
│   ├── generate_share_link.php     # API: ссылка для доступа
│   └── bot_schedule_webhook.php    # Webhook бота мессенджера
├── assets/                         # CSS, JS, иконки
├── imsitID_ios/                    # iOS-приложение (Swift)
├── schedule_insert.sql             # SQL-схема и пример данных
├── extract_teacher_schedule.sql    # SQL: расписание преподавателей
└── settings_documentation/         # Документация к отчёту по практике
```

## Требования

- PHP 8.1 или выше
- MySQL 8.0 или выше
- Apache с mod_rewrite (опционально)
- Composer (для зависимостей)

## Установка

### 1. Клонирование репозитория

```bash
git clone https://github.com/YOUR_USERNAME/imsit-schedule.git
cd imsit-schedule
```

### 2. Зависимости

```bash
composer install
```

### 3. База данных

Создайте базу данных MySQL и импортируйте схему:

```bash
mysql -u root -p imsit_schedule < schedule_insert.sql
```

Или выполните только блок `CREATE TABLE` из `schedule_insert.sql` в phpMyAdmin.

### 4. Конфигурация

Скопируйте пример конфигурации и укажите свои параметры:

```bash
cp config/database.example.php config/database.php
```

Отредактируйте `config/database.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'imsit_schedule');
define('DB_USER', 'root');
define('DB_PASS', '');
```

> **Важно:** файл `config/database.php` не должен попадать в Git — он указан в `.gitignore`.

### 5. Webhook мессенджера (опционально)

Задайте токен бота через переменную окружения:

```bash
export IMSIT_TELEGRAM_BOT_TOKEN="your_bot_token_here"
```

Не храните токен в исходном коде при публикации на GitHub.

### 6. Запуск (XAMPP)

1. Скопируйте проект в `htdocs/reports` или настройте VirtualHost
2. Запустите Apache и MySQL в XAMPP
3. Откройте в браузере: `http://localhost/reports/id.php`

## Основные таблицы БД

| Таблица | Назначение |
|---------|------------|
| `schedule_all` | Занятия: группа, неделя, день, пара, дисциплина, аудитория, преподаватель |
| `schedule_settings` | Текущая неделя, день и время |
| `users` | Учётные записи (администратор, пользователи) |
| `group_activity` | Статистика просмотров по группам |

## API (примеры)

```
GET /api/get_group_schedule.php?group=24-ОЗДЗ-01&week=1&day=3
GET /api/get_teacher_schedule.php?teacher=Плотников%20А.В.&week=1&day=3
GET /api/get_current_lesson.php?group=24-ОЗДЗ-01
GET /api/get_available_groups.php
GET /api/get_available_teachers.php
```

Ответ — JSON с полем `success` и массивом `schedule`.

## Роли пользователей

| Роль | Возможности |
|------|-------------|
| Гость | Просмотр расписания, выбор группы/преподавателя |
| Обучающийся | То же + избранное, авторизация |
| Преподаватель | Просмотр личного расписания |
| Администратор | CRUD занятий, настройки, экспорт CSV, пользователи |

## Мобильное приложение

iOS-приложение **imsitID** находится в каталоге `imsitID_ios/`. Подробности — в [imsitID_ios/README.md](imsitID_ios/README.md).

## Безопасность

- Пароли хранятся в хешированном виде (`password_hash`)
- Подготовленные SQL-запросы (PDO)
- CSRF-защита административных форм
- Разграничение прав по ролям

**Не публикуйте в репозитории:**

- `config/database.php` — пароли БД
- Токены ботов мессенджера
- `.vscode/sftp.json` — данные FTP/SSH

## Лицензия

Учебный проект. Использование — по согласованию с образовательной организацией.

## Автор

Дипломный проект, Академия ИМСИТ, 2025–2026.
