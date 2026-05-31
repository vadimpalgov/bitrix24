<?php

namespace Parfeon\Er\Factory;

use Bitrix\Crm\Service\Factory\Dynamic;
use Bitrix\Crm\Item;
use Bitrix\Crm\Service\Context;
use Bitrix\Crm\Service\Operation;

/**
 * Фабрика для смарт-процесса «Участники профиля» (PP).
 *
 * Элемент является именованным шаблоном согласования.
 * Назначается сотруднику через поле UF_APPROVAL_PROFILE.
 * Содержит дочерние элементы ApprovalProfile (ALP) — шаги согласования.
 */
class ProfileParticipantsFactory extends Dynamic
{
    public function getAddOperation(Item $item, Context $context = null): Operation\Add
    {
        $operation = parent::getAddOperation($item, $context);

        // Место для actions при создании профиля

        return $operation;
    }

    public function getUpdateOperation(Item $item, Context $context = null): Operation\Update
    {
        $operation = parent::getUpdateOperation($item, $context);

        // Место для actions при обновлении профиля

        return $operation;
    }
}
