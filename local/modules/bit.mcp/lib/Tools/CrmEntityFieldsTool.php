<?php

namespace Bit\Mcp\Tools;

use Bitrix\Crm\Service\Container;
use Bitrix\Main\ORM\Fields\BooleanField;
use Bitrix\Main\ORM\Fields\DateField;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\ExpressionField;
use Bitrix\Main\ORM\Fields\FloatField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;
use Bitrix\Main\ORM\Fields\UserTypeField;
use Bitrix\Iblock\IblockTable;
use Bitrix\Main\Application;
use Bitrix\Main\UserFieldLangTable;
use Bitrix\Main\UserFieldTable;
use CCrmOwnerType;
use PhpMcp\Server\Attributes\McpTool;

class CrmEntityFieldsTool
{
    #[McpTool(
        name: 'crm_get_entity_fields',
        description: 'Получить все поля CRM-сущности по её ENTITY_TYPE_ID: ORM-поля, пользовательские поля (UF_*) и родительские связи (PARENT_ID_*). Работает для сделок (2), контактов (3), компаний (4), лидов (1), а также для любого смарт-процесса по его ENTITY_TYPE_ID.'
    )]
    public function getEntityFields(int $entityTypeId): array
    {
        $factory = Container::getInstance()->getFactory($entityTypeId);
        if (!$factory) {
            return ['error' => "Фабрика не найдена для entityTypeId=$entityTypeId"];
        }

        return [
            'entityTypeId'   => $entityTypeId,
            'entityTypeName' => CCrmOwnerType::ResolveName($entityTypeId),
            'ormFields'      => $this->getOrmFields($factory),
            'userFields'     => $this->getUserFields($entityTypeId),
            'parentRelations' => $this->getParentRelations($entityTypeId),
        ];
    }

    // -------------------------------------------------------------------------

    private function getOrmFields($factory): array
    {
        $dataClass = $factory->getDataClass();
        $entity    = $dataClass::getEntity();
        $result    = [];

        foreach ($entity->getFields() as $name => $field) {
            // Пропускаем Reference-поля и вычисляемые дубликаты (_SINGLE, _SHORT)
            if ($field instanceof Reference) {
                continue;
            }
            if (preg_match('/_(SINGLE|SHORT)$/', $name)) {
                continue;
            }

            $type = match (true) {
                $field instanceof IntegerField  => 'integer',
                $field instanceof FloatField    => 'float',
                $field instanceof BooleanField  => 'boolean',
                $field instanceof DatetimeField => 'datetime',
                $field instanceof DateField     => 'date',
                $field instanceof TextField     => 'text',
                $field instanceof StringField   => 'string',
                $field instanceof ExpressionField => 'expression',
                $field instanceof UserTypeField => 'usertype',
                default                         => (new \ReflectionClass($field))->getShortName(),
            };

            // Пользовательские поля уже войдут в userFields — не дублируем
            if ($field instanceof UserTypeField) {
                continue;
            }

            $result[] = ['name' => $name, 'type' => $type];
        }

        return $result;
    }

    private function getUserFields(int $entityTypeId): array
    {
        $ufEntityId = CCrmOwnerType::ResolveUserFieldEntityID($entityTypeId);
        if (!$ufEntityId) {
            return [];
        }

        $rows = UserFieldTable::getList([
            'filter' => ['ENTITY_ID' => $ufEntityId],
            'select' => ['ID', 'FIELD_NAME', 'USER_TYPE_ID', 'MULTIPLE', 'MANDATORY', 'SORT', 'SETTINGS'],
            'order'  => ['SORT' => 'ASC', 'FIELD_NAME' => 'ASC'],
        ])->fetchAll();

        if (!$rows) {
            return [];
        }

        $fieldIds = array_column($rows, 'ID');

        // Labels (ru / en)
        $labels = [];
        foreach (UserFieldLangTable::getList([
            'filter' => ['USER_FIELD_ID' => $fieldIds, 'LANGUAGE_ID' => ['ru', 'en']],
            'select' => ['USER_FIELD_ID', 'LANGUAGE_ID', 'EDIT_FORM_LABEL'],
        ])->fetchAll() as $l) {
            $labels[$l['USER_FIELD_ID']][$l['LANGUAGE_ID']] = $l['EDIT_FORM_LABEL'];
        }

        // Iblock names for iblock_element / iblock_element_dp fields
        $iblockIds = [];
        foreach ($rows as $r) {
            if (in_array($r['USER_TYPE_ID'], ['iblock_element', 'iblock_element_dp'], true)) {
                $id = (int)($r['SETTINGS']['IBLOCK_ID'] ?? 0);
                if ($id > 0) {
                    $iblockIds[] = $id;
                }
            }
        }
        $iblockNames = [];
        if ($iblockIds) {
            \Bitrix\Main\Loader::includeModule('iblock');
            foreach (IblockTable::getList([
                'filter' => ['ID' => array_unique($iblockIds)],
                'select' => ['ID', 'NAME'],
            ])->fetchAll() as $ib) {
                $iblockNames[(int)$ib['ID']] = $ib['NAME'];
            }
        }

        // Enum values for enumeration fields
        $enumValues = [];
        $enumFieldIds = array_column(
            array_filter($rows, static fn($r) => $r['USER_TYPE_ID'] === 'enumeration'),
            'ID'
        );
        if ($enumFieldIds) {
            $in = implode(',', array_map('intval', $enumFieldIds));
            $res = Application::getConnection()->query(
                "SELECT ID, USER_FIELD_ID, VALUE, XML_ID FROM b_user_field_enum WHERE USER_FIELD_ID IN ($in) ORDER BY USER_FIELD_ID, SORT"
            );
            while ($e = $res->fetch()) {
                $enumValues[$e['USER_FIELD_ID']][] = [
                    'id'     => $e['ID'],
                    'value'  => $e['VALUE'],
                    'xml_id' => $e['XML_ID'],
                ];
            }
        }

        return array_map(function ($r) use ($labels, $iblockNames, $enumValues) {
            $fieldLabels = $labels[$r['ID']] ?? [];
            return [
                'name'      => $r['FIELD_NAME'],
                'type'      => $r['USER_TYPE_ID'],
                'multiple'  => $r['MULTIPLE'] === 'Y',
                'mandatory' => $r['MANDATORY'] === 'Y',
                'label_ru'  => $fieldLabels['ru'] ?? null,
                'label_en'  => $fieldLabels['en'] ?? null,
                'settings'  => $this->buildSettings($r['USER_TYPE_ID'], $r['SETTINGS'] ?? [], $iblockNames, $enumValues[$r['ID']] ?? []),
            ];
        }, $rows);
    }

    private function buildSettings(string $type, array $s, array $iblockNames, array $enumValues): ?array
    {
        return match ($type) {
            'iblock_element', 'iblock_element_dp' => array_filter([
                'iblock_id'   => (int)($s['IBLOCK_ID'] ?? 0) ?: null,
                'iblock_name' => $iblockNames[(int)($s['IBLOCK_ID'] ?? 0)] ?? null,
                'display'     => $s['DISPLAY'] ?? null,
                'depends_on'  => isset($s['LIST_DEPENDS_BY']) && $s['LIST_DEPENDS_BY'] ? (int)$s['LIST_DEPENDS_BY'] : null,
            ]),
            'enumeration' => [
                'display' => $s['DISPLAY'] ?? null,
                'values'  => $enumValues,
            ],
            'crm' => array_keys(array_filter($s, static fn($v) => $v === 'Y')),
            'double' => array_filter([
                'precision'     => isset($s['PRECISION']) && (int)$s['PRECISION'] > 0 ? (int)$s['PRECISION'] : null,
                'min_value'     => isset($s['MIN_VALUE']) && (float)$s['MIN_VALUE'] != 0.0 ? (float)$s['MIN_VALUE'] : null,
                'max_value'     => isset($s['MAX_VALUE']) && (float)$s['MAX_VALUE'] != 0.0 ? (float)$s['MAX_VALUE'] : null,
                'default_value' => $s['DEFAULT_VALUE'] !== '' && $s['DEFAULT_VALUE'] !== null ? $s['DEFAULT_VALUE'] : null,
            ]) ?: null,
            'string' => array_filter([
                'min_length'    => isset($s['MIN_LENGTH']) && (int)$s['MIN_LENGTH'] > 0 ? (int)$s['MIN_LENGTH'] : null,
                'max_length'    => isset($s['MAX_LENGTH']) && (int)$s['MAX_LENGTH'] > 0 ? (int)$s['MAX_LENGTH'] : null,
                'default_value' => $s['DEFAULT_VALUE'] !== '' && $s['DEFAULT_VALUE'] !== null ? $s['DEFAULT_VALUE'] : null,
            ]) ?: null,
            'file' => array_filter([
                'extensions'      => !empty($s['EXTENSIONS']) ? $s['EXTENSIONS'] : null,
                'max_size_bytes'  => isset($s['MAX_ALLOWED_SIZE']) && (int)$s['MAX_ALLOWED_SIZE'] > 0 ? (int)$s['MAX_ALLOWED_SIZE'] : null,
            ]) ?: null,
            default => null,
        };
    }

    private function getParentRelations(int $entityTypeId): array
    {
        $rm      = Container::getInstance()->getRelationManager();
        $parents = $rm->getParentRelations($entityTypeId);
        $result  = [];

        foreach ($parents as $rel) {
            $pid = $rel->getParentEntityTypeId();
            $result[] = [
                'fieldName'          => 'PARENT_ID_' . $pid,
                'parentEntityTypeId' => $pid,
                'parentEntityType'   => CCrmOwnerType::ResolveName($pid),
            ];
        }

        return $result;
    }
}
