<?php

namespace Parfeon\Er\Services;

use Bitrix\Crm\Item;
use Bitrix\Main\Config\Option;
use CUser;
use Parfeon\Er\Container;
use Parfeon\Er\Factory\ApproversFactory;
use Parfeon\Er\Mapping\ApprovalProfile;
use Parfeon\Er\Mapping\Approvers;
use Parfeon\Er\Mapping\EmployeeRequest;

class CreateApprovers
{
    private const MODULE_ID = 'parfeon.er';

    private ?ApproversFactory $approversFactory;
    private Container $container;

    public function __construct()
    {
        $this->container = Container::getInstance();

        $this->approversFactory = $this->container->getFactory(
            $this->container->getEntityTypeIdByCode('AP_SMART_PROCESS_ID')
        );
    }

    /**
     * Создаёт AP-элементы для указанной фазы.
     *
     * Фаза 1 (HR):           все HR-менеджеры, параллельно
     * Фаза 2 (РП):           все руководители проекта, параллельно; если пусто — сразу фаза 3
     * Фаза 3 (Руководители): один согласующий по порядку ORDER;
     *                        источник — профиль (ALP) или иерархия (HEAD_OF_DEPARTMENT)
     *
     * @param int  $phase    Номер фазы (1, 2, 3)
     * @param Item $erItem   Элемент заявки (ER)
     * @param int  $order    Порядковый номер в фазе 3 (начиная с 1)
     */
    public function createPhase(int $phase, Item $erItem, int $order = 1): void
    {
        if (!$this->approversFactory) {
            return;
        }

        switch ($phase) {
            case 1:
                $this->createPhaseHr($erItem);
                break;
            case 2:
                $this->createPhaseManagers($erItem);
                break;
            case 3:
                $this->createPhaseHierarchy($erItem, $order);
                break;
        }
    }

    // -------------------------------------------------------------------------
    // Фаза 1 — HR
    // -------------------------------------------------------------------------

    private function createPhaseHr(Item $erItem): void
    {
        $userIds = $erItem->get(EmployeeRequest::HR_MANAGERS);

        if (empty($userIds) || !is_array($userIds)) {
            // HR не найдены — пропускаем фазу 1, сразу запускаем фазу 2
            $this->createPhase(2, $erItem);
            return;
        }

        foreach ($userIds as $userId) {
            if ((int)$userId > 0) {
                $this->createApproverItem($erItem, (int)$userId, 1, 0);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Фаза 2 — Руководители проекта (РП)
    // -------------------------------------------------------------------------

    private function createPhaseManagers(Item $erItem): void
    {
        $userIds = $erItem->get(EmployeeRequest::PROJECT_MANAGERS);

        if (empty($userIds) || !is_array($userIds)) {
            // РП не назначены — пропускаем фазу 2, сразу запускаем фазу 3
            $this->createPhase(3, $erItem, 1);
            return;
        }

        foreach ($userIds as $userId) {
            if ((int)$userId > 0) {
                $this->createApproverItem($erItem, (int)$userId, 2, 0);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Фаза 3 — Руководители (последовательно)
    // -------------------------------------------------------------------------

    private function createPhaseHierarchy(Item $erItem, int $order): void
    {
        $creatorId = (int)$erItem->getAssignedById() ?: (int)$erItem->getCreatedBy();

        // Определяем источник: профиль или иерархия
        $profileId = $this->getApprovalProfileId($creatorId);

        if ($profileId > 0) {
            $this->createFromProfile($erItem, $profileId, $order);
        } else {
            $this->createFromHierarchy($erItem, $order);
        }
    }

    /**
     * Создаёт AP из профиля (ALP-дети ProfileParticipants) по ORDER.
     */
    private function createFromProfile(Item $erItem, int $profileId, int $order): void
    {
        $alpEntityTypeId = $this->container->getEntityTypeIdByCode('ALP_SMART_PROCESS_ID');
        if (!$alpEntityTypeId) {
            return;
        }

        $alpFactory = $this->container->getFactory($alpEntityTypeId);
        if (!$alpFactory) {
            return;
        }

        $ppEntityTypeId = $this->container->getEntityTypeIdByCode('PP_SMART_PROCESS_ID');
        if (!$ppEntityTypeId) {
            return;
        }

        $steps = $alpFactory->getItems([
            'filter' => [
                '=PARENT_ID_' . $ppEntityTypeId => $profileId,
                '=' . ApprovalProfile::ORDER     => $order,
            ],
            'select' => ['ID', ApprovalProfile::USER, ApprovalProfile::ORDER, ApprovalProfile::APPROVAL_STAGE],
            'limit'  => 1,
        ]);

        if (empty($steps)) {
            return;
        }

        $step   = reset($steps);
        $userId = (int)$step->get(ApprovalProfile::USER);

        if ($userId > 0) {
            $this->createApproverItem($erItem, $userId, 3, $order);
        }
    }

    /**
     * Создаёт AP из поля HEAD_OF_DEPARTMENT по индексу (order - 1).
     * Применяет настройки директора.
     */
    private function createFromHierarchy(Item $erItem, int $order): void
    {
        $managers = $this->getOrderedManagers($erItem);

        if (empty($managers)) {
            return;
        }

        $index = $order - 1;

        if (!isset($managers[$index])) {
            return;
        }

        $userId = (int)$managers[$index];

        if ($userId > 0) {
            $this->createApproverItem($erItem, $userId, 3, $order);
        }
    }

    /**
     * Возвращает список руководителей из HEAD_OF_DEPARTMENT
     * с учётом настроек LA_EXCLUDE_DIRECTOR и LA_FORCE_DIRECTOR_IF_MANAGER.
     *
     * Порядок: прямой начальник (index 0) → ... → директор (последний).
     */
    private function getOrderedManagers(Item $erItem): array
    {
        $managers = $erItem->get(EmployeeRequest::HEAD_OF_DEPARTMENT);

        if (empty($managers) || !is_array($managers)) {
            return [];
        }

        $managers = array_values(array_filter(array_map('intval', $managers)));

        if (empty($managers)) {
            return [];
        }

        $excludeDirector      = Option::get(self::MODULE_ID, 'LA_EXCLUDE_DIRECTOR', 'N') === 'Y';
        $forceDirectorIfManager = Option::get(self::MODULE_ID, 'LA_FORCE_DIRECTOR_IF_MANAGER', 'N') === 'Y';

        if (!$excludeDirector) {
            return $managers;
        }

        // Исключаем директора (последний в цепочке)
        $director  = end($managers);
        $managers  = array_slice($managers, 0, -1);

        // Возвращаем директора, если заявитель сам руководитель
        if ($forceDirectorIfManager && $this->isManager((int)$erItem->getCreatedBy())) {
            $managers[] = $director;
        }

        return $managers;
    }

    /**
     * Возвращает общее количество шагов в фазе 3 для данной заявки.
     * Используется в Approvers\ChangeStatus для определения последнего шага.
     */
    public function getPhase3TotalSteps(Item $erItem): int
    {
        $creatorId = (int)$erItem->getCreatedBy();
        $profileId = $this->getApprovalProfileId($creatorId);

        if ($profileId > 0) {
            return $this->countProfileSteps($profileId);
        }

        return count($this->getOrderedManagers($erItem));
    }

    // -------------------------------------------------------------------------
    // Вспомогательные методы
    // -------------------------------------------------------------------------

    /**
     * Создаёт один AP-элемент, если он ещё не существует.
     */
    private function createApproverItem(Item $erItem, int $userId, int $phase, int $order): void
    {
        if ($this->approverExists($erItem, $userId, $phase)) {
            return;
        }

        $item = $this->approversFactory->createItem();
        $item->setAssignedById($userId);
        $item->set('PARENT_ID_' . $erItem->getEntityTypeId(), $erItem->getId());
        $item->set(Approvers::PHASE, $phase);

        if ($order > 0) {
            $item->set(Approvers::ORDER, $order);
        }

        $this->copyMappingData($erItem, $item);

        $operation = $this->approversFactory->getAddOperation($item);
        $result    = $operation->disableCheckAccess()->launch();

        if (!$result->isSuccess()) {
            // TODO: логировать ошибки через модуль логирования Bitrix
        }
    }

    /**
     * Проверяет, существует ли AP-элемент для данного пользователя и фазы.
     */
    private function approverExists(Item $erItem, int $userId, int $phase): bool
    {
        $items = $this->approversFactory->getItems([
            'filter' => [
                '=ASSIGNED_BY_ID'                                  => $userId,
                '=PARENT_ID_' . $erItem->getEntityTypeId()         => $erItem->getId(),
                '=' . Approvers::PHASE                             => $phase,
            ],
            'select' => ['ID'],
            'limit'  => 1,
        ]);

        return !empty($items);
    }

    /**
     * Копирует поля из ER в AP согласно MAPPING.
     */
    private function copyMappingData(Item $erItem, Item $apItem): void
    {
        foreach (EmployeeRequest::MAPPING as $erField => $apField) {
            if (
                $erItem->hasField($erField)
                && $apItem->hasField($apField)
                && ($value = $erItem->get($erField)) !== null
            ) {
                $apItem->set($apField, $value);
            }
        }
    }

    /**
     * Возвращает ID профиля ProfileParticipants для пользователя.
     * Читает поле UF_APPROVAL_PROFILE на сущности пользователя.
     */
    private function getApprovalProfileId(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $user = CUser::GetByID($userId)->Fetch();

        if (!$user) {
            return 0;
        }

        return (int)($user['UF_APPROVAL_PROFILE'] ?? 0);
    }

    /**
     * Считает количество шагов ALP в профиле PP.
     */
    private function countProfileSteps(int $profileId): int
    {
        $alpEntityTypeId = $this->container->getEntityTypeIdByCode('ALP_SMART_PROCESS_ID');
        $ppEntityTypeId  = $this->container->getEntityTypeIdByCode('PP_SMART_PROCESS_ID');

        if (!$alpEntityTypeId || !$ppEntityTypeId) {
            return 0;
        }

        $alpFactory = $this->container->getFactory($alpEntityTypeId);
        if (!$alpFactory) {
            return 0;
        }

        $steps = $alpFactory->getItems([
            'filter' => ['=PARENT_ID_' . $ppEntityTypeId => $profileId],
            'select' => ['ID'],
        ]);

        return count($steps);
    }

    /**
     * Проверяет, является ли пользователь руководителем хотя бы одного отдела.
     */
    private function isManager(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        if (!\Bitrix\Main\Loader::includeModule('humanresources')) {
            return false;
        }

        $nodeMemberService = \Bitrix\HumanResources\Service\Container::getNodeMemberService();
        $nodeRepository    = \Bitrix\HumanResources\Service\Container::getNodeRepository();

        // Получаем все узлы и проверяем, есть ли userId среди руководителей
        // Используем упрощённую проверку через CUser::IsAdmin или роль head
        $user = CUser::GetByID($userId)->Fetch();
        if (!$user || empty($user['UF_DEPARTMENT'])) {
            return false;
        }

        foreach ((array)$user['UF_DEPARTMENT'] as $deptId) {
            $node = $nodeRepository->getByAccessCode(
                \Bitrix\HumanResources\Compatibility\Utils\DepartmentBackwardAccessCode::makeById((int)$deptId)
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
