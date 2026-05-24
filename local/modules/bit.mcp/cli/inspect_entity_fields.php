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

$rm = \Bitrix\Crm\Service\Container::getInstance()->getRelationManager();

foreach ([2, 1036] as $entityTypeId) {
    echo "\n=== ENTITY_TYPE_ID: $entityTypeId (" . \CCrmOwnerType::ResolveName($entityTypeId) . ") ===\n";

    // UserFields
    $ufEntityId = \CCrmOwnerType::ResolveUserFieldEntityID($entityTypeId);
    echo "UF entity: $ufEntityId\n";
    $ufs = \Bitrix\Main\UserFieldTable::getList([
        'filter' => ['ENTITY_ID' => $ufEntityId],
        'select' => ['FIELD_NAME', 'USER_TYPE_ID', 'MULTIPLE', 'MANDATORY'],
        'limit'  => 5,
    ])->fetchAll();
    foreach ($ufs as $uf) {
        echo "  {$uf['FIELD_NAME']} [{$uf['USER_TYPE_ID']}] multiple={$uf['MULTIPLE']} mandatory={$uf['MANDATORY']}\n";
    }

    // Parent relations
    echo "Parent relations:\n";
    try {
        $parents = $rm->getParentRelations($entityTypeId);
        if (empty($parents)) {
            echo "  (none)\n";
        }
        foreach ($parents as $rel) {
            $pid = $rel->getParentEntityTypeId();
            echo "  PARENT_ID_$pid (" . \CCrmOwnerType::ResolveName($pid) . ")\n";
        }
    } catch (\Throwable $e) {
        echo "  [!] " . $e->getMessage() . "\n";

        // Fallback: пробуем getRelations
        try {
            $rels = $rm->getRelations($entityTypeId);
            foreach ($rels as $rel) {
                if ($rel->getChildEntityTypeId() === $entityTypeId) {
                    $pid = $rel->getParentEntityTypeId();
                    echo "  (via getRelations) PARENT_ID_$pid (" . \CCrmOwnerType::ResolveName($pid) . ")\n";
                }
            }
        } catch (\Throwable $e2) {
            echo "  [!] fallback: " . $e2->getMessage() . "\n";
        }
    }
}
