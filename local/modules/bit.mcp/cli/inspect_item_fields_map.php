<?php
define('NO_KEEP_STATISTIC', true);
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

$factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(2);

// Полный набор полей из сделки #160 (без TITLE)
$data = [
    'STAGE_ID'               => 'NEW',
    'TYPE_ID'                => 'SALE',
    'COMPANY_ID'             => 7,
    'ASSIGNED_BY_ID'         => 31,
    'CREATED_BY_ID'          => 1,
    'MODIFY_BY_ID'           => 1,
    'CURRENCY_ID'            => 'USD',
    'OPPORTUNITY'            => 0,
    'BEGINDATE'              => '07.05.2026',
    'CLOSEDATE'              => '14.05.2026',
    'OPENED'                 => true,
    'CATEGORY_ID'            => 0,
    'UF_CRM_1760618027'      => 110,   // Terms of Delivery
    'UF_CRM_1757058931'      => 222,   // Destination
    'UF_CRM_1764753364'      => 192,   // Deal currency
    'UF_CRM_1770191086'      => '26',  // Prime Company
    'UF_CRM_1770386392'      => 249,   // Deal Payment Terms Details
    'UF_CRM_1759386867'      => 76,    // Priority
    'UF_CRM_1760115859'      => 108,   // Commercial Offer Validity
    'UF_CRM_1770365062'      => 246,   // Deal Payment Terms
    'UF_CRM_1770376020'      => 346,   // RFQ Validity
    'UF_DEAL_RESPONSIBLE_TEAM' => 33,
    'UF_DEAL_RESPONSIBLE_EMPLOYEES' => [31, 29],
    'UF_CRM_1759236915'      => ['242'],
    'UF_CRM_1759321988'      => ['160'],
];

$item = $factory->createItem();
$item->setFromCompatibleData($data);
$result = $item->save();

if ($result->isSuccess()) {
    $id = $item->getId();
    echo "OK, created id=$id\n";
    echo "TITLE = " . $item->get('TITLE') . "\n";
    // Удаляем тестовую запись
    $factory->getDeleteOperation($item)->launch();
    echo "deleted\n";
} else {
    echo "ERRORS: " . implode('; ', $result->getErrorMessages()) . "\n";
}
