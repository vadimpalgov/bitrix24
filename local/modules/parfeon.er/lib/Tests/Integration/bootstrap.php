<?php

declare(strict_types=1);

define('NO_KEEP_STATISTIC',    true);
define('NO_AGENT_STATISTIC',   true);
define('NOT_CHECK_PERMISSIONS', true);
define('ADMIN_SECTION',        true);

$_SERVER['DOCUMENT_ROOT'] = '/var/www/bitrix';
$_SERVER['HTTP_HOST']     = 'localhost';
$_SERVER['REQUEST_URI']   = '/';
$_SERVER['SERVER_NAME']   = 'localhost';
$_SERVER['SERVER_PORT']   = '80';
$_SERVER['HTTPS']         = 'off';

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

\Bitrix\Main\Loader::includeModule('crm');
\Bitrix\Main\Loader::includeModule('intranet');
\Bitrix\Main\Loader::includeModule('humanresources');
\Bitrix\Main\Loader::includeModule('parfeon.er');

// Composer-автозагрузчик для тестовых классов
require_once __DIR__ . '/../../../../../vendor/autoload.php';
