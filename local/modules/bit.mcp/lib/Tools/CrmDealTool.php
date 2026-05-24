<?php

namespace Bit\Mcp\Tools;

use Bitrix\Crm\DealTable;
use PhpMcp\Server\Attributes\McpTool;

class CrmDealTool
{
    #[McpTool(
        name: 'crm_get_deals',
        description: 'Получить список сделок CRM Bitrix24. Поддерживает фильтрацию по статусу, ответственному и другим полям.'
    )]
    public function getDeals(
        int $limit = 20,
        string $stageId = '',
        int $responsibleId = 0
    ): array {
        $filter = [];
        if ($stageId !== '') {
            $filter['STAGE_ID'] = $stageId;
        }
        if ($responsibleId > 0) {
            $filter['ASSIGNED_BY_ID'] = $responsibleId;
        }

        $result = DealTable::getList([
            'select' => ['ID', 'TITLE', 'STAGE_ID', 'OPPORTUNITY', 'CURRENCY_ID', 'ASSIGNED_BY_ID', 'DATE_CREATE', 'CLOSEDATE'],
            'filter' => $filter,
            'limit' => $limit,
            'order' => ['DATE_CREATE' => 'DESC'],
        ]);

        return $result->fetchAll();
    }

    #[McpTool(
        name: 'crm_get_deal',
        description: 'Получить подробную информацию о конкретной сделке по её ID, включая пользовательские поля (UF_*).'
    )]
    public function getDeal(int $id): ?array
    {
        global $USER_FIELD_MANAGER;
        $ufFieldNames = array_keys($USER_FIELD_MANAGER->GetUserFields('CRM_DEAL'));

        $result = DealTable::getList([
            'select' => array_merge(['*'], $ufFieldNames),
            'filter' => ['=ID' => $id],
            'limit' => 1,
        ]);

        return $result->fetch() ?: null;
    }
}
