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
        $notifyService = ServiceLocator::getInstance()->get('parfeon.er.service.notify');

        $apStart = Option::get($moduleId, 'AP_START_STATUS');

        $message = $item->getStageId() === $apStart
            ? 'На ваше имя создана новая заявка на согласование'
            : 'Заявка на согласование изменена';

        $requestData = [
            'requestId' => $item->getId(),
            'typeId'    => $item->get(Mapping\Approvers::TYPE),
            'message'   => $message,
            'url'       => '/crm/type/' . $item->getEntityTypeId() . '/details/' . $item->getId() . '/',
            'event'     => 'approver_notify',
        ];

        $sendNotifyResult = $notifyService->send($item->getAssignedById(), $requestData);

        if(!$sendNotifyResult){
            $result->addError(new Error('Ошибка отправки уведомления пользователю ' . $item->getAssignedById()));
        }

        return $result;
    }

}