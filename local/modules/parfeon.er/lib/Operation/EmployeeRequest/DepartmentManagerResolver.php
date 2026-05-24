<?php

namespace Parfeon\Er\Operation\EmployeeRequest;

use Bitrix\Crm\Item;
use Bitrix\Crm\Service\Operation\Action;
use Bitrix\HumanResources\Compatibility\Utils\DepartmentBackwardAccessCode;
use Bitrix\HumanResources\Service\Container;
use Bitrix\Main\Loader;
use Bitrix\Main\Result;
use CUser;
use Parfeon\Er\Mapping\EmployeeRequest;

class DepartmentManagerResolver extends Action
{
    public function process(Item $item): Result
    {
        $result = new Result();

        if (!Loader::includeModule('intranet') || !Loader::includeModule('humanresources')) {
            return $result;
        }

        $creatorId = (int)$item->getCreatedBy();
        if ($creatorId <= 0) {
            return $result;
        }

        $user = CUser::GetByID($creatorId)->Fetch();
        if (!$user || empty($user['UF_DEPARTMENT'])) {
            return $result;
        }

        $departmentId = (int)$user['UF_DEPARTMENT'][0];

        $nodeRepository = Container::getNodeRepository();
        $nodeMemberService = Container::getNodeMemberService();

        $currentNode = $nodeRepository->getByAccessCode(
            DepartmentBackwardAccessCode::makeById($departmentId)
        );

        ray('$departmentId', $departmentId);

        $allManagers = [];

        while ($currentNode !== null) {
            $headEmployees = $nodeMemberService->getDefaultHeadRoleEmployees($currentNode->id);
            $entityIds = $headEmployees->getEntityIds();

            if (!empty($entityIds)) {

                foreach ($entityIds as $managerId) {
                    if (!in_array($managerId, $allManagers)) {
                        $allManagers[] = (int)$managerId;
                    }
                }
            }

            if ($currentNode->parentId > 0) {
                $currentNode = $nodeRepository->getById($currentNode->parentId);
            } else {
                $currentNode = null;
            }
        }

        if (!empty($allManagers)) {
            $item->set(EmployeeRequest::HEAD_OF_DEPARTMENT, $allManagers);
        }

        return $result;
    }
}