<?php

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;
use Bitrix\Crm\Model\Dynamic\TypeTable;
use Bitrix\Crm\StatusTable;

class parfeon_er extends CModule
{
    public $MODULE_ID = 'parfeon.er';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $PARTNER_NAME;
    public $PARTNER_URI;

    private static array $basicDepartments = [
        'Руководство', 'Разработка', 'Маркетинг', 'Продажи',
        'Финансы', 'HR', 'Административный отдел',
    ];

    private static array $spLabels = [
        'ER'   => 'Заявки сотрудников',
        'AP'   => 'Согласующие',
        'ALP'  => 'Профили согласования',
        'ALPU' => 'Участники профиля',
    ];

    public function __construct()
    {
        $arModuleVersion = [];
        include __DIR__ . '/version.php';

        $this->MODULE_VERSION      = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        $this->MODULE_NAME         = Loc::getMessage('PARFEON_ER_MODULE_NAME');
        $this->MODULE_DESCRIPTION  = Loc::getMessage('PARFEON_ER_MODULE_DESC');
        $this->PARTNER_NAME        = 'Vadim Palgov';
        $this->PARTNER_URI         = 'parfeon.dev';
    }

    // ─── DoInstall / DoUninstall ──────────────────────────────────────────────

    public function DoInstall()
    {
        global $APPLICATION, $step;
        $step = max(1, (int)$step);

        Loader::includeModule('crm');
        Loader::includeModule('iblock');

        $title = Loc::getMessage('PARFEON_ER_INSTALL_TITLE');

        // Шаг 1: показываем форму выбора инфоблока
        if ($step === 1) {
            $GLOBALS['wi_structureIblocks'] = $this->getStructureIblocks();
            $GLOBALS['wi_allIblocks']       = $this->getAllIblocks();
            $APPLICATION->IncludeAdminFile($title, __DIR__ . '/step1.php');

        // Шаг 2: обрабатываем выбор инфоблока, показываем форму отделов
        } elseif ($step === 2) {
            $errors = $this->processStep1();
            if (!empty($errors)) {
                $GLOBALS['wi_errors']           = $errors;
                $GLOBALS['wi_structureIblocks'] = $this->getStructureIblocks();
                $GLOBALS['wi_allIblocks']       = $this->getAllIblocks();
                $APPLICATION->IncludeAdminFile($title, __DIR__ . '/step1.php');
            } else {
                $iblockId = (int)Option::get($this->MODULE_ID, 'STRUCTURE_IBLOCK_ID', 0);
                $GLOBALS['wi_departments'] = $this->getDepartments($iblockId);
                $GLOBALS['wi_basicDepts']  = self::$basicDepartments;
                $APPLICATION->IncludeAdminFile($title, __DIR__ . '/step2.php');
            }

        // Шаг 3: обрабатываем отделы, показываем форму привязки смарт-процессов
        } elseif ($step === 3) {
            $this->processStep2();
            $GLOBALS['wi_dynamicTypes'] = $this->getDynamicTypes();
            $GLOBALS['wi_allIblocks']   = $this->getAllIblocks();
            $GLOBALS['wi_savedSpIds']   = $this->getOptionSpIds();
            $GLOBALS['wi_spLabels']     = self::$spLabels;
            $APPLICATION->IncludeAdminFile($title, __DIR__ . '/step3.php');

        // Шаг 4: обрабатываем привязку СП и создание полей
        } elseif ($step >= 4) {
            [$fieldResults, $hasErrors] = $this->processStep3();

            if ($hasErrors) {
                // Возвращаемся на шаг 3 с результатами
                $GLOBALS['wi_fieldResults'] = $fieldResults;
                $GLOBALS['wi_savedSpIds']   = $this->getOptionSpIds();
                $GLOBALS['wi_dynamicTypes'] = $this->getDynamicTypes();
                $GLOBALS['wi_allIblocks']   = $this->getAllIblocks();
                $GLOBALS['wi_spLabels']     = self::$spLabels;
                $APPLICATION->IncludeAdminFile($title, __DIR__ . '/step3.php');
            } else {
                RegisterModule($this->MODULE_ID);
                $APPLICATION->IncludeAdminFile($title, __DIR__ . '/step_done.php');
            }
        }
    }

    public function DoUninstall()
    {
        $this->DoUnInstallEvents();
        UnRegisterModule($this->MODULE_ID);
    }

    public function DoInstallEvents() {}
    public function DoUnInstallEvents() {}

    // ─── Обработка шагов ─────────────────────────────────────────────────────

    private function processStep1(): array
    {
        $errors = [];
        $action = (string)($_REQUEST['action'] ?? '');

        if ($action === 'select') {
            $iblockId = (int)($_REQUEST['iblock_id'] ?? 0);
            if ($iblockId > 0) {
                Option::set($this->MODULE_ID, 'STRUCTURE_IBLOCK_ID', $iblockId);
            } else {
                $errors[] = 'Выберите инфоблок из списка';
            }
        } elseif ($action === 'create') {
            $name = trim((string)($_REQUEST['iblock_name'] ?? ''));
            if ($name === '') {
                $errors[] = 'Введите название инфоблока';
            } else {
                $newId = $this->createStructureIblock($name);
                if ($newId > 0) {
                    Option::set($this->MODULE_ID, 'STRUCTURE_IBLOCK_ID', $newId);
                } else {
                    $errors[] = 'Ошибка создания инфоблока. Убедитесь, что тип «structure» существует.';
                }
            }
        } else {
            $errors[] = 'Неизвестное действие';
        }

        return $errors;
    }

    private function processStep2(): void
    {
        $iblockId = (int)Option::get($this->MODULE_ID, 'STRUCTURE_IBLOCK_ID', 0);
        if ($iblockId <= 0) {
            return;
        }
        foreach ((array)($_REQUEST['basic_depts'] ?? []) as $deptName) {
            $deptName = trim((string)$deptName);
            if ($deptName !== '') {
                $this->createDepartment($iblockId, $deptName);
            }
        }
    }

    private function processStep3(): array
    {
        $spIds = [
            'ER'   => (int)($_REQUEST['er_entity_type_id']   ?? 0),
            'AP'   => (int)($_REQUEST['ap_entity_type_id']   ?? 0),
            'ALP'  => (int)($_REQUEST['alp_entity_type_id']  ?? 0),
            'ALPU' => (int)($_REQUEST['alpu_entity_type_id'] ?? 0),
        ];
        $typesIblockId = (int)($_REQUEST['types_iblock_id'] ?? 0);

        // Валидация
        $errors = [];
        foreach ($spIds as $key => $id) {
            if ($id <= 0) {
                $errors[] = 'Выберите смарт-процесс для «' . self::$spLabels[$key] . '»';
            }
        }
        if (count(array_unique($spIds)) < 4) {
            $errors[] = 'Каждый смарт-процесс должен быть выбран только один раз';
        }
        if (!empty($errors)) {
            $GLOBALS['wi_errors'] = $errors;
            return [[], true];
        }

        // Сохраняем entityTypeId в опции
        foreach ([
            'ER'   => 'ER_SMART_PROCESS_ID',
            'AP'   => 'AP_SMART_PROCESS_ID',
            'ALP'  => 'ALP_SMART_PROCESS_ID',
            'ALPU' => 'ALPU_SMART_PROCESS_ID',
        ] as $key => $optKey) {
            Option::set($this->MODULE_ID, $optKey, $spIds[$key]);
        }

        // Автоопределение стадий по семантике
        $this->detectAndSaveStages($spIds['ER'], 'ER');
        $this->detectAndSaveStages($spIds['AP'], 'AP');

        // Проверка/создание UF-полей
        $fieldResults = [];
        $hasErrors    = false;

        foreach (['ER', 'AP', 'ALP'] as $spKey) {
            $entityTypeId = $spIds[$spKey];
            $schema       = $this->getFieldSchema($spKey, $entityTypeId, $typesIblockId, $spIds['AP']);
            $existing     = $this->getExistingUfFields($entityTypeId);
            $results      = [];

            foreach ($schema as $def) {
                $fn = $def['FIELD_NAME'];
                if (isset($existing[$fn])) {
                    $results[$fn] = 'exists';
                } elseif ($this->createUfField($def)) {
                    $results[$fn] = 'created';
                } else {
                    $results[$fn] = 'error';
                    $hasErrors    = true;
                }
            }
            $fieldResults[$spKey] = $results;
        }
        $fieldResults['ALPU'] = [];

        // Обновляем CRM_TYPE_NUMBER в Mapping-файлах
        $base = __DIR__ . '/../lib/Mapping/';
        $this->updateMappingNumber($base . 'EmployeeRequest.php', $spIds['ER']);
        $this->updateMappingNumber($base . 'Approvers.php',       $spIds['AP']);

        return [$fieldResults, $hasErrors];
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function getStructureIblocks(): array
    {
        $list = [];
        $res  = \CIBlock::GetList(['SORT' => 'ASC'], ['TYPE' => 'structure', 'ACTIVE' => 'Y']);
        while ($row = $res->Fetch()) {
            $list[] = $row;
        }
        return $list;
    }

    private function getAllIblocks(): array
    {
        $list = [];
        $res  = \CIBlock::GetList(['SORT' => 'ASC'], ['ACTIVE' => 'Y']);
        while ($row = $res->Fetch()) {
            $list[] = $row;
        }
        return $list;
    }

    private function createStructureIblock(string $name): int
    {
        $ib = new \CIBlock();
        return (int)$ib->Add([
            'NAME'           => $name,
            'CODE'           => 'company_structure',
            'IBLOCK_TYPE_ID' => 'structure',
            'ACTIVE'         => 'Y',
            'SORT'           => 500,
        ]);
    }

    private function getDepartments(int $iblockId): array
    {
        $list = [];
        $res  = \CIBlockSection::GetList(
            ['SORT' => 'ASC'],
            ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y'],
            false,
            ['ID', 'NAME', 'DEPTH_LEVEL']
        );
        while ($row = $res->Fetch()) {
            $list[] = $row;
        }
        return $list;
    }

    private function createDepartment(int $iblockId, string $name): int
    {
        $bs = new \CIBlockSection();
        return (int)$bs->Add([
            'IBLOCK_ID' => $iblockId,
            'NAME'      => $name,
            'ACTIVE'    => 'Y',
            'SORT'      => 500,
        ]);
    }

    private function getDynamicTypes(): array
    {
        $list = [];
        $res  = TypeTable::getList([
            'select' => ['ID', 'ENTITY_TYPE_ID', 'TITLE'],
            'order'  => ['TITLE' => 'ASC'],
        ]);
        while ($row = $res->fetch()) {
            $list[] = $row;
        }
        return $list;
    }

    private function getOptionSpIds(): array
    {
        return [
            'ER'   => (int)Option::get($this->MODULE_ID, 'ER_SMART_PROCESS_ID',   0),
            'AP'   => (int)Option::get($this->MODULE_ID, 'AP_SMART_PROCESS_ID',   0),
            'ALP'  => (int)Option::get($this->MODULE_ID, 'ALP_SMART_PROCESS_ID',  0),
            'ALPU' => (int)Option::get($this->MODULE_ID, 'ALPU_SMART_PROCESS_ID', 0),
        ];
    }

    private function detectAndSaveStages(int $entityTypeId, string $prefix): void
    {
        $stages = [];
        $res    = StatusTable::getList([
            'filter' => ['ENTITY_ID' => 'DYNAMIC_' . $entityTypeId],
            'order'  => ['SORT' => 'ASC'],
        ]);
        while ($row = $res->fetch()) {
            $stages[] = $row;
        }
        if (empty($stages)) {
            return;
        }

        Option::set($this->MODULE_ID, $prefix . '_START_STATUS', $stages[0]['STATUS_ID']);
        foreach ($stages as $s) {
            if ($s['SEMANTICS'] === 'S') {
                Option::set($this->MODULE_ID, $prefix . '_APPROVE_STATUS', $s['STATUS_ID']);
                break;
            }
        }
        foreach ($stages as $s) {
            if ($s['SEMANTICS'] === 'F') {
                Option::set($this->MODULE_ID, $prefix . '_REJECT_STATUS', $s['STATUS_ID']);
                break;
            }
        }
    }

    private function getFieldSchema(string $spKey, int $entityTypeId, int $typesIblockId, int $apEntityTypeId): array
    {
        $eid = 'CRM_DYNAMIC_' . $entityTypeId;
        $p   = 'UF_CRM_' . $entityTypeId . '_';

        $f = static function (string $eid, string $name, string $type, string $label, int $sort, string $mult = 'N', array $settings = []): array {
            $l = ['ru' => $label, 'en' => $label];
            return [
                'ENTITY_ID'         => $eid,
                'FIELD_NAME'        => $name,
                'USER_TYPE_ID'      => $type,
                'SORT'              => $sort,
                'MULTIPLE'          => $mult,
                'MANDATORY'         => 'N',
                'SHOW_FILTER'       => 'Y',
                'EDIT_FORM_LABEL'   => $l,
                'LIST_COLUMN_LABEL' => $l,
                'LIST_FILTER_LABEL' => $l,
                'SETTINGS'          => $settings,
            ];
        };

        $typeOpts  = ['IBLOCK_ID' => $typesIblockId, 'DISPLAY' => 'DIALOG', 'LIST_HEIGHT' => 1];
        $stageOpts = ['ENTITY_TYPE' => 'DYNAMIC_' . $apEntityTypeId];

        $common = [
            $f($eid, $p . 'TYPE',                 'iblock_element', 'Тип заявки',         100, 'N', $typeOpts),
            $f($eid, $p . 'DESCRIPTION',          'string',         'Описание',            110),
            $f($eid, $p . 'REASON_FOR_REJECTION', 'string',         'Причина отклонения',  120),
            $f($eid, $p . 'DATE_START',           'date',           'Дата начала',         130),
            $f($eid, $p . 'DATE_END',             'date',           'Дата окончания',      140),
        ];

        switch ($spKey) {
            case 'ER':
                return array_merge($common, [
                    $f($eid, $p . 'PROJECT_MANAGERS',   'employee', 'Руководители проекта', 150, 'Y'),
                    $f($eid, $p . 'HEAD_OF_DEPARTMENT', 'employee', 'Руководители отдела',  160, 'Y'),
                    $f($eid, $p . 'HR',                 'employee', 'HR менеджеры',         170, 'Y'),
                ]);
            case 'AP':
                return $common;
            case 'ALP':
                return [
                    $f($eid, $p . 'TYPE',  'iblock_element', 'Тип заявки',          100, 'N', $typeOpts),
                    $f($eid, $p . 'STAGE', 'crm_status',     'Стадия согласования', 110, 'N', $stageOpts),
                ];
            default:
                return [];
        }
    }

    private function getExistingUfFields(int $entityTypeId): array
    {
        $existing = [];
        $res      = \CUserTypeEntity::GetList([], ['ENTITY_ID' => 'CRM_DYNAMIC_' . $entityTypeId]);
        while ($row = $res->Fetch()) {
            $existing[$row['FIELD_NAME']] = true;
        }
        return $existing;
    }

    private function createUfField(array $def): bool
    {
        $uf = new \CUserTypeEntity();
        return (bool)$uf->Add($def);
    }

    private function updateMappingNumber(string $filePath, int $typeNumber): bool
    {
        $content = @file_get_contents($filePath);
        if ($content === false) {
            return false;
        }
        $updated = preg_replace(
            '/const CRM_TYPE_NUMBER\s*=\s*\d+\s*;/',
            'const CRM_TYPE_NUMBER = ' . $typeNumber . ';',
            $content
        );
        return file_put_contents($filePath, $updated) !== false;
    }
}
