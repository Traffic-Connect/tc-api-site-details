## Документация

#### Плагин отдает информацию о сайте

```
Роут: https://example.com/wp-json/api-details/v1/get
```

### Переменные

**$allowedIP** - IP сервера с которого будут разрешены запросы

### Ответ

```yaml
{
    "success": true,
    "data": {
        "server_ip": "IP текущего сервера",
        "server_info": "Информация о текущем веб сервере (nginx или apache)",
        "server_php": "Версия php",
        "plugins": [ //Плагины на сайте
            {
                "name": "Название плагина",
                "version": "Версия плагина",
                "status": "Статус плагина активен или нет true/false",
                "plugin_file": "Путь к файлу плагина"
            },
            {
                etc...
            },
        ],
        "users": [ //Пользователи админки
            {
                "login": "Логин пользователя",
                "password": "Пароль в зашифрованном виде"
            },
            {
                etc...
            }
        ],
        "theme": { //Активная тема на сайте
            "name": "Название темы",
            "version": "Версия темы"
        },
        "is_static_site_plugin_active": "Включен ли плагин TC Static Site? true/false",
        "is_hb_waf_plugin_active": { //Включен ли плагин TC WAF и список его параметров
            "status": true,
            "options": {
                "general": {
                    //Основные опции
                },
                "other": {
                   //Дополнительные опции
                }
            }
        },
        "is_pretty_links_plugin_active": { //В случае автивации плагина Pretty Links список всех ссылок
            "status": true,
            "links": []
        }
    }
}
