# bit.mcp — MCP Сервер для Bitrix24

Модуль реализует [Model Context Protocol (MCP)](https://modelcontextprotocol.io/) сервер поверх Bitrix24.
Позволяет AI-клиентам (Claude Desktop, Cursor, и др.) читать и управлять данными CRM напрямую через чат.

## Стек

- **PHP 8.3** (Docker-контейнер `bitrix-dev-php`)
- **[php-mcp/server ^2.0](https://github.com/php-mcp/server)** — MCP SDK на базе ReactPHP
- **Transport:** stdio (JSON-RPC через stdin/stdout)
- **Bitrix24** — источник данных (CRM, Tasks)

## Структура

```
bit.mcp/
├── CLAUDE.md                        # этот файл
├── cli/
│   └── server.php                   # точка запуска MCP сервера
├── install/
│   ├── index.php                    # Bitrix CModule (bit_mcp)
│   └── version.php
├── lang/
│   ├── ru/install/index.php
│   └── en/install/index.php
└── lib/
    ├── ServerFactory.php            # сборка и запуск сервера
    └── Tools/                       # MCP инструменты (автообнаружение)
        ├── CrmDealTool.php          # crm_get_deals, crm_get_deal
        ├── CrmContactTool.php       # crm_get_contacts, crm_get_contact
        ├── TaskTool.php             # tasks_get_list
        ├── IblockTool.php           # iblock_get_elements, iblock_get_sections, create/update/delete element & section
        └── UserTool.php             # users_get_list, users_get_user, users_create, users_update
```

## Доступные инструменты

| Инструмент | Описание | Параметры |
|---|---|---|
| `crm_get_deals` | Список сделок | `limit`, `stageId`, `responsibleId` |
| `crm_get_deal` | Сделка по ID | `id` |
| `crm_get_contacts` | Список контактов | `limit`, `search` |
| `crm_get_contact` | Контакт по ID | `id` |
| `crm_get_item` | Элемент любой CRM-сущности по ID | `entityTypeId`, `id` |
| `crm_create_item` | Создать элемент CRM | `entityTypeId`, `fields` (JSON) |
| `crm_update_item` | Обновить элемент CRM | `entityTypeId`, `id`, `fields` (JSON) |
| `crm_delete_item` | Удалить элемент CRM (необратимо) | `entityTypeId`, `id` |
| `tasks_get_list` | Список задач | `limit`, `responsibleId`, `groupId`, `status` |
| `iblock_get_elements` | Список элементов инфоблока | `iblockId`, `limit`, `search`, `sectionId` |
| `iblock_create_element` | Создать элемент инфоблока | `iblockId`, `fields` (JSON, поддерживает PROPERTY_VALUES) |
| `iblock_update_element` | Обновить элемент инфоблока | `id`, `fields` (JSON) |
| `iblock_delete_element` | Удалить элемент инфоблока (необратимо) | `id` |
| `iblock_get_sections` | Список разделов инфоблока | `iblockId`, `limit`, `search`, `parentId` |
| `iblock_create_section` | Создать раздел инфоблока | `iblockId`, `fields` (JSON) |
| `iblock_update_section` | Обновить раздел инфоблока | `id`, `fields` (JSON) |
| `iblock_delete_section` | Удалить раздел и его содержимое (необратимо) | `id` |
| `users_get_list` | Список пользователей | `limit`, `search`, `departmentId`, `onlyActive` |
| `users_get_user` | Пользователь по ID | `id` |
| `users_create` | Создать пользователя | `fields` (JSON) |
| `users_update` | Обновить пользователя | `id`, `fields` (JSON) |

## Запуск сервера вручную

```bash
wsl docker exec -i bitrix-dev-php php /var/www/bitrix/local/modules/bit.mcp/cli/server.php
```

## Конфиг Claude Desktop

Файл: `%APPDATA%\Claude\claude_desktop_config.json`

```json
{
  "mcpServers": {
    "bitrix24": {
      "command": "wsl",
      "args": ["docker", "exec", "-i", "bitrix-dev-php", "php",
               "/var/www/bitrix/local/modules/bit.mcp/cli/server.php"]
    }
  }
}
```

> `wsl` используется вместо `docker` напрямую, т.к. Docker установлен только внутри WSL,
> а Claude Desktop запускается как Windows-приложение.

## Как добавить новый инструмент

1. Создать класс в `lib/Tools/МойИнструмент.php`
2. Пространство имён: `Bit\Mcp\Tools`
3. Повесить атрибут `#[McpTool]` на публичные методы
4. Всё — discovery подхватит автоматически, перезапуск не нужен

```php
<?php

namespace Bit\Mcp\Tools;

use PhpMcp\Server\Attributes\McpTool;

class МойИнструмент
{
    #[McpTool(name: 'my_tool', description: 'Что делает инструмент')]
    public function myMethod(string $param): array
    {
        // ... логика Bitrix
        return [];
    }
}
```

## Известные особенности

- **`ob_level=1`** — Bitrix включает output buffering; `ServerFactory` сбрасывает его через `ob_end_clean()` перед стартом сервера, чтобы не загрязнять JSON-RPC поток
- **Логи сервера** — пишутся в stderr, видны в `%APPDATA%\Claude\logs\mcp-server-bitrix24.log`
- **`DATE_CREATE` / `CLOSEDATE`** в сделках приходят как пустой объект `{}` — Bitrix возвращает объект даты, требует явного приведения к строке

## Зависимости (local/composer.json)

```json
"php-mcp/server": "^2.0",
"react/promise": "^3.3"
```

Автозагрузка классов модуля зарегистрирована через PSR-4:
```json
"Bit\\Mcp\\": "modules/bit.mcp/lib/"
```
