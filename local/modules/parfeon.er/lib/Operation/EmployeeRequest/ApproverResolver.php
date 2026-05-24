<?php

namespace Parfeon\Er\Operation\EmployeeRequest;

use Bitrix\Crm\Item;
use Bitrix\Crm\Service\Operation\Action;
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Result;

class ApproverResolver extends Action
{


    public function process(Item $item): Result
    {
        $result = new Result();

        /** @var \Parfeon\Er\Services\CreateApprovers $createApproversService */
        $createApproversService = ServiceLocator::getInstance()->get('parfeon.er.service.create.approvers');

        if($createApproversService){
            $createApproversService->create($item);
        }

        return $result;
    }
}