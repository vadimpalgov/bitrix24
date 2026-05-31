<?php

namespace Parfeon\Er\Mapping;

/**
 * Маппинг полей смарт-процесса «Участники профиля».
 *
 * Связывает пользователя с его профилем согласования (ALP)
 * под конкретный тип заявки. Является родительской сущностью
 * для элементов ApprovalProfile.
 *
 * Entity Type ID: 1058
 */
class ProfileParticipants
{
    const CRM_TYPE_NUMBER = 13;

    const ENTITY_TYPE_ID = 1058;

    /**
     * Пользователь — участник профиля (согласующий).
     * Тип поля: привязка к пользователю.
     */
    const USER = 'UF_CRM_' . self::CRM_TYPE_NUMBER . '_USER';

    /**
     * Тип заявки, для которого назначен данный участник.
     * Тип поля: привязка к элементам инфоблоков и смарт-процессов.
     * Ссылается на тот же инфоблок-справочник, что и UF_CRM_10_TYPE у ER.
     */
    const REQUEST_TYPE = 'UF_CRM_' . self::CRM_TYPE_NUMBER . '_TYPE';
}
