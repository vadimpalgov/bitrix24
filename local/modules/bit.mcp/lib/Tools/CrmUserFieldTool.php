<?php

namespace Bit\Mcp\Tools;

use Bitrix\Main\UserFieldLangTable;
use Bitrix\Main\UserFieldTable;
use CCrmOwnerType;
use CUserTypeEntity;
use PhpMcp\Server\Attributes\McpTool;

class CrmUserFieldTool
{
    #[McpTool(
        name: 'crm_add_user_field',
        description: 'Добавить пользовательское поле (UF_*) к CRM-сущности или смарт-процессу. entityTypeId: сделки=2, контакты=3, компании=4, смарт-процессы по их ENTITY_TYPE_ID (например 1036). Типы поля: string, integer, double, boolean, date, datetime, file, enumeration, employee, iblock_element, crm, money.'
    )]
    public function addUserField(
        int    $entityTypeId,
        string $fieldName,
        string $userTypeId,
        string $labelRu,
        string $labelEn = '',
        bool   $mandatory = false,
        bool   $multiple = false,
        int    $sort = 100
    ): array {
        $ufEntityId = CCrmOwnerType::ResolveUserFieldEntityID($entityTypeId);
        if (!$ufEntityId) {
            return ['success' => false, 'error' => "Неизвестный entityTypeId=$entityTypeId"];
        }

        $fieldName = strtoupper($fieldName);
        if (!str_starts_with($fieldName, 'UF_')) {
            return ['success' => false, 'error' => 'Имя поля должно начинаться с UF_'];
        }

        $labels = [
            'ru' => $labelRu,
            'en' => $labelEn ?: $labelRu,
        ];

        $entity = new CUserTypeEntity();
        $id = $entity->Add([
            'ENTITY_ID'          => $ufEntityId,
            'FIELD_NAME'         => $fieldName,
            'USER_TYPE_ID'       => $userTypeId,
            'MANDATORY'          => $mandatory ? 'Y' : 'N',
            'MULTIPLE'           => $multiple ? 'Y' : 'N',
            'SORT'               => $sort,
            'EDIT_FORM_LABEL'    => $labels,
            'LIST_COLUMN_LABEL'  => $labels,
            'LIST_FILTER_LABEL'  => $labels,
            'ERROR_MESSAGE'      => ['ru' => '', 'en' => ''],
            'HELP_MESSAGE'       => ['ru' => '', 'en' => ''],
        ]);

        if (!$id) {
            global $APPLICATION;
            $error = $APPLICATION->GetException();
            return [
                'success' => false,
                'error'   => $error ? $error->GetString() : 'Неизвестная ошибка при добавлении поля',
            ];
        }

        return ['success' => true, 'id' => (int)$id, 'fieldName' => $fieldName];
    }

    #[McpTool(
        name: 'crm_update_user_field',
        description: 'Обновить пользовательское поле (UF_*): изменить русское/английское название, обязательность, сортировку. Передавайте только те параметры, которые нужно изменить; остальные останутся без изменений.'
    )]
    public function updateUserField(
        int    $entityTypeId,
        string $fieldName,
        string $labelRu = '',
        string $labelEn = '',
        int    $mandatory = -1,
        int    $sort = -1
    ): array {
        $ufEntityId = CCrmOwnerType::ResolveUserFieldEntityID($entityTypeId);
        if (!$ufEntityId) {
            return ['success' => false, 'error' => "Неизвестный entityTypeId=$entityTypeId"];
        }

        $row = UserFieldTable::getList([
            'filter' => ['ENTITY_ID' => $ufEntityId, 'FIELD_NAME' => strtoupper($fieldName)],
            'select' => ['ID'],
            'limit'  => 1,
        ])->fetch();

        if (!$row) {
            return ['success' => false, 'error' => "Поле $fieldName не найдено в entityTypeId=$entityTypeId"];
        }

        $update = [];

        if ($labelRu !== '' || $labelEn !== '') {
            $existing = [];
            foreach (UserFieldLangTable::getList([
                'filter' => ['USER_FIELD_ID' => $row['ID'], 'LANGUAGE_ID' => ['ru', 'en']],
                'select' => ['LANGUAGE_ID', 'EDIT_FORM_LABEL'],
            ])->fetchAll() as $l) {
                $existing[$l['LANGUAGE_ID']] = $l['EDIT_FORM_LABEL'];
            }

            $newLabels = [
                'ru' => $labelRu !== '' ? $labelRu : ($existing['ru'] ?? ''),
                'en' => $labelEn !== '' ? $labelEn : ($existing['en'] ?? ''),
            ];

            $update['EDIT_FORM_LABEL']   = $newLabels;
            $update['LIST_COLUMN_LABEL'] = $newLabels;
            $update['LIST_FILTER_LABEL'] = $newLabels;
        }

        if ($mandatory >= 0) {
            $update['MANDATORY'] = $mandatory ? 'Y' : 'N';
        }

        if ($sort >= 0) {
            $update['SORT'] = $sort;
        }

        if (!$update) {
            return ['success' => false, 'error' => 'Не передано ни одного параметра для обновления'];
        }

        $entity = new CUserTypeEntity();
        $result = $entity->Update($row['ID'], $update);

        if (!$result) {
            global $APPLICATION;
            $error = $APPLICATION->GetException();
            return [
                'success' => false,
                'error'   => $error ? $error->GetString() : 'Неизвестная ошибка при обновлении поля',
            ];
        }

        return ['success' => true, 'id' => (int)$row['ID'], 'fieldName' => strtoupper($fieldName)];
    }

    #[McpTool(
        name: 'crm_delete_user_field',
        description: 'Удалить пользовательское поле (UF_*) из CRM-сущности или смарт-процесса по имени поля. ВНИМАНИЕ: операция необратима, все данные поля будут удалены.'
    )]
    public function deleteUserField(
        int    $entityTypeId,
        string $fieldName
    ): array {
        $ufEntityId = CCrmOwnerType::ResolveUserFieldEntityID($entityTypeId);
        if (!$ufEntityId) {
            return ['success' => false, 'error' => "Неизвестный entityTypeId=$entityTypeId"];
        }

        $row = UserFieldTable::getList([
            'filter' => ['ENTITY_ID' => $ufEntityId, 'FIELD_NAME' => strtoupper($fieldName)],
            'select' => ['ID'],
            'limit'  => 1,
        ])->fetch();

        if (!$row) {
            return ['success' => false, 'error' => "Поле $fieldName не найдено в entityTypeId=$entityTypeId"];
        }

        $entity = new CUserTypeEntity();
        $result = $entity->Delete($row['ID']);

        if (!$result) {
            global $APPLICATION;
            $error = $APPLICATION->GetException();
            return [
                'success' => false,
                'error'   => $error ? $error->GetString() : 'Неизвестная ошибка при удалении поля',
            ];
        }

        return ['success' => true, 'id' => (int)$row['ID'], 'fieldName' => strtoupper($fieldName)];
    }
}
