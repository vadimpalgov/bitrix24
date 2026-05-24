<?php

namespace Bit\Mcp\Tools;

use Bitrix\Crm\Service\Container;
use Bitrix\Main\Type\Date;
use Bitrix\Main\Type\DateTime;
use CCrmOwnerType;
use PhpMcp\Server\Attributes\McpTool;

class CrmItemTool
{
    // -------------------------------------------------------------------------
    // READ
    // -------------------------------------------------------------------------

    #[McpTool(
        name: 'crm_get_item',
        description: 'Получить элемент CRM по entityTypeId и ID. Работает для любой сущности: сделки (2), лиды (1), контакты (3), компании (4), смарт-процессы (например RFQ=1044, Part Number=1062). Возвращает все поля включая UF_* и PARENT_ID_*.'
    )]
    public function getItem(int $entityTypeId, int $id): array
    {
        $factory = Container::getInstance()->getFactory($entityTypeId);
        if (!$factory) {
            return ['error' => "Фабрика не найдена для entityTypeId=$entityTypeId"];
        }

        $item = $factory->getItem($id);
        if (!$item) {
            return ['error' => "Элемент #$id не найден в entityTypeId=$entityTypeId"];
        }

        return $this->serializeItem($item);
    }

    // -------------------------------------------------------------------------
    // CREATE
    // -------------------------------------------------------------------------

    #[McpTool(
        name: 'crm_create_item',
        description: 'Создать элемент CRM. entityTypeId: 1=лид, 2=сделка, 3=контакт, 4=компания, смарт-процесс по ENTITY_TYPE_ID. fields — JSON-объект с полями (TITLE, STAGE_ID, UF_*, PARENT_ID_* и др.). Пример fields: {"TITLE":"Тест","STAGE_ID":"NEW","UF_CRM_5_R_QTY":10}'
    )]
    public function createItem(int $entityTypeId, string $fields): array
    {
        $factory = Container::getInstance()->getFactory($entityTypeId);
        if (!$factory) {
            return ['error' => "Фабрика не найдена для entityTypeId=$entityTypeId"];
        }

        $data = json_decode($fields, true);
        if (!is_array($data)) {
            return ['error' => 'Параметр fields должен быть валидным JSON-объектом'];
        }

        $data = $this->injectCurrentUser($data);

        $item = $factory->createItem();
        $item->setFromCompatibleData($data);

        // launch() через операцию фабрики — в отличие от прямого $item->save()
        // прогоняет проверки прав, события (OnBefore/OnAfter...Add) и actions,
        // благодаря чему срабатывает автозаполнение полей (например, TITLE).
        $result = $factory->getAddOperation($item)->launch();

        if (!$result->isSuccess()) {
            return ['error' => implode('; ', $result->getErrorMessages())];
        }

        return [
            'success'      => true,
            'id'           => $item->getId(),
            'entityTypeId' => $entityTypeId,
            'entityType'   => CCrmOwnerType::ResolveName($entityTypeId),
        ];
    }

    // -------------------------------------------------------------------------
    // UPDATE
    // -------------------------------------------------------------------------

    #[McpTool(
        name: 'crm_update_item',
        description: 'Обновить поля элемента CRM по entityTypeId и ID. fields — JSON-объект только с изменяемыми полями. Пример: {"TITLE":"Новое название","UF_CRM_5_R_QTY":20}'
    )]
    public function updateItem(int $entityTypeId, int $id, string $fields): array
    {
        $factory = Container::getInstance()->getFactory($entityTypeId);
        if (!$factory) {
            return ['error' => "Фабрика не найдена для entityTypeId=$entityTypeId"];
        }

        $data = json_decode($fields, true);
        if (!is_array($data)) {
            return ['error' => 'Параметр fields должен быть валидным JSON-объектом'];
        }

        $data = $this->injectCurrentUser($data);

        $item = $factory->getItem($id);
        if (!$item) {
            return ['error' => "Элемент #$id не найден в entityTypeId=$entityTypeId"];
        }

        $item->setFromCompatibleData($data);

        $result = $item->save();

        if (!$result->isSuccess()) {
            return ['error' => implode('; ', $result->getErrorMessages())];
        }

        return [
            'success'      => true,
            'id'           => $id,
            'entityTypeId' => $entityTypeId,
            'entityType'   => CCrmOwnerType::ResolveName($entityTypeId),
        ];
    }

    // -------------------------------------------------------------------------
    // DELETE
    // -------------------------------------------------------------------------

    #[McpTool(
        name: 'crm_delete_item',
        description: 'Удалить элемент CRM по entityTypeId и ID. entityTypeId: 1=лид, 2=сделка, 3=контакт, 4=компания, смарт-процесс по ENTITY_TYPE_ID. Действие необратимо.'
    )]
    public function deleteItem(int $entityTypeId, int $id): array
    {
        $factory = Container::getInstance()->getFactory($entityTypeId);
        if (!$factory) {
            return ['error' => "Фабрика не найдена для entityTypeId=$entityTypeId"];
        }

        $item = $factory->getItem($id);
        if (!$item) {
            return ['error' => "Элемент #$id не найден в entityTypeId=$entityTypeId"];
        }

        $result = $factory->getDeleteOperation($item)->launch();

        if (!$result->isSuccess()) {
            return ['error' => implode('; ', $result->getErrorMessages())];
        }

        return [
            'success'      => true,
            'id'           => $id,
            'entityTypeId' => $entityTypeId,
            'entityType'   => CCrmOwnerType::ResolveName($entityTypeId),
            'deleted'      => true,
        ];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Подставляет CREATED_BY_ID и MODIFY_BY_ID из текущего пользователя Bitrix,
     * если они не заданы явно в $data (setFromCompatibleData требует ORM-нотацию).
     */
    private function injectCurrentUser(array $data): array
    {
        global $USER;
        $userId = ($USER instanceof \CUser && $USER->IsAuthorized()) ? (int)$USER->GetID() : 1;

        $data['CREATED_BY_ID'] ??= $userId;
        $data['MODIFY_BY_ID']  ??= $userId;

        return $data;
    }

    private function serializeItem(\Bitrix\Crm\Item $item): array
    {
        $data   = $item->getData();
        $result = [];

        foreach ($data as $key => $value) {
            $result[$key] = $this->serializeValue($value);
        }

        return $result;
    }

    private function serializeValue(mixed $value): mixed
    {
        if ($value instanceof DateTime || $value instanceof Date) {
            return $value->format('Y-m-d H:i:s');
        }
        if (is_array($value)) {
            return array_map(fn($v) => $this->serializeValue($v), $value);
        }
        if (is_object($value)) {
            // Пытаемся привести к строке; если не получается — возвращаем null
            return method_exists($value, '__toString') ? (string)$value : null;
        }
        return $value;
    }
}
