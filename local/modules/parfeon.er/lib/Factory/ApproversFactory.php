<?php

namespace Parfeon\Er\Factory;

use Bitrix\Crm\Service\Factory\Dynamic;
use Bitrix\Crm\Item;
use Bitrix\Crm\Service\Context;
use Bitrix\Crm\Service\Operation;
use Parfeon\Er\Operation\Approvers\ChangeStatus;
use Parfeon\Er\Operation\Approvers\SendNotify;


class ApproversFactory  extends Dynamic
{
    public function getAddOperation(Item $item, Context $context = null): Operation\Add
    {
        $operation = parent::getAddOperation($item, $context);

        $operation->addAction(
            Operation::ACTION_AFTER_SAVE,
            new SendNotify()
        );

        return $operation;
    }

    public function getUpdateOperation(Item $item, Context $context = null): Operation\Update
    {
        $operation = parent::getUpdateOperation($item, $context);

        $operation->addAction(
            Operation::ACTION_BEFORE_SAVE,
            new ChangeStatus()
        );



        return $operation;
    }


    public function getItems(array $parameters = []): array
    {
        //unset($parameters['runtime']['permissions']);
        $items = parent::getItems($parameters);



        //ray($items, $_SERVER[''] === '/var/www/bitrix/bitrix/components/bitrix/crm.item.list/lazyload.ajax.php');
        return $items;
    }

}