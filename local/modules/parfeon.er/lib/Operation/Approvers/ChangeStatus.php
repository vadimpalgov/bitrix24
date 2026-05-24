<?php

namespace Parfeon\Er\Operation\Approvers;

use Bitrix\Crm\Item;
use Bitrix\Crm\Service\Container;
use Bitrix\Crm\Service\Operation\Action;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Error;
use Bitrix\Main\Result;
use Bitrix\Main\Loader;
use Parfeon\Er\Mapping;

class ChangeStatus extends Action
{
    public function process(Item $item): Result
    {
        $result = new Result();
        $moduleId = 'parfeon.er';

        // 1. Проверяем, изменилась ли стадия
        if (!$item->isChangedStageId()) {
            return $result;
        }

        $currentStageId = $item->getStageId();

        // 2. Получаем настройки стадий из модуля
        $apApprove = Option::get($moduleId, 'AP_APPROVE_STATUS');
        $apReject = Option::get($moduleId, 'AP_REJECT_STATUS');
        $erApprove = Option::get($moduleId, 'ER_APPROVE_STATUS');
        $erReject = Option::get($moduleId, 'ER_REJECT_STATUS');
        $erEntityTypeId = (int)Option::get($moduleId, 'ER_SMART_PROCESS_ID');

        if (!$apApprove || !$apReject || !$erApprove || !$erReject || !$erEntityTypeId) {
            return $result;
        }

        // Поле привязки к родителю (динамическое название типа PARENT_ID_156)
        $parentFieldName = 'PARENT_ID_' . $erEntityTypeId;
        $parentId = (int)$item->get($parentFieldName);

        if ($parentId <= 0) {
            return $result;
        }

        $factoryAP = Container::getInstance()->getFactory($item->getEntityTypeId());
        $factoryER = Container::getInstance()->getFactory($erEntityTypeId);

        if (!$factoryAP || !$factoryER) {
            return $result;
        }

        if ($currentStageId === $apReject) {

            $reasonForRejection = $item->get(Mapping\Approvers::REASON_FOR_REJECTION);
            if(!$reasonForRejection){
                $result->addError(new Error('Не указана причина отклонения заявки'));
                return $result;
            }

            $updateParentResult = $this->updateParentStatus($factoryER, $parentId, $erReject, $reasonForRejection);
            if(!$updateParentResult->isSuccess()) {
                $result->addErrors($updateParentResult->getErrors());
            }
            return $result;
        }

        if ($currentStageId === $apApprove) {

            $siblings = $factoryAP->getItems([
                'filter' => [
                    '=' . $parentFieldName => $parentId,
                    '!=ID' => $item->getId()
                ]
            ]);

            $allApproved = true;
            $anyRejected = false;

            $hrDepartmentId = $this->getHrDepartmentId();
            $isCurrentRequesterHr = $this->isUserInDepartment($item->getAssignedById(), $hrDepartmentId);

            $hrApprovedBySomeone = $isCurrentRequesterHr;

            foreach ($siblings as $sibling) {
                $siblingStage = $sibling->getStageId();
                $siblingUserId = $sibling->getAssignedById();
                $isSiblingHr = $this->isUserInDepartment($siblingUserId, $hrDepartmentId);

                if ($siblingStage === $apReject) {

                    $reasonForRejection = $item->get(Mapping\Approvers::REASON_FOR_REJECTION);
                    if(!$reasonForRejection){
                        $result->addError(new Error('Не указана причина отклонения заявки'));
                        return $result;
                    }

                    $updateParentResult = $this->updateParentStatus($factoryER, $parentId, $erReject, $reasonForRejection);
                    if(!$updateParentResult->isSuccess()) {
                        $result->addErrors($updateParentResult->getErrors());
                    }
                    return $result;
                }

                if ($isSiblingHr) {
                    if ($siblingStage === $apApprove) {
                        $hrApprovedBySomeone = true;
                    }
                } else {
                    if ($siblingStage !== $apApprove) {
                        $allApproved = false;
                    }
                }
            }
            ray('$allApproved', $allApproved);
            ray('$hrApprovedBySomeone', $hrApprovedBySomeone);
            if ($allApproved && $hrApprovedBySomeone) {
                $updateParentResult = $this->updateParentStatus($factoryER, $parentId, $erApprove);
            }

//            elseif ($allApproved) {
//                $updateParentResult = $this->updateParentStatus($factoryER, $parentId, $erApprove);
//            }

            if($updateParentResult && !$updateParentResult->isSuccess()) {
                $result->addErrors($updateParentResult->getErrors());
            }
        }

        return $result;
    }

    /**
     * Вспомогательный метод для обновления статуса родителя
     */
    private function updateParentStatus($factory, $id, $stageId, ?string $reasonForRejection = null): Result
    {   ray('updateParentStatus', $id, $stageId, $reasonForRejection);

        $parentItem = $factory->getItem($id);
        if ($parentItem && $parentItem->getStageId() !== $stageId) {

            $parentItem->setStageId($stageId);

            if($reasonForRejection){
                $parentItem->set(Mapping\EmployeeRequest::REASON_FOR_REJECTION, $reasonForRejection);
            }

            $operation = $factory->getUpdateOperation($parentItem);

            return $operation->disableCheckAccess()->launch();
        }

        return new Result();
    }

    private function isUserInDepartment(int $userId, int $deptId): bool
    {
        if ($deptId <= 0 || $userId <= 0) return false;

        Loader::includeModule('main');
        $dbUser = \CUser::GetList($by, $order, ['ID' => $userId], ['SELECT' => ['UF_DEPARTMENT']]);
        if ($user = $dbUser->Fetch()) {
            return in_array($deptId, (array)$user['UF_DEPARTMENT']);
        }
        return false;
    }

    private function getHrDepartmentId(): int
    {
        return 40;
    }
}