#!/usr/bin/env php
<?php
define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

$_SERVER['DOCUMENT_ROOT'] = '/var/www/bitrix';
$_SERVER['HTTP_HOST']     = 'localhost';
$_SERVER['REQUEST_URI']   = '/';
$_SERVER['SERVER_NAME']   = 'localhost';
$_SERVER['SERVER_PORT']   = '80';
$_SERVER['HTTPS']         = 'off';

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

$moduleId = 'parfeon.er';

$settings = [
    // ER_START_STATUS: «Подготовка» — триггер создания AP
    // Item создаётся в NEW, пользователь переводит в PREPARATION — запускаются согласующие
    'ER_START_STATUS'   => 'DT1046_8:PREPARATION',

    // Включить всю цепочку иерархии (директор + промежуточные)
    'LA_ADD_ALL_MANAGERS' => 'Y',
];

foreach ($settings as $key => $value) {
    \COption::SetOptionString($moduleId, $key, $value);
    echo 'SET ' . $key . ' = ' . $value . PHP_EOL;
}

echo PHP_EOL . 'Done.' . PHP_EOL;
