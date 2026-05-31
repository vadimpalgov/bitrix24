<?php
use Bitrix\Main\Loader;
use Bitrix\Main\Context;
use Bitrix\Crm\Service\Container;
use Bitrix\Crm\Service;

$moduleId = 'parfeon.er';

if (!Loader::includeModule($moduleId) || !Loader::includeModule('crm')) {
    ShowError('Необходимые модули не установлены');
    return;
}

$request = Context::getCurrent()->getRequest();
$container = Container::getInstance();

/**
 * Получаем типы CRM
 */
$entityTypes = [];
$typeDataClass = $container->getDynamicTypeDataClass();
$types = $typeDataClass::getList([
        'select' => ['ENTITY_TYPE_ID', 'TITLE'],
        'order'  => ['TITLE' => 'ASC'],
]);
while ($type = $types->fetch()) {
    $entityTypes[$type['ENTITY_TYPE_ID']] = '[' . $type['ENTITY_TYPE_ID'] . '] ' . $type['TITLE'];
}

$getStages = function($entityTypeId) {
    if (!$entityTypeId) return [];
    $stages = [];
    $factory = Service\Container::getInstance()->getFactory((int)$entityTypeId);
    if ($factory) {
        foreach ($factory->getStages() as $stage) {
            $stages[$stage->getStatusId()] = $stage->getName();
        }
    }
    return $stages;
};

// Текущие ID для получения стадий
$erSmartId  = \COption::GetOptionString($moduleId, 'ER_SMART_PROCESS_ID');
$apSmartId  = \COption::GetOptionString($moduleId, 'AP_SMART_PROCESS_ID');
$alpSmartId = \COption::GetOptionString($moduleId, 'ALP_SMART_PROCESS_ID');
$ppSmartId  = \COption::GetOptionString($moduleId, 'PP_SMART_PROCESS_ID');
$erStages  = $getStages($erSmartId);
$apStages  = $getStages($apSmartId);

/**
 * Описание всех настроек (для автоматизации сохранения)
 */
$allOptions = [
        'main' => [
                'ER_SMART_PROCESS_ID' => 'Смарт-процесс для Заявок',
                'ER_START_STATUS'     => 'Начальный статус (ER)',
                'ER_APPROVE_STATUS'   => 'Статус одобрения (ER)',
                'ER_REJECT_STATUS'    => 'Статус отклонения (ER)',

                'AP_SMART_PROCESS_ID' => 'Смарт-процесс для Согласующих',
                'AP_START_STATUS'     => 'Начальный статус (AP)',
                'AP_APPROVE_STATUS'   => 'Статус одобрения (AP)',
                'AP_REJECT_STATUS'    => 'Статус отклонения (AP)',

                'ALP_SMART_PROCESS_ID' => 'Смарт-процесс для Профилей согласования',
                'PP_SMART_PROCESS_ID'  => 'Смарт-процесс для Участников профиля',

            // НОВЫЕ: Иерархия и Директор
                'LA_ADD_ALL_MANAGERS' => 'Включать всех руководителей по цепочке иерархии',
                'LA_EXCLUDE_DIRECTOR' => 'Исключить генерального директора из списка',
                'LA_FORCE_DIRECTOR_IF_MANAGER' => 'Добавлять директора, если заявитель сам является руководителем',
        ],
        'leave' => [
                'LA_ENABLE_MIN_DAYS_CHECK' => 'Включить проверку минимального срока',
                'LA_MIN_DAYS'              => 'Минимальная длительность отпуска (дней)',
                'LA_MIN_DAYS_BEFORE_START' => 'Минимальный срок подачи (дней до начала отпуска)',
        ]
];

/**
 * Сохранение
 */
if ($request->isPost() && check_bitrix_sessid()) {
    foreach ($allOptions as $group => $items) {
        foreach ($items as $optionName => $title) {
            $val = $request->getPost($optionName);

            // Логика для чекбоксов (если в массиве нет, значит N)
            if (strpos($optionName, 'ENABLE') !== false || strpos($optionName, 'ADD') !== false || strpos($optionName, 'EXCLUDE') !== false || strpos($optionName, 'FORCE') !== false) {
                $val = ($val === 'Y') ? 'Y' : 'N';
            }

            if ($val !== null) {
                \COption::SetOptionString($moduleId, $optionName, $val);
            }
        }
    }
    LocalRedirect($request->getRequestUri()); // Чтобы не было повторной отправки формы
}


$tabs = [
        ['DIV' => 'main', 'TAB' => 'Настройки согласования', 'TITLE' => 'Иерархия и Смарт-процессы'],
        ['DIV' => 'leave', 'TAB' => 'Параметры отпуска', 'TITLE' => 'Валидация заявок на отпуск'],
];
$tabControl = new CAdminTabControl('tabControl', $tabs);
?>

<form method="post">
    <?= bitrix_sessid_post(); ?>
    <?php
    $tabControl->Begin();
    $tabControl->BeginNextTab();

// Хелпер для Select
    $renderSelect = function($name, $title, $items) use ($moduleId) {
        $current = \COption::GetOptionString($moduleId, $name);
        ?>
        <tr>
            <td width="40%"><?= htmlspecialcharsbx($title) ?>:</td>
            <td width="60%">
                <select name="<?= $name ?>">
                    <option value="">— не выбрано —</option>
                    <?php foreach ($items as $id => $val): ?>
                        <option value="<?= $id ?>" <?= ((string)$id === (string)$current ? 'selected' : '') ?>>
                            <?= htmlspecialcharsbx($val) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <?php
    };

    // Хелпер для Checkbox
    $renderCheckbox = function($name, $title) use ($moduleId) {
        $val = \COption::GetOptionString($moduleId, $name, 'N');
        ?>
        <tr>
            <td width="40%"><?= htmlspecialcharsbx($title) ?>:</td>
            <td width="60%">
                <input type="checkbox" name="<?= $name ?>" value="Y" <?= ($val === 'Y' ? 'checked' : '') ?>>
            </td>
        </tr>
        <?php
    };
    
    // Группа 1: Смарт-процессы
    $renderSelect('ER_SMART_PROCESS_ID', $allOptions['main']['ER_SMART_PROCESS_ID'], $entityTypes);
    if ($erSmartId) {
        $renderSelect('ER_START_STATUS', $allOptions['main']['ER_START_STATUS'], $erStages);
        $renderSelect('ER_APPROVE_STATUS', $allOptions['main']['ER_APPROVE_STATUS'], $erStages);
        $renderSelect('ER_REJECT_STATUS', $allOptions['main']['ER_REJECT_STATUS'], $erStages);
    }

    echo '<tr class="heading"><td colspan="2">Логика подбора согласующих</td></tr>';

    $renderSelect('AP_SMART_PROCESS_ID', $allOptions['main']['AP_SMART_PROCESS_ID'], $entityTypes);
    if ($apSmartId) {
        $renderSelect('AP_START_STATUS', $allOptions['main']['AP_START_STATUS'], $apStages);
        $renderSelect('AP_APPROVE_STATUS', $allOptions['main']['AP_APPROVE_STATUS'], $apStages);
        $renderSelect('AP_REJECT_STATUS', $allOptions['main']['AP_REJECT_STATUS'], $apStages);
    }

    echo '<tr class="heading"><td colspan="2">Профили согласования</td></tr>';

    $renderSelect('ALP_SMART_PROCESS_ID', $allOptions['main']['ALP_SMART_PROCESS_ID'], $entityTypes);
    $renderSelect('PP_SMART_PROCESS_ID',  $allOptions['main']['PP_SMART_PROCESS_ID'],  $entityTypes);

    echo '<tr class="heading"><td colspan="2">Логика иерархии руководителей</td></tr>';

    $renderCheckbox('LA_ADD_ALL_MANAGERS', $allOptions['main']['LA_ADD_ALL_MANAGERS']);
    $renderCheckbox('LA_EXCLUDE_DIRECTOR', $allOptions['main']['LA_EXCLUDE_DIRECTOR']);
    $renderCheckbox('LA_FORCE_DIRECTOR_IF_MANAGER', $allOptions['main']['LA_FORCE_DIRECTOR_IF_MANAGER']);

    $tabControl->BeginNextTab();

    // Вкладка Отпуск
    $renderCheckbox('LA_ENABLE_MIN_DAYS_CHECK', $allOptions['leave']['LA_ENABLE_MIN_DAYS_CHECK']);
    ?>
    <tr>
        <td><?= $allOptions['leave']['LA_MIN_DAYS'] ?>:</td>
        <td>
            <input type="number" name="LA_MIN_DAYS" value="<?= \COption::GetOptionString($moduleId, 'LA_MIN_DAYS', 14) ?>" min="1">
        </td>
    </tr>
    <tr>
        <td><?= $allOptions['leave']['LA_MIN_DAYS_BEFORE_START'] ?>:</td>
        <td>
            <input type="number" name="LA_MIN_DAYS_BEFORE_START" value="<?= \COption::GetOptionString($moduleId, 'LA_MIN_DAYS_BEFORE_START', 7) ?>" min="0">
        </td>
    </tr>

    <?php
    $tabControl->Buttons();
    ?>
    <input type="submit" class="adm-btn-save" value="Сохранить настройки">
    <?php $tabControl->End(); ?>
</form>