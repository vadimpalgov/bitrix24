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
\Bitrix\Main\Loader::includeModule('humanresources');

global $USER;
$USER = new \CUser();
$USER->Authorize(1);

// 1. Проверяем пользователя ivanov (ID 5)
echo '=== Пользователь ivanov (ID=5) ===' . PHP_EOL;
$user = \CUser::GetByID(5)->Fetch();
echo 'UF_DEPARTMENT: ' . json_encode($user['UF_DEPARTMENT']) . PHP_EOL;

// 2. Проверяем иерархию отдела Иванова
$deptId = (int)($user['UF_DEPARTMENT'][0] ?? 0);
echo PHP_EOL . '=== Иерархия отдела ID=' . $deptId . ' ===' . PHP_EOL;
try {
    $nodeRepository    = \Bitrix\HumanResources\Service\Container::getNodeRepository();
    $nodeMemberService = \Bitrix\HumanResources\Service\Container::getNodeMemberService();
    $accessCode = \Bitrix\HumanResources\Compatibility\Utils\DepartmentBackwardAccessCode::makeById($deptId);
    echo 'Access code: ' . $accessCode . PHP_EOL;
    $node = $nodeRepository->getByAccessCode($accessCode);
    if ($node) {
        echo 'Node ID: ' . $node->id . ', Name: ' . $node->name . ', ParentID: ' . $node->parentId . PHP_EOL;
        $heads = $nodeMemberService->getDefaultHeadRoleEmployees($node->id);
        echo 'Head employees: ' . json_encode($heads->getEntityIds()) . PHP_EOL;
        // Идём вверх по иерархии
        $currentNode = $node;
        $level = 0;
        while ($currentNode !== null && $level < 10) {
            $heads = $nodeMemberService->getDefaultHeadRoleEmployees($currentNode->id);
            echo 'Level ' . $level . ': Node=' . $currentNode->name . ' (ID=' . $currentNode->id . '), Heads=' . json_encode($heads->getEntityIds()) . PHP_EOL;
            $currentNode = $currentNode->parentId > 0 ? $nodeRepository->getById($currentNode->parentId) : null;
            $level++;
        }
    } else {
        echo 'Node NOT FOUND for dept ' . $deptId . PHP_EOL;
    }
} catch (\Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
}

// 3. Проверяем HR-отдел по имени
echo PHP_EOL . '=== HR-отделы (поиск по имени "HR") ===' . PHP_EOL;
$res = \CIBlockSection::GetList([], ['=NAME' => 'HR', 'ACTIVE' => 'Y'], false, ['ID', 'NAME', 'IBLOCK_ID']);
while ($s = $res->Fetch()) {
    echo 'Section ID=' . $s['ID'] . ', Name=' . $s['NAME'] . ', IBlock=' . $s['IBLOCK_ID'] . PHP_EOL;
}

// 4. Проверяем формат дат
echo PHP_EOL . '=== Тест формата дат ===' . PHP_EOL;
$formats = ['06/09/2026', '2026-06-09', '09.06.2026'];
foreach ($formats as $fmt) {
    try {
        $date = new \Bitrix\Main\Type\Date($fmt);
        echo $fmt . ' => OK: ' . $date->toString() . PHP_EOL;
    } catch (\Throwable $e) {
        echo $fmt . ' => ERROR: ' . $e->getMessage() . PHP_EOL;
    }
}

// 5. Проверяем есть ли AP-элементы
echo PHP_EOL . '=== AP-элементы (дочерние к ER ID=1) ===' . PHP_EOL;
$factoryAp = \Bitrix\Crm\Service\Container::getInstance()->getFactory(1050);
if ($factoryAp) {
    $items = $factoryAp->getItems(['filter' => ['=PARENT_ID_1046' => 1]]);
    if (empty($items)) {
        echo 'AP-элементов нет' . PHP_EOL;
    }
    foreach ($items as $apItem) {
        echo 'AP ID=' . $apItem->getId()
            . ', ASSIGNED=' . $apItem->getAssignedById()
            . ', STAGE=' . $apItem->getStageId()
            . ', PHASE=' . $apItem->get('UF_CRM_11_PHASE')
            . ', ORDER=' . $apItem->get('UF_CRM_11_ORDER')
            . PHP_EOL;
    }
} else {
    echo 'Factory AP не найдена' . PHP_EOL;
}
