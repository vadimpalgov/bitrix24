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

global $USER;
$USER = new \CUser();
$USER->Authorize(1);

// Проверяем: используется ли наша фабрика
$container = \Bitrix\Crm\Service\Container::getInstance();
echo 'Container class: ' . get_class($container) . PHP_EOL;

$factory = $container->getFactory(1046);
echo 'Factory class: ' . get_class($factory) . PHP_EOL;

// Вручную запускаем операцию обновления для ER ID=1
$erItem = $factory->getItem(1);
if (!$erItem) {
    echo 'ER item not found' . PHP_EOL;
    exit(1);
}

echo PHP_EOL . 'Before resolvers:' . PHP_EOL;
echo 'STAGE_ID: ' . $erItem->getStageId() . PHP_EOL;
echo 'ASSIGNED_BY_ID: ' . $erItem->getAssignedById() . PHP_EOL;
echo 'HR_MANAGERS: ' . json_encode($erItem->get('UF_CRM_10_HR')) . PHP_EOL;
echo 'HEAD_OF_DEPARTMENT: ' . json_encode($erItem->get('UF_CRM_10_HEAD_OF_DEPARTMENT')) . PHP_EOL;

// Вручную вызываем HRManagersResolver
echo PHP_EOL . '=== HRManagersResolver ===' . PHP_EOL;
$hrResolver = new \Parfeon\Er\Operation\EmployeeRequest\HRManagersResolver();
$hrResult = $hrResolver->process($erItem);
if ($hrResult->isSuccess()) {
    echo 'HR after resolver: ' . json_encode($erItem->get('UF_CRM_10_HR')) . PHP_EOL;
} else {
    echo 'HR resolver errors: ' . implode(', ', $hrResult->getErrorMessages()) . PHP_EOL;
}

// Вручную вызываем DepartmentManagerResolver
echo PHP_EOL . '=== DepartmentManagerResolver ===' . PHP_EOL;
$deptResolver = new \Parfeon\Er\Operation\EmployeeRequest\DepartmentManagerResolver();
$deptResult = $deptResolver->process($erItem);
if ($deptResult->isSuccess()) {
    echo 'HEAD_OF_DEPARTMENT after resolver: ' . json_encode($erItem->get('UF_CRM_10_HEAD_OF_DEPARTMENT')) . PHP_EOL;
} else {
    echo 'Dept resolver errors: ' . implode(', ', $deptResult->getErrorMessages()) . PHP_EOL;
}

// Сохраняем результат через операцию обновления
echo PHP_EOL . '=== Сохранение ===' . PHP_EOL;
$operation = $factory->getUpdateOperation($erItem);
$saveResult = $operation->disableCheckAccess()->launch();
if ($saveResult->isSuccess()) {
    echo 'Saved OK' . PHP_EOL;
} else {
    echo 'Save errors: ' . implode(', ', $saveResult->getErrorMessages()) . PHP_EOL;
}

// Проверяем AP после сохранения
echo PHP_EOL . '=== AP-элементы ===' . PHP_EOL;
$factoryAp = $container->getFactory(1050);
$apItems = $factoryAp->getItems(['filter' => ['=PARENT_ID_1046' => 1]]);
if (empty($apItems)) {
    echo 'AP-элементов нет' . PHP_EOL;
} else {
    foreach ($apItems as $ap) {
        echo 'AP ID=' . $ap->getId()
            . ', ASSIGNED=' . $ap->getAssignedById()
            . ', PHASE=' . $ap->get('UF_CRM_11_PHASE')
            . ', ORDER=' . $ap->get('UF_CRM_11_ORDER')
            . ', STAGE=' . $ap->getStageId()
            . PHP_EOL;
    }
}
