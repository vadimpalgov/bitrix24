<?php

namespace Bit\Mcp\Tools;

use Bitrix\Crm\Model\Dynamic\TypeTable;
use PhpMcp\Server\Attributes\McpTool;

class CrmSmartProcessTool
{
    #[McpTool(
        name: 'crm_get_smart_processes',
        description: 'Получить список всех смарт-процессов (пользовательских типов CRM) в системе Bitrix24.'
    )]
    public function getSmartProcesses(): array
    {
        $result = TypeTable::getList([
            'select' => ['ID', 'NAME', 'TITLE', 'CODE', 'ENTITY_TYPE_ID', 'CREATED_BY', 'CREATED_TIME', 'UPDATED_TIME'],
            'order'  => ['ID' => 'ASC'],
        ]);

        return $result->fetchAll();
    }
}
