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

$moduleId = 'parfeon.er';
$erId = \COption::GetOptionString($moduleId, 'ER_SMART_PROCESS_ID');
$apId = \COption::GetOptionString($moduleId, 'AP_SMART_PROCESS_ID');

echo '=== Настройки модуля ===' . PHP_EOL;
echo 'ER_SMART_PROCESS_ID=' . $erId . PHP_EOL;
echo 'AP_SMART_PROCESS_ID=' . $apId . PHP_EOL;
echo 'ER_START_STATUS='   . \COption::GetOptionString($moduleId, 'ER_START_STATUS') . PHP_EOL;
echo 'ER_APPROVE_STATUS=' . \COption::GetOptionString($moduleId, 'ER_APPROVE_STATUS') . PHP_EOL;
echo 'ER_REJECT_STATUS='  . \COption::GetOptionString($moduleId, 'ER_REJECT_STATUS') . PHP_EOL;
echo 'AP_START_STATUS='   . \COption::GetOptionString($moduleId, 'AP_START_STATUS') . PHP_EOL;
echo 'AP_APPROVE_STATUS=' . \COption::GetOptionString($moduleId, 'AP_APPROVE_STATUS') . PHP_EOL;
echo 'AP_REJECT_STATUS='  . \COption::GetOptionString($moduleId, 'AP_REJECT_STATUS') . PHP_EOL;
echo 'LA_EXCLUDE_DIRECTOR='   . \COption::GetOptionString($moduleId, 'LA_EXCLUDE_DIRECTOR', 'N') . PHP_EOL;
echo 'LA_ADD_ALL_MANAGERS='   . \COption::GetOptionString($moduleId, 'LA_ADD_ALL_MANAGERS', 'N') . PHP_EOL;

$factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory((int)$erId);
if ($factory) {
    echo PHP_EOL . '=== ER Stages ===' . PHP_EOL;
    foreach ($factory->getStages() as $stage) {
        echo '  ' . $stage->getStatusId() . ' => ' . $stage->getName() . PHP_EOL;
    }
}

$factoryAp = \Bitrix\Crm\Service\Container::getInstance()->getFactory((int)$apId);
if ($factoryAp) {
    echo PHP_EOL . '=== AP Stages ===' . PHP_EOL;
    foreach ($factoryAp->getStages() as $stage) {
        echo '  ' . $stage->getStatusId() . ' => ' . $stage->getName() . PHP_EOL;
    }
}
