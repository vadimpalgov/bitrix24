<?php
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

$_SERVER['DOCUMENT_ROOT'] = '/var/www/bitrix';
$_SERVER['HTTP_HOST']     = 'localhost';
$_SERVER['REQUEST_URI']   = '/';
$_SERVER['SERVER_NAME']   = 'localhost';
$_SERVER['SERVER_PORT']   = '80';
$_SERVER['HTTPS']         = 'off';

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
\Bitrix\Main\Loader::includeModule('crm');

$factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(1044); // RFQ

// 1. Методы фабрики для чтения
echo "=== Factory get-методы ===\n";
foreach (get_class_methods($factory) as $m) {
    if (preg_match('/^(getItem|getItems|getList)/i', $m)) {
        echo "  $m\n";
    }
}

// 2. getItem() напрямую
echo "\n=== getItem(1) ===\n";
$item = $factory->getItem(1);
if ($item) {
    $data = $item->getData();
    foreach ($data as $k => $v) {
        if (is_object($v)) {
            echo "  $k => " . get_class($v) . " → " . (string)$v . "\n";
        } elseif (is_array($v)) {
            echo "  $k => array[" . count($v) . "]\n";
        } else {
            echo "  $k => " . var_export($v, true) . "\n";
        }
    }
} else {
    echo "  item not found\n";
    // Попробуем первый существующий
    $items = $factory->getItems(['limit' => 1]);
    echo "  total via getItems(limit=1): " . count($items) . "\n";
    if (!empty($items)) {
        echo "  first ID: " . $items[0]->getId() . "\n";
        $data = $items[0]->getData();
        foreach (array_slice($data, 0, 10, true) as $k => $v) {
            if (is_object($v)) $str = get_class($v);
            elseif (is_array($v)) $str = 'array[' . count($v) . ']';
            else $str = var_export($v, true);
            echo "  $k => $str\n";
        }
    }
}

// 3. Проверяем setFromCompatibleData vs set
echo "\n=== Item set/get/setFromCompatibleData ===\n";
$newItem = $factory->createItem();
$reflection = new ReflectionClass($newItem);
foreach (['set', 'get', 'setFromCompatibleData', 'getData'] as $m) {
    if ($reflection->hasMethod($m)) {
        $ref = $reflection->getMethod($m);
        $params = array_map(fn($p) => '$' . $p->getName(), $ref->getParameters());
        echo "  $m(" . implode(', ', $params) . ")\n";
    }
}
