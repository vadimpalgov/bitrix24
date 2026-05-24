<?php

namespace Parfeon\Er\Services;


use Bitrix\Crm\Item;
use Parfeon\Er\Container;
use Parfeon\Er\Factory\ApproversFactory;
use Parfeon\Er\Mapping\EmployeeRequest;
use Bitrix\Main\DI\ServiceLocator;
use Recurr\Exception;

class CreateApprovers
{
    private ?ApproversFactory $approversFactory;



    public function __construct()
    {
        $container = Container::getInstance();

        $this->approversFactory = $container->getFactory(
            $container->getEntityTypeIdByCode('AP_SMART_PROCESS_ID')
        );
    }

    public function create(Item $employeeItem): void
    {
        if (!$this->approversFactory) {
            return;
        }

        $userIds = $this->getUserIds($employeeItem);

        if (empty($userIds)) {
            return;
        }
        ray('$userIds', $userIds);
        foreach ($userIds as $userId) {
            $this->createApproverItem($employeeItem, $userId);
        }
    }

    private function getUserIds(Item $employeeItem): array
    {
        $userIds = [];

        // 1. Руководители проекта
        $projectManagers = $employeeItem->get(EmployeeRequest::PROJECT_MANAGERS);
        if (is_array($projectManagers)) {
            foreach ($projectManagers as $userId) {
                if ((int)$userId > 0) {
                    $userIds[] = (int)$userId;
                }
            }
        }

        // 2. Руководители подразделений
        $headOfDepartments = $employeeItem->get(EmployeeRequest::HEAD_OF_DEPARTMENT);
        if (is_array($headOfDepartments)) {
            foreach ($headOfDepartments as $headOfDepartment) {
                if ((int)$headOfDepartment > 0) {
                    $userIds[] = (int)$headOfDepartment;
                }
            }
        }
        ray('$headOfDepartments', $headOfDepartments);
        if ($headOfDepartment) {
            $userIds[] = $headOfDepartment;
        }


        // 3. HR-менеджеры
        $hrManagers = $employeeItem->get(EmployeeRequest::HR_MANAGERS);
        if (is_array($hrManagers)) {
            foreach ($hrManagers as $userId) {
                if ((int)$userId > 0) {
                    $userIds[] = (int)$userId;
                }
            }
        }

        return array_values(array_unique($userIds));
    }

    private function createApproverItem(Item $employeeItem, int $userId): void
    {
        if ($this->approverExists($employeeItem, $userId)) {
            return;
        }

        $item = $this->approversFactory->createItem();

        $item->setAssignedById($userId);

        $item->set(
            'PARENT_ID_' . $employeeItem->getEntityTypeId(),
            $employeeItem->getId()
        );

        $this->mappingData($employeeItem, $item);

        $item->setAssignedById($userId);

        $operation = $this->approversFactory->getAddOperation($item);
        $createResult = $operation->disableCheckAccess()->launch();

        if(!$createResult->isSuccess()) {
            ray($createResult->getErrorMessages());
        }

    }

    private function approverExists(Item $employeeItem, int $userId): bool
    {
        $items = $this->approversFactory->getItems([
            'filter' => [
                '=ASSIGNED_BY_ID' => $userId,
                '=PARENT_ID_' . $employeeItem->getEntityTypeId() => $employeeItem->getId(),
            ],
            'select' => ['ID'],
            'limit' => 1,
        ]);

        return !empty($items);
    }

    private function mappingData(Item $employeeItem, Item $approveItem)
    {
        foreach (EmployeeRequest::MAPPING as $employeeField => $approveField) {
            if($employeeItem->hasField($employeeField) && $approveItem->hasField($approveField) && $value = $employeeItem->get($employeeField)) {
                $approveItem->set($approveField, $value);
            }
        }
    }
}