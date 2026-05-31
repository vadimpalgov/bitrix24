<?php

namespace Parfeon\Er\Factory;

use Bitrix\Crm\Service\Factory\Dynamic;
use Bitrix\Crm\Item;
use Bitrix\Crm\Service\Context;
use Bitrix\Crm\Service\Operation;

/**
 * Фабрика для смарт-процесса «Профили согласования» (ALP).
 *
 * Элемент профиля описывает одного согласующего и его стадию
 * внутри шаблона ProfileParticipants.
 *
 * Поля: UF_CRM_12_USER, UF_CRM_12_STAGE, UF_CRM_12_ORDER
 */
class ApprovalProfileFactory extends Dynamic
{
    public function getAddOperation(Item $item, Context $context = null): Operation\Add
    {
        $operation = parent::getAddOperation($item, $context);

        // Место для actions при создании шага профиля

        return $operation;
    }

    public function getUpdateOperation(Item $item, Context $context = null): Operation\Update
    {
        $operation = parent::getUpdateOperation($item, $context);

        // Место для actions при обновлении шага профиля

        return $operation;
    }
}
