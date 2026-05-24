<?php

namespace Bit\Mcp\Tools;

use Bitrix\Iblock\PropertyTable;
use Bitrix\Main\Loader;
use CIBlockProperty;
use PhpMcp\Server\Attributes\McpTool;

class IblockPropertyTool
{
    // -------------------------------------------------------------------------
    // READ
    // -------------------------------------------------------------------------

    #[McpTool(
        name: 'iblock_get_properties',
        description: 'Получить список свойств инфоблока по его ID. Возвращает код, название, тип, обязательность, множественность и перечисления (для типа L).'
    )]
    public function getProperties(int $iblockId, bool $withEnumValues = true): array
    {
        Loader::includeModule('iblock');

        $result = PropertyTable::getList([
            'select' => [
                'ID', 'NAME', 'CODE', 'SORT', 'ACTIVE',
                'PROPERTY_TYPE', 'USER_TYPE', 'USER_TYPE_SETTINGS',
                'MULTIPLE', 'IS_REQUIRED', 'DEFAULT_VALUE',
                'LINK_IBLOCK_ID', 'ROW_COUNT', 'COL_COUNT', 'FILE_TYPE',
            ],
            'filter' => ['=IBLOCK_ID' => $iblockId],
            'order'  => ['SORT' => 'ASC', 'NAME' => 'ASC'],
        ]);

        $properties = $result->fetchAll();

        if ($withEnumValues) {
            foreach ($properties as &$prop) {
                if ($prop['PROPERTY_TYPE'] === 'L') {
                    $prop['ENUM_VALUES'] = $this->getEnumValues((int)$prop['ID']);
                }
            }
            unset($prop);
        }

        return $properties;
    }

    #[McpTool(
        name: 'iblock_get_property',
        description: 'Получить одно свойство инфоблока по ID свойства. Для типа L (список) включает все возможные значения перечисления.'
    )]
    public function getProperty(int $id): array
    {
        Loader::includeModule('iblock');

        $result = PropertyTable::getList([
            'select' => [
                'ID', 'IBLOCK_ID', 'NAME', 'CODE', 'SORT', 'ACTIVE',
                'PROPERTY_TYPE', 'USER_TYPE', 'USER_TYPE_SETTINGS',
                'MULTIPLE', 'IS_REQUIRED', 'DEFAULT_VALUE',
                'LINK_IBLOCK_ID', 'ROW_COUNT', 'COL_COUNT', 'FILE_TYPE',
            ],
            'filter' => ['=ID' => $id],
            'limit'  => 1,
        ]);

        $prop = $result->fetch();
        if (!$prop) {
            return ['error' => "Свойство #$id не найдено"];
        }

        if ($prop['PROPERTY_TYPE'] === 'L') {
            $prop['ENUM_VALUES'] = $this->getEnumValues($id);
        }

        return $prop;
    }

    // -------------------------------------------------------------------------
    // CREATE
    // -------------------------------------------------------------------------

    #[McpTool(
        name: 'iblock_create_property',
        description: <<<'DESC'
Создать свойство инфоблока. iblockId — ID инфоблока. fields — JSON-объект.
Обязательные поля: NAME, CODE, PROPERTY_TYPE.
Типы свойств (PROPERTY_TYPE):
  S — строка (USER_TYPE: "HTML" для html/текст, "DateTime"/"Date" для дат)
  N — число
  L — список (передать VALUES: [{"VALUE":"Красный","XML_ID":"red","SORT":10}, ...])
  F — файл
  G — привязка к разделу (LINK_IBLOCK_ID — ID инфоблока)
  E — привязка к элементу (LINK_IBLOCK_ID — ID инфоблока)
Необязательные: MULTIPLE (Y/N), IS_REQUIRED (Y/N), SORT, DEFAULT_VALUE, ACTIVE (Y/N), FILE_TYPE, ROW_COUNT, COL_COUNT.
Пример (строка): {"NAME":"Цвет","CODE":"COLOR","PROPERTY_TYPE":"S","MULTIPLE":"N","SORT":100}
Пример (список): {"NAME":"Статус","CODE":"STATUS","PROPERTY_TYPE":"L","VALUES":[{"VALUE":"Новый","XML_ID":"new","SORT":10},{"VALUE":"Архив","XML_ID":"archive","SORT":20}]}
DESC
    )]
    public function createProperty(int $iblockId, string $fields): array
    {
        Loader::includeModule('iblock');

        $data = json_decode($fields, true);
        if (!is_array($data)) {
            return ['error' => 'Параметр fields должен быть валидным JSON-объектом'];
        }

        $data['IBLOCK_ID'] = $iblockId;
        $data['ACTIVE']   ??= 'Y';
        $data['MULTIPLE'] ??= 'N';

        $prop = new CIBlockProperty();
        $id   = $prop->Add($data);

        if (!$id) {
            return ['error' => $prop->LAST_ERROR ?: 'Неизвестная ошибка при создании свойства'];
        }

        return ['success' => true, 'id' => (int)$id, 'iblockId' => $iblockId];
    }

    // -------------------------------------------------------------------------
    // UPDATE
    // -------------------------------------------------------------------------

    #[McpTool(
        name: 'iblock_update_property',
        description: <<<'DESC'
Обновить свойство инфоблока по ID. fields — JSON-объект только с изменяемыми полями.
Изменяемые поля: NAME, SORT, ACTIVE, MULTIPLE, IS_REQUIRED, DEFAULT_VALUE, FILE_TYPE, ROW_COUNT, COL_COUNT, LINK_IBLOCK_ID.
Для обновления значений списка (PROPERTY_TYPE=L) передать VALUES — полный список значений с ID существующих:
  [{"ID":5,"VALUE":"Красный","XML_ID":"red","SORT":10}, {"VALUE":"Синий","XML_ID":"blue","SORT":20}]
  (элементы без ID будут созданы, существующие ID — обновлены; не указанные ID — удалены)
Пример: {"NAME":"Новое название","SORT":200,"IS_REQUIRED":"Y"}
DESC
    )]
    public function updateProperty(int $id, string $fields): array
    {
        Loader::includeModule('iblock');

        $data = json_decode($fields, true);
        if (!is_array($data)) {
            return ['error' => 'Параметр fields должен быть валидным JSON-объектом'];
        }

        $prop   = new CIBlockProperty();
        $result = $prop->Update($id, $data);

        if (!$result) {
            return ['error' => $prop->LAST_ERROR ?: 'Неизвестная ошибка при обновлении свойства'];
        }

        return ['success' => true, 'id' => $id];
    }

    // -------------------------------------------------------------------------
    // DELETE
    // -------------------------------------------------------------------------

    #[McpTool(
        name: 'iblock_delete_property',
        description: 'Удалить свойство инфоблока по ID. Вместе со свойством удаляются все его значения у элементов. Действие необратимо.'
    )]
    public function deleteProperty(int $id): array
    {
        Loader::includeModule('iblock');

        $result = CIBlockProperty::Delete($id);

        if (!$result) {
            global $APPLICATION;
            $error = $APPLICATION->GetException();
            return ['error' => ($error ? $error->GetString() : 'Неизвестная ошибка при удалении свойства')];
        }

        return ['success' => true, 'id' => $id, 'deleted' => true];
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    private function getEnumValues(int $propertyId): array
    {
        $dbEnum = \CIBlockPropertyEnum::GetList(
            ['SORT' => 'ASC', 'VALUE' => 'ASC'],
            ['PROPERTY_ID' => $propertyId]
        );

        $values = [];
        while ($row = $dbEnum->Fetch()) {
            $values[] = [
                'ID'    => $row['ID'],
                'VALUE' => $row['VALUE'],
                'XML_ID' => $row['XML_ID'],
                'SORT'  => $row['SORT'],
                'DEF'   => $row['DEF'],
            ];
        }

        return $values;
    }
}
