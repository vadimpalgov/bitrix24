<?php

namespace Bit\Mcp\Tools;

use Bitrix\Crm\ContactTable;
use PhpMcp\Server\Attributes\McpTool;

class CrmContactTool
{
    #[McpTool(
        name: 'crm_get_contacts',
        description: 'Получить список контактов CRM Bitrix24 с возможностью поиска по имени или email.'
    )]
    public function getContacts(
        int $limit = 20,
        string $search = ''
    ): array {
        $filter = [];
        if ($search !== '') {
            $filter['%NAME'] = $search;
        }

        $result = ContactTable::getList([
            'select' => ['ID', 'NAME', 'LAST_NAME', 'SECOND_NAME', 'EMAIL', 'PHONE', 'ASSIGNED_BY_ID', 'DATE_CREATE'],
            'filter' => $filter,
            'limit' => $limit,
            'order' => ['DATE_CREATE' => 'DESC'],
        ]);

        return $result->fetchAll();
    }

    #[McpTool(
        name: 'crm_get_contact',
        description: 'Получить подробную информацию о контакте по его ID.'
    )]
    public function getContact(int $id): ?array
    {
        $result = ContactTable::getById($id);
        return $result->fetch() ?: null;
    }
}
