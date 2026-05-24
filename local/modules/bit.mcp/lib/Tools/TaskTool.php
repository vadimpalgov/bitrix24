<?php

namespace Bit\Mcp\Tools;

use Bitrix\Tasks\Internals\Task\Item as TaskItem;
use PhpMcp\Server\Attributes\McpTool;
use Tasker\Task\Item;

class TaskTool
{
    #[McpTool(
        name: 'tasks_get_list',
        description: 'Получить список задач Bitrix24. Можно фильтровать по ответственному, статусу и группе.'
    )]
    public function getList(
        int $responsibleId = 0,
        int $groupId = 0,
        int $status = 0,
        int $limit = 20
    ): array {
        $filter = [];
        if ($responsibleId > 0) {
            $filter['RESPONSIBLE_ID'] = $responsibleId;
        }
        if ($groupId > 0) {
            $filter['GROUP_ID'] = $groupId;
        }
        if ($status > 0) {
            $filter['STATUS'] = $status;
        }

        $res = \CTasks::GetList(
            ['DEADLINE' => 'ASC'],
            $filter,
            ['ID', 'TITLE', 'STATUS', 'RESPONSIBLE_ID', 'DEADLINE', 'CREATED_DATE', 'GROUP_ID'],
            ['nPageSize' => $limit]
        );

        $tasks = [];
        while ($task = $res->GetNext()) {
            $tasks[] = $task;
        }

        return $tasks;
    }
}
