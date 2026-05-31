<?php

namespace Parfeon\Er\Operation\EmployeeRequest;

use Bitrix\Crm\Item;
use Bitrix\Crm\Service\Operation\Action;
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Result;
use Bitrix\Main\UserTable;
use Parfeon\Er\Services\NotifyService;
use Parfeon\Er\Mapping;

class CreateName extends Action
{
    public function process(Item $item): Result
    {
        $result = new Result();

        /** @var NotifyService $notifyService */
        $notifyService = ServiceLocator::getInstance()->get('parfeon.er.service.notify');

        $typeEnumId = $item->get(Mapping\EmployeeRequest::TYPE);
        $typeName = $notifyService->getTypeName($typeEnumId) ?: 'Заявка';

        $createdBy = (int)$item->getCreatedBy();
        $userName = "Сотрудник #{$createdBy}";

        if ($createdBy > 0) {
            $user = UserTable::getList([
                'select' => ['NAME', 'LAST_NAME'],
                'filter' => ['=ID' => $createdBy],
                'limit' => 1,
            ])->fetch();

            if ($user) {
                $userName = trim($user['NAME'] . ' ' . $user['LAST_NAME']);
            }
        }

        $newTitle = sprintf('%s от %s', $typeName, $userName);

        $item->setTitle($newTitle);

        return $result;
    }
}