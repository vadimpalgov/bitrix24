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

// Обязательные UF_ поля сделок с метками
$con = \Bitrix\Main\Application::getConnection();
$res = $con->query("
    SELECT f.FIELD_NAME, f.USER_TYPE_ID, f.MANDATORY, l.EDIT_FORM_LABEL
    FROM b_user_field f
    LEFT JOIN b_user_field_lang l ON l.USER_FIELD_ID = f.ID AND l.LANGUAGE_ID = 'en'
    WHERE f.ENTITY_ID = 'CRM_DEAL'
    ORDER BY f.SORT
");
while ($row = $res->fetch()) {
    $req = $row['MANDATORY'] === 'Y' ? ' [REQUIRED]' : '';
    echo "{$row['FIELD_NAME']} [{$row['USER_TYPE_ID']}]{$req} — \"{$row['EDIT_FORM_LABEL']}\"\n";
}
