# Подключение проекта к GitHub

## Шаг 1. Создайте репозиторий на GitHub

1. Откройте [https://github.com/new](https://github.com/new)
2. **Repository name:** `imsit-schedule` (или любое имя)
3. **Public** или **Private**
4. **Не** ставьте галочки «Add README», «Add .gitignore» — они уже есть локально
5. Нажмите **Create repository**

Скопируйте URL репозитория, например:
`https://github.com/ВАШ_ЛОГИН/imsit-schedule.git`

---

## Шаг 2. Команды в терминале

Откройте терминал и выполните (замените URL на свой):

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/reports

# Если git ещё не инициализирован:
git init

# Настройка имени (один раз на компьютере, если ещё не делали):
# git config --global user.name "Ваше Имя"
# git config --global user.email "your@email.com"

git add .
git status   # проверьте: database.php НЕ должен быть в списке

git commit -m "Initial commit: информационная система электронного расписания ИМСИТ"

git branch -M main

git remote add origin https://github.com/ВАШ_ЛОГИН/imsit-schedule.git

git push -u origin main
```

При `git push` GitHub попросит логин. Используйте **Personal Access Token** вместо пароля:
[github.com/settings/tokens](https://github.com/settings/tokens) → Generate new token (classic) → scope `repo`.

---

## Шаг 3. Проверка

```bash
git remote -v
git log -1
```

На GitHub должны появиться файлы: `README.md`, `id.php`, `includes/ScheduleManager.php` и т.д.

**Не должно быть:** `config/database.php`, `vendor/`, `.vscode/`.

---

## Дальнейшая работа

После изменений в коде:

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/reports
git add .
git commit -m "Описание изменений"
git push
```

---

## SSH вместо HTTPS (опционально)

```bash
git remote set-url origin git@github.com:ВАШ_ЛОГИН/imsit-schedule.git
git push -u origin main
```

Нужен SSH-ключ: [docs.github.com/en/authentication/connecting-to-github-with-ssh](https://docs.github.com/en/authentication/connecting-to-github-with-ssh)

---

## ⚠️ Безопасность перед push

1. `config/database.php` — в `.gitignore`, не попадёт в Git
2. В `api/bot_schedule_webhook.php` есть токен бота — перед публичным репозиторием уберите его из кода и смените токен у @BotFather
