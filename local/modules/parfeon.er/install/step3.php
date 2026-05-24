<?php
global $APPLICATION;

$dynamicTypes = $GLOBALS['wi_dynamicTypes'] ?? [];
$allIblocks   = $GLOBALS['wi_allIblocks']   ?? [];
$savedSpIds   = $GLOBALS['wi_savedSpIds']   ?? [];
$spLabels     = $GLOBALS['wi_spLabels']     ?? [];
$fieldResults = $GLOBALS['wi_fieldResults'] ?? [];
$errors       = $GLOBALS['wi_errors']       ?? [];
$nextUrl      = $APPLICATION->GetCurPage() . '?lang=' . LANG . '&id=parfeon.er&install=Y&step=4';

$spInputNames = [
    'ER'   => 'er_entity_type_id',
    'AP'   => 'ap_entity_type_id',
    'ALP'  => 'alp_entity_type_id',
    'ALPU' => 'alpu_entity_type_id',
];
$spFieldHints = [
    'ER'   => 'TYPE, DESCRIPTION, REASON_FOR_REJECTION, DATE_START, DATE_END, PROJECT_MANAGERS, HEAD_OF_DEPARTMENT, HR',
    'AP'   => 'TYPE, DESCRIPTION, REASON_FOR_REJECTION, DATE_START, DATE_END',
    'ALP'  => 'TYPE, STAGE',
    'ALPU' => '—',
];
$statusLabel = [
    'exists'  => '<span style="color:#888">≡ уже есть</span>',
    'created' => '<span style="color:#5a9e00;font-weight:bold">✓ создано</span>',
    'error'   => '<span style="color:#c00;font-weight:bold">✗ ошибка</span>',
];
?>
<style>
.wi-steps{display:flex;margin-bottom:24px}
.wi-step{flex:1;padding:9px 12px;background:#f0f0f0;border:1px solid #d0d0d0;text-align:center;font-size:13px;color:#777}
.wi-step+.wi-step{border-left:none}
.wi-step.wi-active{background:#1a7ab5;color:#fff;border-color:#1a7ab5}
.wi-step.wi-done{background:#7BD500;color:#fff;border-color:#5a9e00}
.wi-card{border:1px solid #ddd;border-radius:3px;padding:10px 14px;margin-bottom:8px;background:#fafafa}
.wi-card h4{margin:0 0 6px;font-size:13px}
.wi-warn{background:#fff3cd;border:1px solid #ffc107;padding:9px 13px;border-radius:3px;margin-bottom:14px;font-size:13px}
.wi-sep{border:none;border-top:1px solid #e0e0e0;margin:18px 0}
</style>

<div style="max-width:820px">

<div class="wi-steps">
    <div class="wi-step wi-done">1. Структура компании</div>
    <div class="wi-step wi-done">2. Отделы</div>
    <div class="wi-step wi-active">3. Смарт-процессы</div>
</div>

<?php if (!empty($errors)): ?>
    <div class="wi-warn"><?= implode('<br>', array_map('htmlspecialcharsbx', $errors)) ?></div>
<?php endif; ?>

<?php if (!empty($fieldResults)): ?>

    <?php
    $hasFieldErrors = false;
    foreach ($fieldResults as $rows) {
        if (in_array('error', $rows, true)) {
            $hasFieldErrors = true;
            break;
        }
    }
    ?>

    <?php if ($hasFieldErrors): ?>
        <div class="wi-warn">Некоторые поля не удалось создать. Проверьте ошибки ниже и повторите.</div>
    <?php endif; ?>

    <?php foreach ($fieldResults as $spKey => $fields): ?>
        <div class="wi-card">
            <h4><?= htmlspecialcharsbx($spLabels[$spKey] ?? $spKey) ?></h4>
            <?php if (empty($fields)): ?>
                <span style="color:#aaa;font-size:12px">Нет обязательных полей</span>
            <?php else: ?>
                <table style="font-size:12px;border-collapse:collapse">
                    <?php foreach ($fields as $fieldName => $status): ?>
                        <tr>
                            <td style="padding:1px 12px 1px 0;color:#555"><?= htmlspecialcharsbx($fieldName) ?></td>
                            <td><?= $statusLabel[$status] ?? htmlspecialcharsbx($status) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <?php if ($hasFieldErrors): ?>
        <hr class="wi-sep">
        <p>Повторить с теми же смарт-процессами:</p>
        <form method="post" action="<?= $nextUrl ?>">
            <?= bitrix_sessid_post() ?>
            <?php foreach ($spInputNames as $key => $inputName): ?>
                <input type="hidden" name="<?= $inputName ?>" value="<?= (int)($savedSpIds[$key] ?? 0) ?>">
            <?php endforeach; ?>
            <input type="hidden" name="types_iblock_id" value="0">
            <input type="submit" class="adm-btn adm-btn-save" value="Повторить →">
        </form>
        <hr class="wi-sep">
        <p style="color:#888;font-size:12px">Или выберите другие смарт-процессы:</p>
    <?php endif; ?>

<?php endif; ?>

<?php if (empty($fieldResults) || !empty($hasFieldErrors)): ?>

<h2><?= empty($fieldResults) ? 'Шаг 3. Привязка смарт-процессов' : 'Изменить выбор' ?></h2>

<?php if (empty($dynamicTypes)): ?>
    <div class="wi-warn">
        Смарт-процессов не найдено. Создайте четыре смарт-процесса в
        <a href="/bitrix/admin/crm_smart_process_list.php?lang=<?= LANG ?>">CRM → Смарт-процессы</a>
        и вернитесь сюда.
    </div>
<?php else: ?>

    <p>Укажите, какой смарт-процесс выполняет каждую роль. Установщик проверит поля и создаст отсутствующие.</p>

    <form method="post" action="<?= $nextUrl ?>">
        <?= bitrix_sessid_post() ?>

        <table class="adm-detail-content-table edit-table" style="margin-bottom:16px">
            <thead>
                <tr>
                    <th style="width:22%">Роль</th>
                    <th style="width:40%">Смарт-процесс</th>
                    <th style="font-weight:normal;color:#777;font-size:11px">Поля для проверки</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($spLabels as $key => $label):
                $saved = (int)($savedSpIds[$key] ?? 0);
            ?>
                <tr>
                    <td><strong><?= htmlspecialcharsbx($label) ?></strong></td>
                    <td>
                        <select name="<?= $spInputNames[$key] ?>" style="width:100%">
                            <option value="">— выберите —</option>
                            <?php foreach ($dynamicTypes as $type): ?>
                                <option value="<?= (int)$type['ENTITY_TYPE_ID'] ?>"
                                    <?= (int)$type['ENTITY_TYPE_ID'] === $saved ? 'selected' : '' ?>>
                                    <?= htmlspecialcharsbx($type['TITLE']) ?>
                                    (entityTypeId: <?= (int)$type['ENTITY_TYPE_ID'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td style="color:#777;font-size:11px"><?= htmlspecialcharsbx($spFieldHints[$key]) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <hr class="wi-sep">

        <table class="adm-detail-content-table edit-table" style="margin-bottom:20px">
            <tr>
                <td style="width:22%">
                    Инфоблок типов заявок
                    <div style="color:#888;font-size:11px;margin-top:2px">Справочник для поля «Тип заявки»</div>
                </td>
                <td>
                    <select name="types_iblock_id" style="min-width:280px">
                        <option value="0">— без привязки —</option>
                        <?php foreach ($allIblocks as $ib): ?>
                            <option value="<?= (int)$ib['ID'] ?>">
                                [<?= (int)$ib['ID'] ?>] <?= htmlspecialcharsbx($ib['NAME']) ?>
                                (<?= htmlspecialcharsbx($ib['IBLOCK_TYPE_ID']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>

        <input type="submit" class="adm-btn adm-btn-save" value="Проверить и установить поля →">
    </form>

<?php endif; ?>
<?php endif; ?>

</div>
