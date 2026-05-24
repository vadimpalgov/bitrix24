<?php

namespace Parfeon\Er\Operation\EmployeeRequest;

use Bitrix\Crm\Item;
use Bitrix\Crm\Service\Operation\Action;
use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Bitrix\Main\Type\Date;
use Parfeon\Er\Mapping\EmployeeRequest;

class DayCountChecker extends Action
{
    const MODULE_ID = 'parfeon.er';

    public function process(Item $item): Result
    {
        $result = new Result();

        $daysCount = (int)\COption::GetOptionString(self::MODULE_ID, 'LA_MIN_DAYS', 14);

        if($daysCount <=0){
            return $result;
        }

        $dateStartValue = $item->get(EmployeeRequest::DATE_START);
        $dateEndValue   = $item->get(EmployeeRequest::DATE_END);

        // если одно из полей не заполнено — проверку пропускаем
        if (!$dateStartValue || !$dateEndValue) {
            return $result;
        }

        try {
            $dateStart = new Date($dateStartValue);
            $dateEnd   = new Date($dateEndValue);
        } catch (\Exception $e) {
            $result->addError(new Error('Некорректный формат даты'));
            return $result;
        }

        // разница в днях
        $diffDays = (int)(($dateEnd->getTimestamp() - $dateStart->getTimestamp()) / 86400);

        if ($diffDays < $daysCount) {
            $result->addError(
                new Error("Между датой начала и датой окончания должно быть не менее {$daysCount} дней")
            );
        }

        return $result;
    }
}