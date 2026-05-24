<?php
global $APPLICATION;

$structureIblocks = $GLOBALS['wi_structureIblocks'] ?? [];
$allIblocks       = $GLOBALS['wi_allIblocks']       ?? [];
$errors           = $GLOBALS['wi_errors']           ?? [];
$savedIblock      = (int)\Bitrix\Main\Config\Option::get('parfeon.er', 'STRUCTURE_IBLOCK_ID', 0);
$nextUrl          = $APPLICATION->GetCurPage() . '?lang=' . LANG . '&id=parfeon.er&install=Y&step=2';
?>
<style>
.wi-steps{display:flex;margin-bottom:24px}
.wi-step{flex:1;padding:9px 12px;background:#f0f0f0;border:1px solid #d0d0d0;text-align:center;font-size:13px;color:#777}
.wi-step+.wi-step{border-left:none}
.wi-step.wi-active{background:#1a7ab5;color:#fff;border-color:#1a7ab5}
.wi-step.wi-done{background:#7BD500;color:#fff;border-color:#5a9e00}
.wi-note{background:#e8f4fd;border:1px solid #b8d9f0;padding:9px 13px;border-radius:3px;margin-bottom:14px;font-size:13px}
.wi-warn{background:#fff3cd;border:1px solid #ffc107;padding:9px 13px;border-radius:3px;margin-bottom:14px;font-size:13px}
.wi-sep{border:none;border-top:1px solid #e0e0e0;margin:18px 0}
</style>

<div style="max-width:820px">

<div class="wi-steps">
    <div class="wi-step wi-active">1. Структура компании</div>
    <div class="wi-step">2. Отделы</div>
    <div class="wi-step">3. Смарт-процессы</div>
</div>

<?php if (!empty($errors)): ?>
    <div class="wi-warn"><?= implode('<br>', array_map('htmlspecialcharsbx', $errors)) ?></div>
<?php endif; ?>

<h2>Шаг 1. Инфоблок структуры компании</h2>
<p>Выберите инфоблок, в котором хранятся подразделения компании.</p>

<?php if (!empty($structureIblocks)): ?>
    <div class="wi-note">Найдены инфоблоки типа <b>«structure»</b> — скорее всего они содержат структуру компании.</div>
    <form method="post" action="<?= $nextUrl ?>">
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
    <hr class="wi-sep">
    <p style="color:#888;font-size:12px">Не подходит? Выберите из всех инфоблоков:</p>
<?php endif; ?>

<?php if (!empty($allIblocks)): ?>
    <form method="post" action="<?= $nextUrl ?>">
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
    <hr class="wi-sep">
<?php endif; ?>

<p style="<?= !empty($allIblocks) ? 'color:#888;font-size:12px' : '' ?>">
    <?= !empty($allIblocks) ? 'Создать новый инфоблок:' : 'Инфоблоков нет. Создать новый:' ?>
</p>
<form method="post" action="<?= $nextUrl ?>">
    <?= bitrix_sessid_post() ?>
    <input type="hidden" name="action" value="create">
    <table class="adm-detail-content-table edit-table">
        <tr>
            <td width="40%">Название нового инфоблока:</td>
            <td><input type="text" name="iblock_name" value="Структура компании" style="width:280px"></td>
        </tr>
    </table>
    <input type="submit" class="adm-btn adm-btn-save" value="Создать и продолжить →">
</form>

</div>
