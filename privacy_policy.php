<?php
require_once 'config/auth.php';
require_once 'config/database.php';

$theme = $_COOKIE['theme'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="ru" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Политика конфиденциальности - imsitID</title>
    <link rel="icon" href="assets/icons/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="assets/icons/favicon-32x32.png" sizes="32x32" type="image/png">
    <link rel="icon" href="assets/icons/favicon-16x16.png" sizes="16x16" type="image/png">
    <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .terms-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .terms-header {
            text-align: center;
            margin-bottom: 40px;
            padding: 30px 0;
            border-bottom: 2px solid var(--border-color);
        }
        
        .terms-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 10px;
        }
        
        .terms-subtitle {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }
        
        .terms-content {
            background: var(--bg-primary);
            border-radius: 12px;
            padding: 40px;
            box-shadow: var(--shadow);
            line-height: 1.8;
        }
        
        .terms-section {
            margin-bottom: 30px;
        }
        
        .terms-section h2 {
            color: var(--accent-color);
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 10px;
        }
        
        .terms-section h3 {
            color: var(--text-primary);
            font-size: 1.2rem;
            font-weight: 600;
            margin: 20px 0 10px 0;
        }
        
        .terms-section p {
            margin-bottom: 15px;
            color: var(--text-color);
            text-align: justify;
        }
        
        .terms-section ul, .terms-section ol {
            margin: 15px 0;
            padding-left: 25px;
        }
        
        .terms-section li {
            margin-bottom: 8px;
            color: var(--text-color);
        }
        
        .terms-section strong {
            color: var(--text-primary);
            font-weight: 600;
        }
        
        .terms-section a {
            color: var(--accent-color);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .terms-section a:hover {
            color: var(--accent-hover);
            text-decoration: underline;
        }
        
        .terms-section .highlight {
            background: rgba(102, 126, 234, 0.1);
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid var(--accent-color);
            margin: 20px 0;
        }
        
        .terms-section .warning {
            background: rgba(255, 193, 7, 0.1);
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #ffc107;
            margin: 20px 0;
        }
        
        .terms-footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 2px solid var(--border-color);
            color: var(--text-secondary);
        }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--accent-color);
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            margin-top: 20px;
        }
        
        .back-btn:hover {
            background: var(--accent-hover);
            transform: translateY(-2px);
        }
        
        @media (max-width: 768px) {
            .terms-container {
                padding: 10px;
            }
            
            .terms-content {
                padding: 20px;
            }
            
            .terms-title {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="header-left">
                <a href="id" class="logo">
                    <i class="fas fa-arrow-left"></i>
                    Расписание
                </a>
            </div>
        </div>
    </header>

    <main class="terms-container">
        <div class="terms-header">
            <h1 class="terms-title">Политика конфиденциальности</h1>
            <p class="terms-subtitle">imsitID</p>
        </div>

        <div class="terms-content">
            <div class="terms-section warning">
                <h3>Важное уведомление</h3>
                <p><strong>Сервис imsitID не имеет никакого отношения к Академии ИМСИТ или любому другому образовательному учреждению.</strong> Это независимый проект, предоставляющий только информационные услуги по расписанию занятий. Источником данных о расписании является официальный сайт Академии ИМСИТ <a href="https://imsit.ru" target="_blank">imsit.ru</a>.</p>
            </div>

            <div class="terms-section">
                <h2>1. Общие положения</h2>
                
                <h3>1.1. Введение</h3>
                <p>Настоящая Политика конфиденциальности (далее — «Политика») описывает, как сервис imsitID (далее — «Сервис», «мы», «нас») собирает, использует, хранит и защищает персональные данные пользователей веб-приложения и Telegram-бота (далее — «Пользователь», «вы», «вас»).</p>
                
                <h3>1.2. Принятие условий</h3>
                <p>Используя наш Сервис, вы соглашаетесь с условиями настоящей Политики конфиденциальности. Если вы не согласны с какими-либо условиями, пожалуйста, не используйте Сервис.</p>
                
                <h3>1.3. Изменения политики</h3>
                <p>Мы оставляем за собой право изменять настоящую Политику. О существенных изменениях пользователи будут уведомлены через систему уведомлений в Telegram-боте или на сайте. Дата последнего обновления указана в конце документа.</p>
            </div>

            <div class="terms-section">
                <h2>2. Какие данные мы собираем</h2>
                
                <h3>2.1. Данные, собираемые автоматически</h3>
                <p>При использовании Сервиса мы можем собирать следующие данные:</p>
                <ul>
                    <li><strong>Telegram данные:</strong> Telegram username, Telegram ID (при использовании Telegram-бота)</li>
                    <li><strong>Данные о выборе:</strong> Выбранная группа или преподаватель в боте</li>
                    <li><strong>Технические данные:</strong> IP-адрес, тип браузера, операционная система, время доступа</li>
                    <li><strong>Данные об использовании:</strong> Страницы, которые вы посещаете, время посещения, частота использования</li>
                </ul>
                
                <h3>2.2. Данные, предоставляемые пользователем</h3>
                <p>При создании аккаунта или использовании дополнительных функций вы можете предоставить:</p>
                <ul>
                    <li>Имя (необязательно)</li>
                    <li>Номер группы (необязательно)</li>
                    <li>Настройки уведомлений</li>
                    <li>Предпочтения отображения (тема, избранные группы/преподаватели)</li>
                </ul>
                
                <h3>2.3. Данные о расписании</h3>
                <p>Данные о расписании занятий получаются с официального сайта Академии ИМСИТ <a href="https://imsit.ru" target="_blank">imsit.ru</a>. Мы не собираем и не храним персональные данные студентов или преподавателей из расписания — только публично доступную информацию о времени занятий, аудиториях и названиях дисциплин.</p>
            </div>

            <div class="terms-section">
                <h2>3. Как мы используем ваши данные</h2>
                
                <h3>3.1. Основные цели использования</h3>
                <p>Персональные данные используются исключительно для:</p>
                <ul>
                    <li><strong>Предоставления услуг расписания</strong> — отображение персонального расписания выбранной группы или преподавателя</li>
                    <li><strong>Персонализации интерфейса</strong> — сохранение ваших предпочтений (выбранная группа, тема оформления, избранное)</li>
                    <li><strong>Отправки уведомлений</strong> — информирование об изменениях в расписании (только при вашем согласии)</li>
                    <li><strong>Улучшения качества сервиса</strong> — анализ использования для улучшения функциональности</li>
                    <li><strong>Технической поддержки</strong> — решение технических проблем и ответы на обращения</li>
                </ul>
                
                <h3>3.2. Что мы НЕ делаем с вашими данными</h3>
                <p>Мы НЕ используем ваши данные для:</p>
                <ul>
                    <li>Продажи третьим лицам</li>
                    <li>Рекламных рассылок без вашего согласия</li>
                    <li>Передачи данных образовательным учреждениям</li>
                    <li>Создания профилей для коммерческих целей</li>
                    <li>Любых других целей, не указанных в настоящей Политике</li>
                </ul>
            </div>

            <div class="terms-section">
                <h2>4. Хранение и защита данных</h2>
                
                <h3>4.1. Срок хранения данных</h3>
                <p>Мы храним ваши персональные данные до тех пор, пока:</p>
                <ul>
                    <li>Вы используете Сервис</li>
                    <li>Не запросите удаление аккаунта</li>
                    <li>Не требуется для выполнения юридических обязательств</li>
                </ul>
                <p>После удаления аккаунта все персональные данные удаляются безвозвратно в течение 30 дней.</p>
                
                <h3>4.2. Меры защиты</h3>
                <p>Мы принимаем технические и организационные меры для защиты персональных данных:</p>
                <ul>
                    <li>Шифрование данных при передаче (HTTPS)</li>
                    <li>Защита серверов от несанкционированного доступа</li>
                    <li>Регулярное обновление программного обеспечения</li>
                    <li>Ограничение доступа к данным только для уполномоченных лиц</li>
                    <li>Регулярное резервное копирование данных</li>
                </ul>
                
                <h3>4.3. Передача данных третьим лицам</h3>
                <p>Мы НЕ передаём ваши персональные данные третьим лицам, за исключением случаев:</p>
                <ul>
                    <li>Когда это требуется по закону или по запросу государственных органов</li>
                    <li>Когда это необходимо для защиты наших прав и безопасности</li>
                    <li>При использовании сервисов хостинга и инфраструктуры (данные остаются под нашим контролем)</li>
                </ul>
            </div>

            <div class="terms-section">
                <h2>5. Ваши права</h2>
                
                <h3>5.1. Право на доступ</h3>
                <p>Вы имеете право запросить информацию о том, какие персональные данные мы храним о вас, и получить копию этих данных.</p>
                
                <h3>5.2. Право на исправление</h3>
                <p>Вы можете в любое время изменить или обновить свои персональные данные через настройки аккаунта или связавшись с нами.</p>
                
                <h3>5.3. Право на удаление</h3>
                <p>Вы можете запросить удаление вашего аккаунта и всех связанных персональных данных. После удаления данные будут безвозвратно удалены в течение 30 дней.</p>
                
                <h3>5.4. Право на отзыв согласия</h3>
                <p>Вы можете в любое время отозвать согласие на обработку персональных данных, отключив уведомления или удалив аккаунт.</p>
                
                <h3>5.5. Право на ограничение обработки</h3>
                <p>Вы можете запросить ограничение обработки ваших персональных данных в определённых случаях.</p>
                
                <h3>5.6. Как реализовать свои права</h3>
                <p>Для реализации своих прав свяжитесь с нами через:</p>
                <ul>
                    <li><strong>Telegram администратора:</strong> @cowgivesmilk</li>
                    <li><strong>Telegram поддержки:</strong> @cowgivesmilk</li>
                </ul>
            </div>

            <div class="terms-section">
                <h2>6. Cookies и аналогичные технологии</h2>
                
                <h3>6.1. Использование cookies</h3>
                <p>Мы используем cookies и аналогичные технологии для:</p>
                <ul>
                    <li>Сохранения ваших предпочтений (тема оформления, выбранная группа)</li>
                    <li>Улучшения работы Сервиса</li>
                    <li>Анализа использования Сервиса</li>
                </ul>
                
                <h3>6.2. Управление cookies</h3>
                <p>Вы можете управлять cookies через настройки вашего браузера. Однако отключение cookies может повлиять на функциональность Сервиса.</p>
            </div>

            <div class="terms-section">
                <h2>7. Данные несовершеннолетних</h2>
                
                <h3>7.1. Возраст пользователей</h3>
                <p>Сервис может использоваться лицами любого возраста. Мы не собираем специальную информацию о возрасте пользователей. Если вы являетесь родителем или опекуном и считаете, что ваш ребёнок предоставил нам персональные данные, свяжитесь с нами для удаления этих данных.</p>
            </div>

            <div class="terms-section">
                <h2>8. Международная передача данных</h2>
                
                <h3>8.1. Хранение данных</h3>
                <p>Ваши персональные данные хранятся на серверах, расположенных в Российской Федерации. Мы не передаём данные за пределы Российской Федерации, за исключением случаев, когда это требуется по закону.</p>
            </div>

            <div class="terms-section">
                <h2>9. Изменения в политике</h2>
                
                <h3>9.1. Уведомления об изменениях</h3>
                <p>Мы можем периодически обновлять настоящую Политику конфиденциальности. О существенных изменениях мы уведомим вас через:</p>
                <ul>
                    <li>Уведомления в Telegram-боте</li>
                    <li>Объявление на сайте</li>
                    <li>Email-уведомление (если вы предоставили email)</li>
                </ul>
                
                <h3>9.2. Продолжение использования</h3>
                <p>Продолжение использования Сервиса после внесения изменений означает ваше согласие с обновлённой Политикой конфиденциальности.</p>
            </div>

            <div class="terms-section">
                <h2>10. Контакты</h2>
                
                <h3>10.1. Служба поддержки</h3>
                <p>По всем вопросам, связанным с обработкой персональных данных, обращайтесь:</p>
                <ul>
                    <li><strong>Telegram администратора:</strong> @cowgivesmilk</li>
                    <li><strong>Telegram поддержки:</strong> @cowgivesmilk</li>
                    <li><strong>Telegram канал:</strong> <a href="https://t.me/imsitID" target="_blank">@imsitID</a></li>
                    <li><strong>Telegram бот:</strong> <a href="https://t.me/imsitid_bot" target="_blank">@imsitid_bot</a></li>
                </ul>
                
                <h3>10.2. Время ответа</h3>
                <p>Мы стремимся отвечать на обращения в течение 24 часов в рабочие дни.</p>
            </div>

            <div class="terms-section highlight">
                <h3>Важная информация</h3>
                <p><strong>Используя imsitID, вы подтверждаете, что ознакомились с настоящей Политикой конфиденциальности и принимаете все её условия.</strong></p>
                <p>Сервис не имеет отношения к Академии ИМСИТ или любому другому образовательному учреждению. Источником данных о расписании является официальный сайт Академии ИМСИТ <a href="https://imsit.ru" target="_blank">imsit.ru</a>.</p>
                <p>Если у вас есть вопросы по обработке персональных данных, пожалуйста, свяжитесь с нами перед использованием сервиса.</p>
            </div>
        </div>

        <div class="terms-footer">
            <p><strong>Дата последнего обновления:</strong> <?php echo date('d.m.Y'); ?></p>
            <p><strong>Версия:</strong> 1.0</p>
            <a href="user_agreement.php" class="back-btn" style="margin-right: 10px;">
                <i class="fas fa-file-contract"></i>
                Пользовательское соглашение
            </a>
            <a href="login" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Вернуться к входу
            </a>
        </div>
    </main>
</body>
</html>
