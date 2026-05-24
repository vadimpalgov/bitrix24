<?php

namespace Parfeon\Er\Operation\EmployeeRequest;

use Bitrix\Crm\Item;
use Bitrix\Crm\Service\Operation\Action;
use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Bitrix\Main\Type\Date;
use Bitrix\Main\Localization\Loc;
use Parfeon\Er\Mapping\EmployeeRequest;

class DayStartChecker extends Action
{
    const MODULE_ID = 'parfeon.er';

    public function process(Item $item): Result
    {
        $result = new Result();

        $minDaysBefore = (int)\COption::GetOptionString(self::MODULE_ID, 'LA_MIN_DAYS_BEFORE_START', 0);

        if ($minDaysBefore <= 0) {
            return $result;
        }

        $startDate = $item->get(EmployeeRequest::DATE_START);
        ray('$startDate', $startDate);
        if (!$startDate instanceof Date) {
            return $result;
        }

        $today = new Date();
        $deadlineDate = (new Date())->add($minDaysBefore . 'D');

        // Сравниваем: если дата начала отпуска раньше, чем сегодня + N дней
        if ($startDate->getTimestamp() < $deadlineDate->getTimestamp()) {
            $result->addError(new Error(
                sprintf(
                    'Заявление должно быть подано не менее чем за %d дн. до начала отпуска. Ближайшая возможная дата: %s',
                    $minDaysBefore,
                    $deadlineDate->toString()
                )
            ));
        }

        return $result;
    }
}