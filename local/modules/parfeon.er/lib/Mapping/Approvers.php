<?php

namespace Parfeon\Er\Mapping;

class Approvers
{
    const CRM_TYPE_NUMBER = 11;

    const TYPE = 'UF_CRM_' . self::CRM_TYPE_NUMBER . '_TYPE';

    const DESCRIPTION = 'UF_CRM_' . self::CRM_TYPE_NUMBER . '_DESCRIPTION';
    const REASON_FOR_REJECTION = 'UF_CRM_' . self::CRM_TYPE_NUMBER . '_REASON_FOR_REJECTION';

    const DATE_START = 'UF_CRM_' . self::CRM_TYPE_NUMBER . '_DATE_START';

    const DATE_END = 'UF_CRM_' . self::CRM_TYPE_NUMBER . '_DATE_END';
}
