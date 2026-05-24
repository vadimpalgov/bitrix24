<?php

namespace Parfeon\Er;


use Bitrix\Crm\Service\Factory;
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Loader;
use Bitrix\Crm\Service\Container as BitrixContainer;
use Parfeon\Er\Factory\EmployeeRequestFactory;
use Parfeon\Er\Factory\ApproversFactory;

use Bitrix\Main\Config\Option;

Loader::requireModule('crm');

class Container extends BitrixContainer
{
    private const OPTION_TO_FACTORY = [
        'ER_SMART_PROCESS_ID' => EmployeeRequestFactory::class,
        'AP_SMART_PROCESS_ID' => ApproversFactory::class,

    ];

    public function getFactory(int $entityTypeId): ?Factory
    {
        $factories = $this->getFactoriesMap();


        if(isset($factories[$entityTypeId]))
        {
            $identifier = static::getIdentifierByClassName(static::$dynamicFactoriesClassName, [$entityTypeId]);

            if ( ServiceLocator::getInstance()->has($identifier) )
            {
                return ServiceLocator::getInstance()->get($identifier);
            }

            $type = $this->getTypeByEntityTypeId($entityTypeId);
            if ( !$type )
            {
                return null;
            }

            if (isset($factories[$entityTypeId])) {
                $factoryClass = $factories[$entityTypeId];
                $factory = new $factoryClass($type);
            }

            ServiceLocator::getInstance()->addInstance(
                $identifier,
                $factory
            );

            return $factory;
        }

        return parent::getFactory($entityTypeId);
    }

    protected function getFactoriesMap(): array
    {
        $factories = [];

        foreach (self::OPTION_TO_FACTORY as $optionName => $factoryClass) {
            $entityTypeId = (int)Option::get('parfeon.er', $optionName, 0);

            if ($entityTypeId <= 0) {
                continue;
            }

            $factories[$entityTypeId] = $factoryClass;
        }

        return $factories;
    }

    public function getEntityTypeIdByCode(string $code): ?int
    {
        if (!array_key_exists($code, self::OPTION_TO_FACTORY)) {
            return null;
        }

        $id = (int)Option::get('parfeon.er', $code, 0);

        return $id > 0 ? $id : null;
    }
}