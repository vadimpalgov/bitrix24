<?php

namespace Bit\Mcp\Tools;

use Bitrix\Iblock\ElementTable;
use Bitrix\Iblock\SectionTable;
use Bitrix\Main\Loader;
use CIBlockElement;
use CIBlockSection;
use PhpMcp\Server\Attributes\McpTool;

class IblockTool
{
    // -------------------------------------------------------------------------
    // ELEMENTS — READ
    // -------------------------------------------------------------------------

    #[McpTool(
        name: 'iblock_get_elements',
        description: 'Получить элементы инфоблока по его ID. Используется для расшифровки значений полей типа iblock_element / iblock_element_dp — чтобы понять, что стоит за числовым ID значения.'
    )]
    public function getElements(
        int    $iblockId,
        int    $limit = 50,
        string $search = '',
        int    $sectionId = 0
    ): array {
        Loader::includeModule('iblock');

        $filter = ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y'];
        if ($search !== '') {
            $filter['%NAME'] = $search;
        }
        if ($sectionId > 0) {
            $filter['SECTION_ID'] = $sectionId;
        }

        $result = ElementTable::getList([
            'select' => ['ID', 'NAME', 'XML_ID', 'CODE', 'SORT'],
            'filter' => $filter,
            'order'  => ['SORT' => 'ASC', 'NAME' => 'ASC'],
            'limit'  => $limit,
        ]);

        return $result->fetchAll();
    }

    // -------------------------------------------------------------------------
    // ELEMENTS — CREATE / UPDATE / DELETE
    // -------------------------------------------------------------------------

    #[McpTool(
        name: 'iblock_create_element',
        description: 'Создать элемент инфоблока. iblockId — ID инфоблока. fields — JSON-объект с полями. Стандартные поля: NAME (обязательно), ACTIVE (Y/N), SORT, CODE, XML_ID, IBLOCK_SECTION_ID, PREVIEW_TEXT, DETAIL_TEXT. Свойства передаются в ключе PROPERTY_VALUES: {"PROPERTY_VALUES":{"PROP_CODE":"значение"}}. Пример fields: {"NAME":"Новый элемент","ACTIVE":"Y","SORT":100,"PROPERTY_VALUES":{"PRICE":1500,"COLOR":"Красный"}}'
    )]
    public function createElement(int $iblockId, string $fields): array
    {
        Loader::includeModule('iblock');

        $data = json_decode($fields, true);
        if (!is_array($data)) {
            return ['error' => 'Параметр fields должен быть валидным JSON-объектом'];
        }

        $data['IBLOCK_ID'] = $iblockId;
        $data['ACTIVE']  ??= 'Y';

        $el = new CIBlockElement();
        $id = $el->Add($data);

        if (!$id) {
            return ['error' => $el->LAST_ERROR ?: 'Неизвестная ошибка при создании элемента'];
        }

        return ['success' => true, 'id' => (int)$id, 'iblockId' => $iblockId];
    }

    #[McpTool(
        name: 'iblock_update_element',
        description: 'Обновить элемент инфоблока по ID. fields — JSON-объект только с изменяемыми полями. Стандартные поля: NAME, ACTIVE, SORT, CODE, PREVIEW_TEXT, DETAIL_TEXT, IBLOCK_SECTION_ID. Свойства передаются в ключе PROPERTY_VALUES. Пример fields: {"NAME":"Новое имя","ACTIVE":"Y","PROPERTY_VALUES":{"PRICE":2000}}'
    )]
    public function updateElement(int $id, string $fields): array
    {
        Loader::includeModule('iblock');

        $data = json_decode($fields, true);
        if (!is_array($data)) {
            return ['error' => 'Параметр fields должен быть валидным JSON-объектом'];
        }

        $result = CIBlockElement::Update($id, $data);

        if (!$result) {
            global $APPLICATION;
            $error = $APPLICATION->GetException();
            return ['error' => ($error ? $error->GetString() : 'Неизвестная ошибка при обновлении элемента')];
        }

        return ['success' => true, 'id' => $id];
    }

    #[McpTool(
        name: 'iblock_delete_element',
        description: 'Удалить элемент инфоблока по ID. Действие необратимо.'
    )]
    public function deleteElement(int $id): array
    {
        Loader::includeModule('iblock');

        $result = CIBlockElement::Delete($id);

        if (!$result) {
            global $APPLICATION;
            $error = $APPLICATION->GetException();
            return ['error' => ($error ? $error->GetString() : 'Неизвестная ошибка при удалении элемента')];
        }

        return ['success' => true, 'id' => $id, 'deleted' => true];
    }

    // -------------------------------------------------------------------------
    // SECTIONS — READ
    // -------------------------------------------------------------------------

    #[McpTool(
        name: 'iblock_get_sections',
        description: 'Получить разделы инфоблока по его ID. parentId — ID родительского раздела (0 = корневые разделы). search — поиск по названию.'
    )]
    public function getSections(
        int    $iblockId,
        int    $limit = 50,
        string $search = '',
        int    $parentId = 0
    ): array {
        Loader::includeModule('iblock');

        $filter = ['=IBLOCK_ID' => $iblockId];
        if ($search !== '') {
            $filter['%NAME'] = $search;
        }
        if ($parentId > 0) {
            $filter['=IBLOCK_SECTION_ID'] = $parentId;
        } elseif ($parentId === 0) {
            $filter['=IBLOCK_SECTION_ID'] = false; // корневые разделы
        }

        $result = SectionTable::getList([
            'select' => ['ID', 'NAME', 'CODE', 'XML_ID', 'SORT', 'IBLOCK_SECTION_ID', 'DEPTH_LEVEL', 'LEFT_MARGIN', 'RIGHT_MARGIN'],
            'filter' => $filter,
            'order'  => ['LEFT_MARGIN' => 'ASC'],
            'limit'  => $limit,
        ]);

        return $result->fetchAll();
    }

    // -------------------------------------------------------------------------
    // SECTIONS — CREATE / UPDATE / DELETE
    // -------------------------------------------------------------------------

    #[McpTool(
        name: 'iblock_create_section',
        description: 'Создать раздел инфоблока. iblockId — ID инфоблока. fields — JSON-объект с полями. Поля: NAME (обязательно), ACTIVE (Y/N), SORT, CODE, XML_ID, IBLOCK_SECTION_ID (ID родительского раздела, 0 = корень), DESCRIPTION. Пример fields: {"NAME":"Новый раздел","ACTIVE":"Y","SORT":100,"IBLOCK_SECTION_ID":0}'
    )]
    public function createSection(int $iblockId, string $fields): array
    {
        Loader::includeModule('iblock');

        $data = json_decode($fields, true);
        if (!is_array($data)) {
            return ['error' => 'Параметр fields должен быть валидным JSON-объектом'];
        }

        $data['IBLOCK_ID'] = $iblockId;
        $data['ACTIVE']  ??= 'Y';

        $sect = new CIBlockSection();
        $id   = $sect->Add($data);

        if (!$id) {
            return ['error' => $sect->LAST_ERROR ?: 'Неизвестная ошибка при создании раздела'];
        }

        return ['success' => true, 'id' => (int)$id, 'iblockId' => $iblockId];
    }

    #[McpTool(
        name: 'iblock_update_section',
        description: 'Обновить раздел инфоблока по ID. fields — JSON-объект только с изменяемыми полями. Поля: NAME, ACTIVE, SORT, CODE, XML_ID, IBLOCK_SECTION_ID (перенос в другой родительский раздел), DESCRIPTION. Пример fields: {"NAME":"Переименованный раздел","SORT":200}'
    )]
    public function updateSection(int $id, string $fields): array
    {
        Loader::includeModule('iblock');

        $data = json_decode($fields, true);
        if (!is_array($data)) {
            return ['error' => 'Параметр fields должен быть валидным JSON-объектом'];
        }

        $result = CIBlockSection::Update($id, $data);

        if (!$result) {
            global $APPLICATION;
            $error = $APPLICATION->GetException();
            return ['error' => ($error ? $error->GetString() : 'Неизвестная ошибка при обновлении раздела')];
        }

        return ['success' => true, 'id' => $id];
    }

    #[McpTool(
        name: 'iblock_delete_section',
        description: 'Удалить раздел инфоблока по ID. Вместе с разделом удаляются все его подразделы и элементы. Действие необратимо.'
    )]
    public function deleteSection(int $id): array
    {
        Loader::includeModule('iblock');

        $result = CIBlockSection::Delete($id);

        if (!$result) {
            global $APPLICATION;
            $error = $APPLICATION->GetException();
            return ['error' => ($error ? $error->GetString() : 'Неизвестная ошибка при удалении раздела')];
        }

        return ['success' => true, 'id' => $id, 'deleted' => true];
    }
}
