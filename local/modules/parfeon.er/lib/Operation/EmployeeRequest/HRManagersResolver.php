<?php
namespace Parfeon\Er\Operation\EmployeeRequest;

use Bitrix\Crm\Item;
use Bitrix\Crm\Service\Operation\Action;
use Bitrix\Main\Loader;
use Bitrix\Main\Result;
use CUser;
use CIBlockSection;
use Parfeon\Er\Mapping\EmployeeRequest;

class HRManagersResolver extends Action
{
    private const HR_DEPARTMENT_NAME = 'HR';

    public function process(Item $item): Result
    {
        $result = new Result();

        if (!Loader::includeModule('intranet')) {
            return $result;
        }

        $hrDepartmentIds = $this->getHrDepartmentIdsByName();

        if (empty($hrDepartmentIds)) {
            return $result;
        }

        $hrUserIds = $this->getUsersByDepartments($hrDepartmentIds);

        if (empty($hrUserIds)) {
            return $result;
        }
        $item->set(EmployeeRequest::HR_MANAGERS, $hrUserIds);

        return $result;
    }

    /**
     * Получаем ID подразделений с именем "Отдел кадров"
     */
    private function getHrDepartmentIdsByName(): array
    {
        $ids = [];

        $res = CIBlockSection::GetList(
            [],
            [
                '=NAME' => self::HR_DEPARTMENT_NAME,
                'ACTIVE' => 'Y',
            ],
            false,
            ['ID']
        );

        while ($section = $res->Fetch()) {
            $ids[] = (int)$section['ID'];
        }

        return $ids;
    }

    /**
     * Получаем пользователей по подразделениям
     */
    private function getUsersByDepartments(array $departmentIds): array
    {
        $userIds = [];

        $rsUsers = CUser::GetList(
            'ID',
            'ASC',
            [
                'ACTIVE' => 'Y',
                'UF_DEPARTMENT' => $departmentIds,
            ],
            ['FIELDS' => ['ID']]
        );

        while ($user = $rsUsers->Fetch()) {
            $userIds[] = (int)$user['ID'];
        }

        return array_values(array_unique($userIds));
    }
}