<?php

use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;
use Bitrix\Crm\Model\Dynamic\TypeTable;
use Bitrix\Crm\StatusTable;

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php');

$moduleId = 'parfeon.er';

if (!$USER->IsAdmin()) {
    $APPLICATION->AuthForm('Доступ запрещён');
}

if (!Loader::includeModule('crm') || !Loader::includeModule('iblock')) {
    ShowError('Необходимые модули crm и iblock не установлены');
    require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php');
    die();
}

Loader::includeModule($moduleId);
$APPLICATION->SetTitle('Мастер настройки parfeon.er');

$request    = \Bitrix\Main\Context::getCurrent()->getRequest();
$sessionKey = 'parfeon_er_wizard';
$errors     = [];

if (!isset($_SESSION[$sessionKey])) {
    $_SESSION[$sessionKey] = ['step' => 1];
}

// ─── Helpers ────────────────────────────────────────────────────────────────

function sw_getStructureIblocks(): array
{
    $list = [];
    $res  = \CIBlock::GetList(['SORT' => 'ASC'], ['TYPE' => 'structure', 'ACTIVE' => 'Y']);
    while ($row = $res->Fetch()) {
        $list[] = $row;
    }
    return $list;
}

function sw_getAllIblocks(): array
{
    $list = [];
    $res  = \CIBlock::GetList(['SORT' => 'ASC'], ['ACTIVE' => 'Y']);
    while ($row = $res->Fetch()) {
        $list[] = $row;
    }
    return $list;
}

function sw_createStructureIblock(string $name): int
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

function sw_getDepartments(int $iblockId): array
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

function sw_createDepartment(int $iblockId, string $name, int $parentId = 0): int
{
    $bs = new \CIBlockSection();
    return (int)$bs->Add([
        'IBLOCK_ID'         => $iblockId,
        'IBLOCK_SECTION_ID' => $parentId ?: false,
        'NAME'              => $name,
        'ACTIVE'            => 'Y',
        'SORT'              => 500,
    ]);
}

function sw_createSmartProcess(string $title, array $stages): array
{
    $addResult = TypeTable::add([
        'TITLE'                       => $title,
        'IS_USE_IN_USERFIELD_ENABLED' => true,
    ]);

    if (!$addResult->isSuccess()) {
        return ['success' => false, 'errors' => $addResult->getErrorMessages()];
    }

    $typeId       = $addResult->getId();
    $row          = TypeTable::getById($typeId)->fetch();
    $entityTypeId = (int)$row['ENTITY_TYPE_ID'];
    $entityId     = 'DYNAMIC_' . $entityTypeId;
    $stageIds     = [];

    foreach ($stages as $i => $stage) {
        $statusId = $entityId . ':' . $stage['code'];
        StatusTable::add([
            'ENTITY_ID' => $entityId,
            'STATUS_ID' => $statusId,
            'NAME'      => $stage['name'],
            'NAME_INIT' => $stage['name'],
            'SORT'      => ($i + 1) * 10,
            'COLOR'     => $stage['color'],
            'SEMANTICS' => $stage['semantics'] ?? '',
        ]);
        $stageIds[$stage['code']] = $statusId;
    }

    return ['success' => true, 'entityTypeId' => $entityTypeId, 'stageIds' => $stageIds];
}

// ─── Config ─────────────────────────────────────────────────────────────────

$smartProcessesConfig = [
    'ER' => [
        'title'     => 'Заявки сотрудников',
        'optionId'  => 'ER_SMART_PROCESS_ID',
        'stageOpts' => ['NEW' => 'ER_START_STATUS', 'APPROVED' => 'ER_APPROVE_STATUS', 'REJECTED' => 'ER_REJECT_STATUS'],
        'stages'    => [
            ['code' => 'NEW',      'name' => 'Новая',           'color' => '#BBECF3', 'semantics' => ''],
            ['code' => 'PENDING',  'name' => 'На согласовании', 'color' => '#FED24F', 'semantics' => ''],
            ['code' => 'APPROVED', 'name' => 'Согласована',     'color' => '#7BD500', 'semantics' => 'S'],
            ['code' => 'REJECTED', 'name' => 'Отклонена',       'color' => '#FF5752', 'semantics' => 'F'],
        ],
    ],
    'AP' => [
        'title'     => 'Согласующие',
        'optionId'  => 'AP_SMART_PROCESS_ID',
        'stageOpts' => ['WAITING' => 'AP_START_STATUS', 'APPROVED' => 'AP_APPROVE_STATUS', 'REJECTED' => 'AP_REJECT_STATUS'],
        'stages'    => [
            ['code' => 'WAITING',  'name' => 'Ожидает',     'color' => '#BBECF3', 'semantics' => ''],
            ['code' => 'APPROVED', 'name' => 'Согласовано', 'color' => '#7BD500', 'semantics' => 'S'],
            ['code' => 'REJECTED', 'name' => 'Отклонено',   'color' => '#FF5752', 'semantics' => 'F'],
        ],
    ],
    'ALP' => [
        'title'     => 'Профили согласования',
        'optionId'  => 'ALP_SMART_PROCESS_ID',
        'stageOpts' => [],
        'stages'    => [
            ['code' => 'ACTIVE',  'name' => 'Активен', 'color' => '#7BD500', 'semantics' => ''],
            ['code' => 'ARCHIVE', 'name' => 'Архив',   'color' => '#ACB4BE', 'semantics' => 'F'],
        ],
    ],
    'ALPU' => [
        'title'     => 'Участники профиля',
        'optionId'  => 'ALPU_SMART_PROCESS_ID',
        'stageOpts' => [],
        'stages'    => [
            ['code' => 'ACTIVE',  'name' => 'Активен', 'color' => '#7BD500', 'semantics' => ''],
            ['code' => 'ARCHIVE', 'name' => 'Архив',   'color' => '#ACB4BE', 'semantics' => 'F'],
        ],
    ],
];

$basicDepartments = ['Руководство', 'Разработка', 'Маркетинг', 'Продажи', 'Финансы', 'HR', 'Административный отдел'];

// ─── POST обработка ──────────────────────────────────────────────────────────

if ($request->isPost() && check_bitrix_sessid()) {

    $currentStep = (int)$_SESSION[$sessionKey]['step'];

    if ($currentStep === 1) {
        $action = $request->getPost('action');

        if ($action === 'select') {
            $iblockId = (int)$request->getPost('iblock_id');
            if ($iblockId > 0) {
                $_SESSION[$sessionKey]['iblock_id'] = $iblockId;
                Option::set($moduleId, 'STRUCTURE_IBLOCK_ID', $iblockId);
                $_SESSION[$sessionKey]['step'] = 2;
            } else {
                $errors[] = 'Выберите инфоблок из списка';
            }
        } elseif ($action === 'create') {
            $iblockName = trim((string)$request->getPost('iblock_name'));
            if ($iblockName === '') {
                $errors[] = 'Введите название инфоблока';
            } else {
                $newId = sw_createStructureIblock($iblockName);
                if ($newId > 0) {
                    $_SESSION[$sessionKey]['iblock_id'] = $newId;
                    Option::set($moduleId, 'STRUCTURE_IBLOCK_ID', $newId);
                    $_SESSION[$sessionKey]['step'] = 2;
                } else {
                    $errors[] = 'Ошибка создания инфоблока. Убедитесь что тип «structure» существует.';
                }
            }
        }
    }

    elseif ($currentStep === 2) {
        $iblockId = (int)($_SESSION[$sessionKey]['iblock_id'] ?? 0);
        foreach ((array)$request->getPost('basic_depts') as $name) {
            $name = trim((string)$name);
            if ($name !== '') {
                sw_createDepartment($iblockId, $name);
            }
        }
        $_SESSION[$sessionKey]['step'] = 3;
    }

    elseif ($currentStep === 3) {
        $results    = [];
        $allSuccess = true;

        foreach ($smartProcessesConfig as $key => $config) {
            $existingId = (int)Option::get($moduleId, $config['optionId'], 0);
            if ($existingId > 0) {
                $results[$key] = ['status' => 'skipped', 'title' => $config['title'], 'entityTypeId' => $existingId];
                continue;
            }

            $r = sw_createSmartProcess($config['title'], $config['stages']);
            if ($r['success']) {
                Option::set($moduleId, $config['optionId'], $r['entityTypeId']);
                foreach ($config['stageOpts'] as $code => $optKey) {
                    if (isset($r['stageIds'][$code])) {
                        Option::set($moduleId, $optKey, $r['stageIds'][$code]);
                    }
                }
                $results[$key] = ['status' => 'created', 'title' => $config['title'], 'entityTypeId' => $r['entityTypeId']];
            } else {
                $allSuccess    = false;
                $results[$key] = ['status' => 'error', 'title' => $config['title'], 'errors' => $r['errors']];
            }
        }

        $_SESSION[$sessionKey]['step3_results'] = $results;

        if ($allSuccess) {
            $_SESSION[$sessionKey]['step'] = 4;
        }
    }
}

// ─── Данные для рендера ───────────────────────────────────────────────────────

$step         = (int)$_SESSION[$sessionKey]['step'];
$savedIblock  = (int)($_SESSION[$sessionKey]['iblock_id'] ?? Option::get($moduleId, 'STRUCTURE_IBLOCK_ID', 0));
$step3Results = $_SESSION[$sessionKey]['step3_results'] ?? [];
$formAction   = $APPLICATION->GetCurPage();

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php');
?>
<style>
.sw-steps{display:flex;margin-bottom:24px}
.sw-step{flex:1;padding:9px 12px;background:#f0f0f0;border:1px solid #d0d0d0;text-align:center;font-size:13px;color:#777}
.sw-step+.sw-step{border-left:none}
.sw-step.active{background:#1a7ab5;color:#fff;border-color:#1a7ab5}
.sw-step.done{background:#7BD500;color:#fff;border-color:#5a9e00}
.sw-card{border:1px solid #ddd;border-radius:3px;padding:11px 15px;margin-bottom:10px;background:#fafafa}
.sw-card h4{margin:0 0 7px;font-size:14px}
.sw-stages{margin:5px 0 0;padding-left:16px}
.sw-stages li{margin-bottom:3px;font-size:13px}
.sw-dot{display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:5px;vertical-align:middle}
.sw-ok{color:#5a9e00;font-weight:bold}
.sw-skip{color:#888}
.sw-err{color:#c00;font-weight:bold}
.sw-note{background:#e8f4fd;border:1px solid #b8d9f0;padding:9px 13px;border-radius:3px;margin-bottom:14px;font-size:13px}
.sw-warn{background:#fff3cd;border:1px solid #ffc107;padding:9px 13px;border-radius:3px;margin-bottom:14px;font-size:13px}
.sw-sep{border:none;border-top:1px solid #e0e0e0;margin:20px 0}
</style>

<div style="max-width:820px">

<?php if (!empty($errors)): ?>
    <div class="sw-warn"><?= implode('<br>', array_map('htmlspecialcharsbx', $errors)) ?></div>
<?php endif; ?>

<div class="sw-steps">
    <div class="sw-step <?= $step > 1 ? 'done' : ($step === 1 ? 'active' : '') ?>">1. Структура компании</div>
    <div class="sw-step <?= $step > 2 ? 'done' : ($step === 2 ? 'active' : '') ?>">2. Отделы</div>
    <div class="sw-step <?= $step >= 3 ? ($step === 4 ? 'done' : 'active') : '' ?>">3. Смарт-процессы</div>
</div>

<?php if ($step === 4):
    unset($_SESSION[$sessionKey]);
?>
    <div style="text-align:center;padding:36px 0">
        <div style="font-size:52px;color:#7BD500;line-height:1">✓</div>
        <h2 style="margin:14px 0 8px">Настройка завершена</h2>
        <p style="color:#555;margin-bottom:20px">Смарт-процессы созданы, настройки сохранены.</p>
        <a href="/bitrix/admin/settings.php?mid=<?= $moduleId ?>&lang=<?= LANG ?>" class="adm-btn adm-btn-save">
            Открыть настройки модуля
        </a>
    </div>

<?php elseif ($step === 1):
    $structureIblocks = sw_getStructureIblocks();
    $allIblocks       = sw_getAllIblocks();
?>
    <h2>Шаг 1. Инфоблок структуры компании</h2>
    <p>Выберите инфоблок, в котором хранятся подразделения компании.</p>

    <?php if (!empty($structureIblocks)): ?>
        <div class="sw-note">Найдены инфоблоки типа <b>«structure»</b> — скорее всего содержат структуру компании.</div>
        <form method="post" action="<?= $formAction ?>">
            <?= bitrix_sessid_post() ?>
            <input type="hidden" name="action" value="select">
            <table class="adm-detail-content-table edit-table">
                <tr>
                    <td width="40%">Инфоблок структуры:</td>
                    <td>
                        <select name="iblock_id" style="min-width:280px">
                            <option value="">— выберите —</option>
                            <?php foreach ($structureIblocks as $ib): ?>
                                <option value="<?= (int)$ib['ID'] ?>" <?= (int)$ib['ID'] === $savedIblock ? 'selected' : '' ?>>
                                    [<?= (int)$ib['ID'] ?>] <?= htmlspecialcharsbx($ib['NAME']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
            <input type="submit" class="adm-btn adm-btn-save" value="Выбрать →">
        </form>
        <hr class="sw-sep">
        <p style="color:#888;font-size:12px">Не тот инфоблок? Выберите из всех:</p>
    <?php endif; ?>

    <?php if (!empty($allIblocks)): ?>
        <form method="post" action="<?= $formAction ?>">
            <?= bitrix_sessid_post() ?>
            <input type="hidden" name="action" value="select">
            <table class="adm-detail-content-table edit-table">
                <tr>
                    <td width="40%">Любой инфоблок:</td>
                    <td>
                        <select name="iblock_id" style="min-width:280px">
                            <option value="">— выберите —</option>
                            <?php foreach ($allIblocks as $ib): ?>
                                <option value="<?= (int)$ib['ID'] ?>" <?= (int)$ib['ID'] === $savedIblock ? 'selected' : '' ?>>
                                    [<?= (int)$ib['ID'] ?>] <?= htmlspecialcharsbx($ib['NAME']) ?>
                                    (<?= htmlspecialcharsbx($ib['IBLOCK_TYPE_ID']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
            <input type="submit" class="adm-btn adm-btn-save" value="Выбрать →">
        </form>
        <hr class="sw-sep">
    <?php endif; ?>

    <p style="<?= !empty($allIblocks) ? 'color:#888;font-size:12px' : '' ?>">
        <?= !empty($allIblocks) ? 'Создать новый инфоблок:' : 'Инфоблоков нет. Создать новый:' ?>
    </p>
    <form method="post" action="<?= $formAction ?>">
        <?= bitrix_sessid_post() ?>
        <input type="hidden" name="action" value="create">
        <table class="adm-detail-content-table edit-table">
            <tr>
                <td width="40%">Название:</td>
                <td><input type="text" name="iblock_name" value="Структура компании" style="width:280px"></td>
            </tr>
        </table>
        <input type="submit" class="adm-btn adm-btn-save" value="Создать и продолжить →">
    </form>

<?php elseif ($step === 2):
    $departments = sw_getDepartments($savedIblock);
?>
    <h2>Шаг 2. Отделы</h2>

    <?php if (!empty($departments)): ?>
        <div class="sw-note">В инфоблоке уже есть <b><?= count($departments) ?></b> подразделений — заполнять не нужно.</div>
        <form method="post" action="<?= $formAction ?>">
            <?= bitrix_sessid_post() ?>
            <input type="submit" class="adm-btn adm-btn-save" value="Продолжить →">
        </form>
    <?php else: ?>
        <div class="sw-warn">Инфоблок пуст. Выберите базовые отделы для создания:</div>
        <form method="post" action="<?= $formAction ?>">
            <?= bitrix_sessid_post() ?>
            <table class="adm-detail-content-table edit-table">
                <?php foreach ($basicDepartments as $dept): ?>
                    <tr><td>
                        <label>
                            <input type="checkbox" name="basic_depts[]" value="<?= htmlspecialcharsbx($dept) ?>" checked>
                            <?= htmlspecialcharsbx($dept) ?>
                        </label>
                    </td></tr>
                <?php endforeach; ?>
            </table>
            <br>
            <input type="submit" class="adm-btn adm-btn-save" value="Создать выбранные и продолжить →">
            &nbsp;
            <button type="submit" class="adm-btn"
                onclick="this.form.querySelectorAll('[name=\'basic_depts[]\']').forEach(c=>c.checked=false);return true;">
                Пропустить
            </button>
        </form>
    <?php endif; ?>

<?php elseif ($step === 3): ?>

    <h2>Шаг 3. Создание смарт-процессов</h2>

    <?php if (empty($step3Results)): ?>
        <p>Будут созданы следующие смарт-процессы:</p>
        <?php foreach ($smartProcessesConfig as $config):
            $existingId = (int)Option::get($moduleId, $config['optionId'], 0);
        ?>
            <div class="sw-card">
                <h4>
                    <?= htmlspecialcharsbx($config['title']) ?>
                    <?php if ($existingId > 0): ?>
                        <span style="color:#888;font-weight:normal;font-size:12px">— уже создан (ID: <?= $existingId ?>), будет пропущен</span>
                    <?php endif; ?>
                </h4>
                <ul class="sw-stages">
                    <?php foreach ($config['stages'] as $stage): ?>
                        <li>
                            <span class="sw-dot" style="background:<?= $stage['color'] ?>"></span>
                            <?= htmlspecialcharsbx($stage['name']) ?>
                            <?php if ($stage['semantics'] === 'S'): ?>
                                <span style="color:#5a9e00;font-size:11px">(успех)</span>
                            <?php elseif ($stage['semantics'] === 'F'): ?>
                                <span style="color:#c00;font-size:11px">(провал)</span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
        <form method="post" action="<?= $formAction ?>" style="margin-top:16px">
            <?= bitrix_sessid_post() ?>
            <input type="submit" class="adm-btn adm-btn-save" value="Создать →">
        </form>

    <?php else: ?>
        <p>Результаты:</p>
        <?php foreach ($step3Results as $r): ?>
            <div class="sw-card">
                <?php if ($r['status'] === 'error'): ?>
                    <span class="sw-err">✗ <?= htmlspecialcharsbx($r['title']) ?></span>
                    <span style="color:#c00;font-size:12px"> — <?= htmlspecialcharsbx(implode(', ', $r['errors'] ?? [])) ?></span>
                <?php elseif ($r['status'] === 'skipped'): ?>
                    <span class="sw-skip">— <?= htmlspecialcharsbx($r['title']) ?> пропущен (ID: <?= $r['entityTypeId'] ?>)</span>
                <?php else: ?>
                    <span class="sw-ok">✓ <?= htmlspecialcharsbx($r['title']) ?> создан (ID: <?= $r['entityTypeId'] ?>)</span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <form method="post" action="<?= $formAction ?>" style="margin-top:12px">
            <?= bitrix_sessid_post() ?>
            <input type="submit" class="adm-btn adm-btn-save" value="Повторить →">
        </form>
    <?php endif; ?>

<?php endif; ?>

</div>

<?php require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php'); ?>
