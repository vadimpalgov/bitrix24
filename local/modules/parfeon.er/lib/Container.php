<?php

namespace Parfeon\Er;

use Bitrix\Crm\Service\Factory;
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Loader;
use Bitrix\Crm\Service\Container as BitrixContainer;
use Bitrix\Main\Config\Option;
use Parfeon\Er\Factory\EmployeeRequestFactory;
use Parfeon\Er\Factory\ApproversFactory;
use Parfeon\Er\Factory\ApprovalProfileFactory;
use Parfeon\Er\Factory\ProfileParticipantsFactory;

Loader::requireModule('crm');

class Container extends BitrixContainer
{
    /**
     * Смарт-процессы с кастомными фабриками.
     * Ключ — код настройки модуля, значение — класс фабрики.
     */
    private const OPTION_TO_FACTORY = [
        'ER_SMART_PROCESS_ID'  => EmployeeRequestFactory::class,
        'AP_SMART_PROCESS_ID'  => ApproversFactory::class,
        'ALP_SMART_PROCESS_ID' => ApprovalProfileFactory::class,
        'PP_SMART_PROCESS_ID'  => ProfileParticipantsFactory::class,
    ];


    public function getFactory(int $entityTypeId): ?Factory
    {
        $factories = $this->getFactoriesMap();

        if (!isset($factories[$entityTypeId])) {
            return parent::getFactory($entityTypeId);
        }

        $identifier = static::getIdentifierByClassName(static::$dynamicFactoriesClassName, [$entityTypeId]);

        if (ServiceLocator::getInstance()->has($identifier)) {
            return ServiceLocator::getInstance()->get($identifier);
        }

        $type = $this->getTypeByEntityTypeId($entityTypeId);
        if (!$type) {
            return null;
        }

        $factoryClass = $factories[$entityTypeId];
        $factory = new $factoryClass($type);

        ServiceLocator::getInstance()->addInstance($identifier, $factory);

        return $factory;
    }

    /**
     * Возвращает маппинг entityTypeId → класс фабрики
     * только для смарт-процессов с кастомными фабриками.
     */
    protected function getFactoriesMap(): array
    {
        $factories = [];

        foreach (self::OPTION_TO_FACTORY as $optionName => $factoryClass) {
            $entityTypeId = (int)Option::get('parfeon.er', $optionName, 0);

            if ($entityTypeId > 0) {
                $factories[$entityTypeId] = $factoryClass;
            }
        }

        return $factories;
    }

    /**
     * Возвращает entityTypeId по коду настройки модуля.
     * Работает для всех смарт-процессов модуля, не только тех, что имеют кастомную фабрику.
     *
     * Пример: getEntityTypeIdByCode('ALP_SMART_PROCESS_ID') → 1054
     */
    public function getEntityTypeIdByCode(string $code): ?int
    {
        if (!array_key_exists($code, self::OPTION_TO_FACTORY)) {
            return null;
        }

        $id = (int)Option::get('parfeon.er', $code, 0);

        return $id > 0 ? $id : null;
    }
}