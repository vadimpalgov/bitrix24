<?php

namespace Parfeon\Er\Operation\Approvers;

use Bitrix\Crm\Item;
use Bitrix\Crm\Service\Operation\Action;
use Bitrix\Main\Config\Option;
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Error;
use Bitrix\Main\Result;
use Parfeon\Er\Services\NotifyService;
use Parfeon\Er\Mapping;

class SendNotify extends Action
{
    public function process(Item $item): Result
    {
        $result = new Result();

        $moduleId = 'parfeon.er';

        /** @var NotifyService $notifyService */
        $notifyService = ServiceLocator::getInstance()->get('employee.requests.service.notify');

        $entityTypeId = (int)Option::get($moduleId, 'ER_SMART_PROCESS_ID');
        $apStart = Option::get($moduleId, 'AR_START_STATUS');
        $apApprove = Option::get($moduleId, 'AP_APPROVE_STATUS');
        $apReject = Option::get($moduleId, 'AP_REJECT_STATUS');

        if($item->getStageId() === $apStart){
            $message = 'На ваше имя создана новая заявка на согласование';
        } else {
            $message = 'Заявка изменена';
        }

        $requestData = [
            'requestId' => $item->getId(),
            'typeId' => $item->get(Mapping\Approvers::TYPE),
            'message' => $message,
            'url' => '/crm/type/'.$entityTypeId.'/details/'.$item->getId().'/',
            'event' => 'request_approved'
        ];

        $sendNotifyResult = $notifyService->send($item->getAssignedById(), $requestData);

        if(!$sendNotifyResult){
            $result->addError(new Error('Ошибка отправки уведомления пользователю ' . $item->getAssignedById()));
        }

        return $result;
    }

}