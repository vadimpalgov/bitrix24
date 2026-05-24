#!/usr/bin/env php
<?php

/**
 * MCP Server entry point — запускается через Docker exec.
 *
 * Команда запуска:
 *   docker exec -i bitrix_php php /var/www/bitrix/local/modules/bit.mcp/cli/server.php
 *
 * Конфиг MCP клиента (Claude Desktop / Cursor / etc.):
 * {
 *   "mcpServers": {
 *     "bitrix24": {
 *       "command": "docker",
 *       "args": ["exec", "-i", "bitrix_php", "php", "/var/www/bitrix/local/modules/bit.mcp/cli/server.php"]
 *     }
 *   }
 * }
 */

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('NO_LANG_FILES', false);
define('NOT_CHECK_PERMISSIONS', true);

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 4); // /var/www/bitrix

require_once $_SERVER['DOCUMENT_ROOT'] . '/local/vendor/autoload.php';
$_SERVER['HTTP_HOST']     = 'localhost';
$_SERVER['REQUEST_URI']   = '/';
$_SERVER['SERVER_NAME']   = 'localhost';
$_SERVER['SERVER_PORT']   = '80';
$_SERVER['HTTPS']         = 'off';

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

\Bitrix\Main\Loader::includeModule('crm');
\Bitrix\Main\Loader::includeModule('tasks');

// Авторизуем администратора, чтобы Factory операции (create/update)
// корректно заполняли CREATED_BY / UPDATED_BY и проходили валидацию.
global $USER;
$USER = new \CUser();
$USER->Authorize(1);

use Bit\Mcp\ServerFactory;

ServerFactory::run($_SERVER['DOCUMENT_ROOT']);
