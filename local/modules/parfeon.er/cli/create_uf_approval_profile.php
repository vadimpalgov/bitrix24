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

$oUserTypeEntity = new CUserTypeEntity();

// Проверяем, не существует ли уже поле
$existing = CUserTypeEntity::GetList([], [
    'ENTITY_ID'  => 'USER',
    'FIELD_NAME' => 'UF_APPROVAL_PROFILE',
])->Fetch();

if ($existing) {
    echo 'Already exists: ID=' . $existing['ID'] . PHP_EOL;
    exit(0);
}

$fields = [
    'ENTITY_ID'         => 'USER',
    'FIELD_NAME'        => 'UF_APPROVAL_PROFILE',
    'USER_TYPE_ID'      => 'integer',
    'SORT'              => 100,
    'MULTIPLE'          => 'N',
    'MANDATORY'         => 'N',
    'SHOW_FILTER'       => 'N',
    'SHOW_IN_LIST'      => 'Y',
    'EDIT_IN_LIST'      => 'Y',
    'IS_SEARCHABLE'     => 'N',
    'SETTINGS'          => ['DEFAULT_VALUE' => ''],
    'EDIT_FORM_LABEL'   => ['ru' => 'Профиль согласования', 'en' => 'Approval Profile'],
    'LIST_COLUMN_LABEL' => ['ru' => 'Профиль согласования', 'en' => 'Approval Profile'],
    'LIST_FILTER_LABEL' => ['ru' => 'Профиль согласования', 'en' => 'Approval Profile'],
    'HELP_MESSAGE'      => ['ru' => 'ID профиля согласования (ProfileParticipants, смарт-процесс 1058)', 'en' => 'Approval Profile ID (ProfileParticipants)'],
];

$id = $oUserTypeEntity->Add($fields);

if ($id) {
    echo 'Created: ID=' . $id . PHP_EOL;
} else {
    global $APPLICATION;
    $error = $APPLICATION->GetException();
    echo 'ERROR: ' . ($error ? $error->GetString() : 'unknown') . PHP_EOL;
    exit(1);
}
