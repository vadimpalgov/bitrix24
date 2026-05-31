<?php

namespace Parfeon\Er\Operation\EmployeeRequest;

use Bitrix\Crm\Item;
use Bitrix\Crm\Service\Operation\Action;
use Bitrix\HumanResources\Compatibility\Utils\DepartmentBackwardAccessCode;
use Bitrix\HumanResources\Service\Container;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Result;
use CUser;
use Parfeon\Er\Mapping\EmployeeRequest;

class DepartmentManagerResolver extends Action
{
    private const MODULE_ID = 'parfeon.er';

    public function process(Item $item): Result
    {
        $result = new Result();

        if (!Loader::includeModule('intranet') || !Loader::includeModule('humanresources')) {
            return $result;
        }

        // Используем ASSIGNED_BY_ID — ответственный (заявитель), а не технический создатель
        $creatorId = (int)$item->getAssignedById();
        if ($creatorId <= 0) {
            $creatorId = (int)$item->getCreatedBy();
        }
        if ($creatorId <= 0) {
            return $result;
        }

        $user = CUser::GetByID($creatorId)->Fetch();
        if (!$user || empty($user['UF_DEPARTMENT'])) {
            return $result;
        }

        $departmentId = (int)$user['UF_DEPARTMENT'][0];

        $managers = $this->collectManagers($departmentId);

        if (empty($managers)) {
            return $result;
        }

        $managers = $this->applyDirectorSettings($managers, $creatorId);

        if (!empty($managers)) {
            $item->set(EmployeeRequest::HEAD_OF_DEPARTMENT, $managers);
        }

        return $result;
    }

    /**
     * Собирает руководителей снизу вверх по иерархии отделов.
     * Порядок: прямой начальник (index 0) → ... → директор (последний).
     */
    private function collectManagers(int $departmentId): array
    {
        $nodeRepository    = Container::getNodeRepository();
        $nodeMemberService = Container::getNodeMemberService();

        $addAllManagers = Option::get(self::MODULE_ID, 'LA_ADD_ALL_MANAGERS', 'N') === 'Y';

        $currentNode = $nodeRepository->getByAccessCode(
            DepartmentBackwardAccessCode::makeById($departmentId)
        );

        $managers = [];

        while ($currentNode !== null) {
            $headEmployees = $nodeMemberService->getDefaultHeadRoleEmployees($currentNode->id);
            $entityIds     = $headEmployees->getEntityIds();

            foreach ($entityIds as $managerId) {
                $managerId = (int)$managerId;
                if ($managerId > 0 && !in_array($managerId, $managers, true)) {
                    $managers[] = $managerId;
                }
            }

            // Если LA_ADD_ALL_MANAGERS выключен — берём только прямого начальника
            if (!$addAllManagers) {
                break;
            }

            $currentNode = $currentNode->parentId > 0
                ? $nodeRepository->getById($currentNode->parentId)
                : null;
        }

        return $managers;
    }

    /**
     * Применяет настройки директора к итоговому списку.
     *
     * LA_EXCLUDE_DIRECTOR = Y           → убирает директора (последний в списке)
     * LA_FORCE_DIRECTOR_IF_MANAGER = Y  → возвращает директора, если заявитель сам руководитель
     */
    private function applyDirectorSettings(array $managers, int $creatorId): array
    {
        $excludeDirector        = Option::get(self::MODULE_ID, 'LA_EXCLUDE_DIRECTOR', 'N') === 'Y';
        $forceDirectorIfManager = Option::get(self::MODULE_ID, 'LA_FORCE_DIRECTOR_IF_MANAGER', 'N') === 'Y';

        if (!$excludeDirector) {
            return $managers;
        }

        // Снимаем директора
        $director = array_pop($managers);

        // Возвращаем, если заявитель сам руководитель
        if ($forceDirectorIfManager && $director !== null && $this->isManager($creatorId)) {
            $managers[] = $director;
        }

        return $managers;
    }

    /**
     * Проверяет, является ли пользователь руководителем хотя бы одного отдела.
     */
    private function isManager(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $user = CUser::GetByID($userId)->Fetch();
        if (!$user || empty($user['UF_DEPARTMENT'])) {
            return false;
        }

        $nodeRepository    = Container::getNodeRepository();
        $nodeMemberService = Container::getNodeMemberService();

        foreach ((array)$user['UF_DEPARTMENT'] as $deptId) {
            $node = $nodeRepository->getByAccessCode(
                DepartmentBackwardAccessCode::makeById((int)$deptId)
            );
            if (!$node) {
                continue;
            }

            $heads = $nodeMemberService->getDefaultHeadRoleEmployees($node->id);
            if (in_array($userId, $heads->getEntityIds(), false)) {
                return true;
            }
        }

        return false;
    }
}
