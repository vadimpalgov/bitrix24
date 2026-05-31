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
\Bitrix\Main\Loader::includeModule('crm');
\Bitrix\Main\Loader::includeModule('intranet');

global $USER;
$USER = new \CUser();
$USER->Authorize(1);

// 1. Проверяем Container
echo '=== Container ===' . PHP_EOL;
$container = \Bitrix\Crm\Service\Container::getInstance();
echo 'Container class: ' . get_class($container) . PHP_EOL;

// 2. Проверяем Factory для ER (1046)
echo PHP_EOL . '=== Factory ER (1046) ===' . PHP_EOL;
$factory = $container->getFactory(1046);
echo 'Factory class: ' . ($factory ? get_class($factory) : 'NULL') . PHP_EOL;

// 3. Проверяем Module settings
echo PHP_EOL . '=== Module Settings ===' . PHP_EOL;
$keys = ['ER_SMART_PROCESS_ID', 'AP_SMART_PROCESS_ID', 'ER_START_STATUS', 'ER_APPROVE_STATUS', 'ER_REJECT_STATUS'];
foreach ($keys as $key) {
    echo $key . ': ' . \Bitrix\Main\Config\Option::get('parfeon.er', $key, '(empty)') . PHP_EOL;
}

// 4. Проверяем отдел sidorova (ID=20)
echo PHP_EOL . '=== Отдел ID=20 (HR?) ===' . PHP_EOL;
$res = \CIBlockSection::GetList([], ['ID' => 20], false, ['ID', 'NAME', 'IBLOCK_ID', 'ACTIVE']);
while ($s = $res->Fetch()) {
    echo 'Section ID=' . $s['ID'] . ', Name="' . $s['NAME'] . '", IBlock=' . $s['IBLOCK_ID'] . ', Active=' . $s['ACTIVE'] . PHP_EOL;
}

// 5. Все отделы верхнего уровня
echo PHP_EOL . '=== Все активные отделы ===' . PHP_EOL;
$res = \CIBlockSection::GetList([], ['ACTIVE' => 'Y'], false, ['ID', 'NAME', 'IBLOCK_ID', 'DEPTH_LEVEL']);
while ($s = $res->Fetch()) {
    echo 'ID=' . $s['ID'] . ', Name="' . $s['NAME'] . '", Depth=' . $s['DEPTH_LEVEL'] . PHP_EOL;
}

// 6. Пользователи в отделе 20
echo PHP_EOL . '=== Пользователи в отделе 20 ===' . PHP_EOL;
$rsUsers = \CUser::GetList('ID', 'ASC', ['ACTIVE' => 'Y', 'UF_DEPARTMENT' => [20]], ['FIELDS' => ['ID', 'NAME', 'LAST_NAME']]);
while ($u = $rsUsers->Fetch()) {
    echo 'ID=' . $u['ID'] . ', ' . $u['NAME'] . ' ' . $u['LAST_NAME'] . PHP_EOL;
}

// 7. ER item current stage vs ER_START_STATUS
echo PHP_EOL . '=== ER Stage Check ===' . PHP_EOL;
if ($factory) {
    $erItem = $factory->getItem(1);
    if ($erItem) {
        $currentStage = $erItem->getStageId();
        $startStatus  = \Bitrix\Main\Config\Option::get('parfeon.er', 'ER_START_STATUS', '');
        echo 'Item stage: ' . $currentStage . PHP_EOL;
        echo 'ER_START_STATUS: ' . $startStatus . PHP_EOL;
        echo 'Match: ' . ($currentStage === $startStatus ? 'YES' : 'NO') . PHP_EOL;
    }
}
