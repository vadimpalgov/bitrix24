<?php

namespace Parfeon\Er\Mapping;

class EmployeeRequest
{
    const MAPPING = [
        self::TYPE => Approvers::TYPE,
        self::DESCRIPTION => Approvers::DESCRIPTION,
     //   self::REASON_FOR_REJECTION => Approvers::REASON_FOR_REJECTION,
        self::DATE_START => Approvers::DATE_START,
        self::DATE_END => Approvers::DATE_END,
    ];

   // const STAGE_CREATE = 'DT1034_54:NEW';
   // const STAGE_CREATE = 'DT1154_76:NEW';

    const CRM_TYPE_NUMBER = 10;

    const PROJECT_MANAGERS = 'UF_CRM_' . self::CRM_TYPE_NUMBER . '_PROJECT_MANAGERS';
    const HEAD_OF_DEPARTMENT = 'UF_CRM_' . self::CRM_TYPE_NUMBER . '_HEAD_OF_DEPARTMENT';
    const HR_MANAGERS = 'UF_CRM_' . self::CRM_TYPE_NUMBER . '_HR';

    const TYPE = 'UF_CRM_' . self::CRM_TYPE_NUMBER . '_TYPE';
    const DESCRIPTION = 'UF_CRM_' . self::CRM_TYPE_NUMBER . '_DESCRIPTION';
    const REASON_FOR_REJECTION = 'UF_CRM_' . self::CRM_TYPE_NUMBER . '_REASON_FOR_REJECTION';

    const DATE_START = 'UF_CRM_' . self::CRM_TYPE_NUMBER . '_DATE_START';

    const DATE_END = 'UF_CRM_' . self::CRM_TYPE_NUMBER . '_DATE_END';
}