<?php

namespace Parfeon\Er\Mapping;

/**
 * Маппинг полей смарт-процесса «Профили согласования» (ALP).
 *
 * Профиль описывает, какую стадию AP должен выставить согласующий
 * при обработке заявки конкретного типа.
 *
 * Entity Type ID: 1054
 */
class ApprovalProfile
{
    const CRM_TYPE_NUMBER = 12;

    const ENTITY_TYPE_ID = 1054;

    /**
     * Тип заявки, к которому относится профиль.
     * Тип поля: привязка к элементам инфоблоков и смарт-процессов.
     * Ссылается на тот же инфоблок-справочник, что и UF_CRM_10_TYPE у ER.
     */
    const APPROVAL_TYPE = 'UF_CRM_' . self::CRM_TYPE_NUMBER . '_TYPE';

    /**
     * Целевая стадия смарт-процесса AP, которую согласующий должен выставить.
     * Тип поля: привязка к справочникам CRM (стадии смарт-процесса Approvers).
     */
    const APPROVAL_STAGE = 'UF_CRM_' . self::CRM_TYPE_NUMBER . '_STAGE';

    /**
     * Согласующий — пользователь Bitrix.
     * Тип поля: привязка к пользователю.
     */
    const USER = 'UF_CRM_' . self::CRM_TYPE_NUMBER . '_USER';

    /**
     * Порядковый номер шага в цепочке профиля.
     * Определяет очерёдность последовательного согласования в фазе 3.
     */
    const ORDER = 'UF_CRM_' . self::CRM_TYPE_NUMBER . '_ORDER';
}
