<?php

use Bitrix\Main\DI\ServiceLocator;


$serviceLocator = ServiceLocator::getInstance();

$serviceLocator->addInstanceLazy('crm.service.container', [
    'className' => '\\Parfeon\\Er\\Container',
]);




