# MCP-серверы для тестирования

Конфигурация из `C:\Users\vpalg\AppData\Roaming\Claude\claude_desktop_config.json`.

---

## bitrix24

Запрос отправляется через `wsl docker exec` к PHP-серверу внутри контейнера.

```json
{
  "bitrix24": {
    "command": "wsl",
    "args": [
      "docker",
      "exec",
      "-i",
      "bitrix-dev-php",
      "php",
      "/var/www/bitrix/local/modules/bit.mcp/cli/server.php"
    ]
  }
}
```

**Сервер:** `bitrix-dev-php` (Docker-контейнер)  
**Скрипт:** `/var/www/bitrix/local/modules/bit.mcp/cli/server.php`

---

# OpenAI Codex CLI — подключение MCP bitrix24

**Установка:**

```bash
npm install -g @openai/codex
```

**Аутентификация:**

```bash
export OPENAI_API_KEY="sk-..."
```

---

## Конфигурация (`~/.codex/config.yaml`)

```yaml
model: o4-mini
approvalMode: suggest   # auto | suggest | full-auto

mcpServers:
  bitrix24:
    command: wsl
    args:
      - docker
      - exec
      - -i
      - bitrix-dev-php
      - php
      - /var/www/bitrix/local/modules/bit.mcp/cli/server.php
```

---

## Примеры запросов к MCP bitrix24

```bash
# Интерактивный режим
codex

# Одиночный запрос
codex "получи список пользователей через MCP bitrix24"
codex --model o4-mini "найди все заявки на отпуск со статусом 'на согласовании'"
codex --approval-mode full-auto "создай тестовую заявку через crm_create_item"
```

Что говорить Codex для вызова инструментов:

```
# Список пользователей
"вызови mcp bitrix24 users_get_list и выведи результат"

# Поля смарт-процесса
"через mcp bitrix24 crm_get_entity_fields получи поля entityTypeId=10"

# Создать элемент
"создай тестовую заявку: crm_create_item entityTypeId=10, title='Тест от Codex'"

# Получить элемент
"получи элемент id=1 из смарт-процесса entityTypeId=10"
```
