<?php

namespace Parfeon\Er\Factory;

use Bitrix\Crm\Service\Factory\Dynamic;
use Bitrix\Crm\Item;
use Bitrix\Crm\Service\Context;
use Bitrix\Crm\Service\Operation;
use Bitrix\Main\Config\Option;
use Parfeon\Er\Mapping\EmployeeRequest;
use Parfeon\Er\Operation\EmployeeRequest\AddingAbsencesSchedule;
use Parfeon\Er\Operation\EmployeeRequest\ApproverResolver;
use Parfeon\Er\Operation\EmployeeRequest\ChangeStatus;
use Parfeon\Er\Operation\EmployeeRequest\CreateName;
use Parfeon\Er\Operation\EmployeeRequest\DayCountChecker;
use Parfeon\Er\Operation\EmployeeRequest\DayStartChecker;
use Parfeon\Er\Operation\EmployeeRequest\DepartmentManagerResolver;
use Parfeon\Er\Operation\EmployeeRequest\HRManagersResolver;


class EmployeeRequestFactory  extends Dynamic
{
    const MODULE_ID = 'parfeon.er';

    public function getAddOperation(Item $item, Context $context = null): Operation\Add
    {
        $operation = parent::getAddOperation($item, $context);

        $operation->addAction(
            Operation::ACTION_BEFORE_SAVE,
            new CreateName()
        );


        $enableMinDays = \COption::GetOptionString(self::MODULE_ID, 'LA_ENABLE_MIN_DAYS_CHECK', 'N') === 'Y';
        if($enableMinDays) {
            $operation->addAction(
                Operation::ACTION_BEFORE_SAVE,
                new DayCountChecker()
            );

            $operation->addAction(
                Operation::ACTION_BEFORE_SAVE,
                new DayStartChecker()
            );
        }

        $operation->addAction(
            Operation::ACTION_BEFORE_SAVE,
            new DepartmentManagerResolver()
        );

        $operation->addAction(
            Operation::ACTION_BEFORE_SAVE,
            new HRManagersResolver()
        );

        return $operation;
    }

    public function getUpdateOperation(Item $item, Context $context = null): Operation\Update
    {
        $operation = parent::getUpdateOperation($item, $context);

        $epStart   = Option::get(self::MODULE_ID, 'ER_START_STATUS');
        $epApprove = Option::get(self::MODULE_ID, 'ER_APPROVE_STATUS');
        $epReject  = Option::get(self::MODULE_ID, 'ER_REJECT_STATUS');

        $enableMinDays = \COption::GetOptionString(self::MODULE_ID, 'LA_ENABLE_MIN_DAYS_CHECK', 'N') === 'Y';
        if($enableMinDays) {
            $operation->addAction(
                Operation::ACTION_BEFORE_SAVE,
                new DayCountChecker()
            );
        }

        if($item->isChangedStageId() && $item->getStageId() === $epStart){

            $operation->addAction(
                Operation::ACTION_BEFORE_SAVE,
                new DepartmentManagerResolver()
            );

            $operation->addAction(
                Operation::ACTION_BEFORE_SAVE,
                new HRManagersResolver()
            );


            $operation->addAction(
                Operation::ACTION_AFTER_SAVE,
                new ApproverResolver()
            );
        }

        if($item->getStageId() === $epReject || $item->getStageId() === $epApprove){
            $operation->addAction(
                Operation::ACTION_BEFORE_SAVE,
                new ChangeStatus()
            );
        }

        $operation->addAction(
            Operation::ACTION_AFTER_SAVE,
            new AddingAbsencesSchedule()
        );

        return $operation;
    }




}