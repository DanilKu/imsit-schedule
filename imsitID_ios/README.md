# imsitID iOS App

Мобильное приложение для просмотра расписания Академии ИМСИТ на iOS.

## Возможности

- ✅ Просмотр расписания для групп и преподавателей
- ✅ Выбор недели (1 или 2) и дня недели
- ✅ Отображение текущей и следующей пары
- ✅ Прогресс текущей пары
- ✅ Поиск по группам и преподавателям
- ✅ Избранное
- ✅ Офлайн режим - полная загрузка расписания для просмотра без интернета
- ✅ Современный дизайн с LiquidGlass эффектами

## Требования

- iOS 18.0+
- Xcode 16.0+
- Swift 6.0

## Установка

1. Откройте проект в Xcode:
   ```bash
   open imsitID.xcodeproj
   ```

2. Выберите целевое устройство или симулятор

3. Нажмите ⌘R для запуска

## Настройка API

По умолчанию приложение использует базовый URL: `https://imsit.shop`

Если нужно изменить URL, отредактируйте файл `Services/APIService.swift`:

```swift
private let baseURL = "https://your-domain.com"
```

## Структура проекта

```
imsitID/
├── Models/              # Модели данных
│   ├── Lesson.swift
│   └── Schedule.swift
├── Views/               # SwiftUI представления
│   ├── HeaderView.swift
│   ├── SelectionPromptView.swift
│   ├── CurrentLessonsView.swift
│   ├── WeekDaySelectorView.swift
│   ├── ScheduleListView.swift
│   ├── GroupSelectionView.swift
│   └── TeacherSelectionView.swift
├── ViewModels/          # Управление состоянием
│   └── AppState.swift
├── Services/            # Сервисы
│   ├── APIService.swift
│   └── StorageService.swift
├── Utilities/           # Утилиты
│   └── GlassModifier.swift
└── ContentView.swift    # Главный view
```

## Функциональность

### Загрузка расписания

Приложение загружает полное расписание для выбранной группы или преподавателя:
- Неделя 1 (дни 1-6)
- Неделя 2 (дни 1-6)

Все данные сохраняются локально для офлайн просмотра.

### Офлайн режим

После первой загрузки расписание сохраняется в UserDefaults и доступно без интернета. Для обновления используйте кнопку обновления в заголовке.

### Избранное

Можно добавлять группы и преподавателей в избранное. Избранные элементы отображаются первыми в списках выбора.

## API Endpoints

Приложение использует следующие API endpoints:

- `GET /api/get_available_groups.php` - список групп
- `GET /api/get_available_teachers.php` - список преподавателей
- `GET /api/get_group_schedule.php?group={group}&week={week}&day={day}` - расписание группы
- `GET /api/get_teacher_schedule_ios.php?teacher={teacher}&week={week}&day={day}` - расписание преподавателя
- `GET /api/get_current_lesson.php?group={group}` - текущая пара группы
- `GET /api/get_teacher_current_lesson.php?teacher_name={teacher}` - текущая пара преподавателя

## Дизайн

Приложение использует современный дизайн с эффектами LiquidGlass (glass morphism):
- Полупрозрачные карточки с размытием фона
- Градиентные фоны
- Плавные анимации
- Темная тема

## Лицензия

Все права защищены.

