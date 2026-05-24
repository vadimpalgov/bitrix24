<?php

namespace Parfeon\Er\Operation\EmployeeRequest;

use Bitrix\Crm\Item;
use Bitrix\Crm\Service\Operation\Action;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Result;
use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Application;
use Parfeon\Er\Mapping\EmployeeRequest;

class AddingAbsencesSchedule extends Action
{
    public function process(Item $item): Result
    {
        $result = new Result();
        $moduleId = 'parfeon.er';

        $approveStatus = Option::get($moduleId, 'ER_APPROVE_STATUS');

        // Проверяем стадию
        if ($item->getStageId() !== $approveStatus) {
            return $result;
        }

        if (!Loader::includeModule('iblock')) {
            return $result;
        }

        // 1. Находим ID инфоблока графиков отсутствий по символьному коду
        $iblockId = null;
        $res = \CIBlock::GetList([], ['TYPE' => 'structure', 'CODE' => 'absence']);
        if ($absenceIblock = $res->Fetch()) {
            $iblockId = $absenceIblock['ID'];
        }

        if (!$iblockId) {

            // Если код 'absence' не найден, можно попробовать найти по названию или ID из настроек
            return $result;
        }

        $userId = $item->getAssignedById();
        $dateStart = $item->get(EmployeeRequest::DATE_START);
        $dateEnd = $item->get(EmployeeRequest::DATE_END);

        // Подготавливаем поля для инфоблока
        $el = new \CIBlockElement;

        $fields = [
            "IBLOCK_ID"      => $iblockId,
            "NAME"           => "Отпуск: " . $item->getHeading() ?: "Заявка №" . $item->getId(),
            "ACTIVE"         => "Y",
            "DATE_ACTIVE_FROM" => (string)$dateStart,
            "DATE_ACTIVE_TO"   => (string)$dateEnd,
            "PROPERTY_VALUES" => [
                "USER"         => $userId, // Привязка к пользователю (код свойства обычно USER)
                "ABSENCE_TYPE" => "VACATION", // Тип отсутствия
                "FINISH_STATE" => "Y" // Подтвержденное отсутствие
            ]
        ];

        if ($absenceId = $el->Add($fields)) {

        } else {
            $result->addError(new \Bitrix\Main\Error($el->LAST_ERROR));
        }

        return $result;
    }
}