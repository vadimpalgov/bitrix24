<?php

namespace Parfeon\Er\Operation\EmployeeRequest;

use Bitrix\Crm\Item;
use Bitrix\Crm\Service\Operation\Action;
use Bitrix\Main\Config\Option;
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Result;
use Parfeon\Er\Services\NotifyService;
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

        $erApprove = Option::get($moduleId, 'ER_APPROVE_STATUS');
        $erReject = Option::get($moduleId, 'ER_REJECT_STATUS');

        /** @var NotifyService $notifyService */
        $notifyService = ServiceLocator::getInstance()->get('employee.requests.service.notify');

        $typeName = $notifyService->getTypeName($item->get(Mapping\EmployeeRequest::TYPE));

        if($item->getStageId() === $erApprove) {
            $message = 'Ваша '.$typeName.' согласована';
        }

        if($item->getStageId() === $erReject) {
            $message = 'Ваша '.$typeName.' отклонена по причине - ' .$item->get(Mapping\EmployeeRequest::REASON_FOR_REJECTION);
        }

        $requestData = [
            'requestId' => $item->getId(),
            'typeId' => $item->get(Mapping\EmployeeRequest::TYPE),
            'message' => $message,
            'url' => '/crm/type/'.$item->getEntityTypeId().'/details/'.$item->getId().'/',
            'event' => 'request_approved'
        ];

        $sendNotifyResult = $notifyService->send($item->getAssignedById(), $requestData);

        if(!$sendNotifyResult->isSuccess()){
            $result->addErrors($sendNotifyResult->getErrors());
        }

        return $result;
    }
}