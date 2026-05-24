<?php
global $APPLICATION;

$departments = $GLOBALS['wi_departments'] ?? [];
$basicDepts  = $GLOBALS['wi_basicDepts']  ?? [];
$nextUrl     = $APPLICATION->GetCurPage() . '?lang=' . LANG . '&id=parfeon.er&install=Y&step=3';
?>
<style>
.wi-steps{display:flex;margin-bottom:24px}
.wi-step{flex:1;padding:9px 12px;background:#f0f0f0;border:1px solid #d0d0d0;text-align:center;font-size:13px;color:#777}
.wi-step+.wi-step{border-left:none}
.wi-step.wi-active{background:#1a7ab5;color:#fff;border-color:#1a7ab5}
.wi-step.wi-done{background:#7BD500;color:#fff;border-color:#5a9e00}
.wi-note{background:#e8f4fd;border:1px solid #b8d9f0;padding:9px 13px;border-radius:3px;margin-bottom:14px;font-size:13px}
.wi-warn{background:#fff3cd;border:1px solid #ffc107;padding:9px 13px;border-radius:3px;margin-bottom:14px;font-size:13px}
</style>

<div style="max-width:820px">

<div class="wi-steps">
    <div class="wi-step wi-done">1. Структура компании</div>
    <div class="wi-step wi-active">2. Отделы</div>
    <div class="wi-step">3. Смарт-процессы</div>
</div>

<h2>Шаг 2. Отделы</h2>

<?php if (!empty($departments)): ?>
    <div class="wi-note">
        В инфоблоке уже есть <b><?= count($departments) ?></b> подразделений — заполнять не нужно.
    </div>
    <form method="post" action="<?= $nextUrl ?>">
        <?= bitrix_sessid_post() ?>
        <input type="submit" class="adm-btn adm-btn-save" value="Продолжить →">
    </form>
<?php else: ?>
    <div class="wi-warn">Инфоблок пуст. Выберите базовые отделы для создания:</div>
    <form method="post" action="<?= $nextUrl ?>">
        <?= bitrix_sessid_post() ?>
        <table class="adm-detail-content-table edit-table">
            <?php foreach ($basicDepts as $dept): ?>
                <tr><td>
                    <label>
                        <input type="checkbox" name="basic_depts[]"
                               value="<?= htmlspecialcharsbx($dept) ?>" checked>
                        <?= htmlspecialcharsbx($dept) ?>
                    </label>
                </td></tr>
            <?php endforeach; ?>
        </table>
        <br>
        <input type="submit" class="adm-btn adm-btn-save" value="Создать выбранные и продолжить →">
        &nbsp;
        <input type="submit" class="adm-btn" value="Пропустить">
    </form>
<?php endif; ?>

</div>
