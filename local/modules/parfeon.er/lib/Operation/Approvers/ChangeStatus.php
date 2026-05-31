<?php

namespace Parfeon\Er\Operation\Approvers;

use Bitrix\Crm\Item;
use Bitrix\Crm\Service\Container;
use Bitrix\Crm\Service\Operation\Action;
use Bitrix\Main\Config\Option;
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Error;
use Bitrix\Main\Result;
use Parfeon\Er\Mapping\Approvers;
use Parfeon\Er\Mapping\EmployeeRequest;
use Parfeon\Er\Services\CreateApprovers;

class ChangeStatus extends Action
{
    private const MODULE_ID = 'parfeon.er';

    public function process(Item $item): Result
    {
        $result = new Result();

        if (!$item->isChangedStageId()) {
            return $result;
        }

        $apApprove = Option::get(self::MODULE_ID, 'AP_APPROVE_STATUS');
        $apReject  = Option::get(self::MODULE_ID, 'AP_REJECT_STATUS');
        $erApprove = Option::get(self::MODULE_ID, 'ER_APPROVE_STATUS');
        $erReject  = Option::get(self::MODULE_ID, 'ER_REJECT_STATUS');
        $erEntityTypeId = (int)Option::get(self::MODULE_ID, 'ER_SMART_PROCESS_ID');

        if (!$apApprove || !$apReject || !$erApprove || !$erReject || !$erEntityTypeId) {
            return $result;
        }

        $parentFieldName = 'PARENT_ID_' . $erEntityTypeId;
        $parentId        = (int)$item->get($parentFieldName);

        if ($parentId <= 0) {
            return $result;
        }

        $factoryAP = Container::getInstance()->getFactory($item->getEntityTypeId());
        $factoryER = Container::getInstance()->getFactory($erEntityTypeId);

        if (!$factoryAP || !$factoryER) {
            return $result;
        }

        $currentStage = $item->getStageId();

        // ── REJECT ────────────────────────────────────────────────────────────
        if ($currentStage === $apReject) {
            $reason = $item->get(Approvers::REASON_FOR_REJECTION);

            if (!$reason) {
                $result->addError(new Error('Не указана причина отклонения заявки'));
                return $result;
            }

            return $this->updateParentStatus($factoryER, $parentId, $erReject, $reason);
        }

        // ── APPROVE ───────────────────────────────────────────────────────────
        if ($currentStage !== $apApprove) {
            return $result;
        }

        $phase = (int)$item->get(Approvers::PHASE);
        $order = (int)$item->get(Approvers::ORDER);

        $erItem = $factoryER->getItem($parentId);
        if (!$erItem) {
            return $result;
        }

        /** @var CreateApprovers $createApprovers */
        $createApprovers = ServiceLocator::getInstance()->get('parfeon.er.service.create.approvers');

        $siblings = $factoryAP->getItems([
            'filter' => [
                '='  . $parentFieldName  => $parentId,
                '='  . Approvers::PHASE  => $phase,
                '!=' . 'ID'              => $item->getId(),
            ],
        ]);

        switch ($phase) {

            // ── Фаза 1: HR — достаточно одного ──────────────────────────────
            case 1:
                // Проверяем: не отклонил ли кто-то другой из HR
                foreach ($siblings as $sibling) {
                    if ($sibling->getStageId() === $apReject) {
                        $reason = $sibling->get(Approvers::REASON_FOR_REJECTION) ?: 'Отклонено HR-менеджером';
                        return $this->updateParentStatus($factoryER, $parentId, $erReject, $reason);
                    }
                }
                // Хватит одного одобрения — запускаем фазу 2
                $createApprovers->createPhase(2, $erItem);
                break;

            // ── Фаза 2: РП — нужны все ──────────────────────────────────────
            case 2:
                foreach ($siblings as $sibling) {
                    if ($sibling->getStageId() === $apReject) {
                        $reason = $sibling->get(Approvers::REASON_FOR_REJECTION) ?: 'Отклонено руководителем проекта';
                        return $this->updateParentStatus($factoryER, $parentId, $erReject, $reason);
                    }

                    if ($sibling->getStageId() !== $apApprove) {
                        // Ещё не все одобрили — ждём
                        return $result;
                    }
                }
                // Все одобрили — запускаем фазу 3
                $createApprovers->createPhase(3, $erItem, 1);
                break;

            // ── Фаза 3: Руководители — последовательно ──────────────────────
            case 3:
                $totalSteps = $createApprovers->getPhase3TotalSteps($erItem);
                $nextOrder  = $order + 1;

                if ($nextOrder <= $totalSteps) {
                    // Есть следующий — создаём
                    $createApprovers->createPhase(3, $erItem, $nextOrder);
                } else {
                    // Последний согласовал — одобряем ER
                    return $this->updateParentStatus($factoryER, $parentId, $erApprove);
                }
                break;
        }

        return $result;
    }

    private function updateParentStatus($factory, int $id, string $stageId, ?string $reason = null): Result
    {
        $parentItem = $factory->getItem($id);

        if (!$parentItem || $parentItem->getStageId() === $stageId) {
            return new Result();
        }

        $parentItem->setStageId($stageId);

        if ($reason) {
            $parentItem->set(EmployeeRequest::REASON_FOR_REJECTION, $reason);
        }

        return $factory->getUpdateOperation($parentItem)->disableCheckAccess()->launch();
    }
}
