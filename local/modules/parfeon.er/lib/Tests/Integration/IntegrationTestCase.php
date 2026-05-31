<?php

declare(strict_types=1);

namespace Parfeon\Er\Tests\Integration;

use Bitrix\Crm\Item;
use Bitrix\Crm\Service\Container as CrmContainer;
use Bitrix\Crm\Service\Factory;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Result;
use Bitrix\Main\Type\Date;
use Parfeon\Er\Mapping\Approvers;
use Parfeon\Er\Mapping\EmployeeRequest;
use PHPUnit\Framework\TestCase;

abstract class IntegrationTestCase extends TestCase
{
    protected const MODULE_ID = 'parfeon.er';

    protected int    $erEntityTypeId;
    protected int    $apEntityTypeId;
    protected string $erStartStatus;
    protected string $erApproveStatus;
    protected string $erRejectStatus;
    protected string $apStartStatus;
    protected string $apApproveStatus;
    protected string $apRejectStatus;

    protected Factory $erFactory;
    protected Factory $apFactory;

    private array $cleanUpStack = [];

    // ── setUp / tearDown ──────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        $this->erEntityTypeId  = (int)Option::get(self::MODULE_ID, 'ER_SMART_PROCESS_ID');
        $this->apEntityTypeId  = (int)Option::get(self::MODULE_ID, 'AP_SMART_PROCESS_ID');
        $this->erStartStatus   = Option::get(self::MODULE_ID, 'ER_START_STATUS');
        $this->erApproveStatus = Option::get(self::MODULE_ID, 'ER_APPROVE_STATUS');
        $this->erRejectStatus  = Option::get(self::MODULE_ID, 'ER_REJECT_STATUS');
        $this->apStartStatus   = Option::get(self::MODULE_ID, 'AP_START_STATUS');
        $this->apApproveStatus = Option::get(self::MODULE_ID, 'AP_APPROVE_STATUS');
        $this->apRejectStatus  = Option::get(self::MODULE_ID, 'AP_REJECT_STATUS');

        if (!$this->erEntityTypeId || !$this->apEntityTypeId) {
            $this->markTestSkipped(
                'Модуль parfeon.er не настроен: ER_SMART_PROCESS_ID или AP_SMART_PROCESS_ID не заданы.'
            );
        }

        $this->erFactory = CrmContainer::getInstance()->getFactory($this->erEntityTypeId);
        $this->apFactory = CrmContainer::getInstance()->getFactory($this->apEntityTypeId);

        if (!$this->erFactory || !$this->apFactory) {
            $this->markTestSkipped('Фабрики ER или AP не найдены.');
        }
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->cleanUpStack) as $fn) {
            try { $fn(); } catch (\Throwable) {}
        }
        $this->cleanUpStack = [];
        parent::tearDown();
    }

    // ── Пользовательский контекст ─────────────────────────────────────────────

    protected function actingAs(int $userId): void
    {
        global $USER;
        $USER = new \CUser();
        $USER->Authorize($userId);
    }

    // ── Создание / перевод ER ─────────────────────────────────────────────────

    protected function createEr(array $fields): int
    {
        $item = $this->erFactory->createItem();
        foreach ($fields as $field => $value) {
            $item->set($field, $value);
        }

        $result = $this->erFactory
            ->getAddOperation($item)
            ->disableCheckAccess()
            ->launch();

        $this->assertTrue($result->isSuccess(), implode('; ', $result->getErrorMessages()));

        $erId = $item->getId();
        $this->assertGreaterThan(0, $erId, 'ER не сохранён (ID=0)');

        $this->scheduleErDelete($erId);

        return $erId;
    }

    /**
     * Создаёт ER и сразу переводит его в ER_START_STATUS.
     * Используется во всех сценариях, где нужна «рабочая» заявка.
     */
    protected function createErInStartStatus(array $extraFields = []): int
    {
        $erId = $this->createEr(array_merge($this->defaultErFields(), $extraFields));
        $this->updateErStage($erId, $this->erStartStatus);
        return $erId;
    }

    protected function updateErStage(int $erId, string $stageId): void
    {
        $item = $this->erFactory->getItem($erId);
        $this->assertNotNull($item, "ER ID={$erId} не найден");

        $item->setStageId($stageId);

        $result = $this->erFactory
            ->getUpdateOperation($item)
            ->disableCheckAccess()
            ->launch();

        $this->assertTrue(
            $result->isSuccess(),
            "Ошибка обновления ER: " . implode('; ', $result->getErrorMessages()),
        );
    }

    // ── Работа с AP ───────────────────────────────────────────────────────────

    /**
     * Переводит AP в стадию, ожидает успех.
     */
    protected function updateApStage(int $apId, string $stageId, ?string $reason = null): void
    {
        $result = $this->tryUpdateApStage($apId, $stageId, $reason);
        $this->assertTrue(
            $result->isSuccess(),
            "Ошибка обновления AP: " . implode('; ', $result->getErrorMessages()),
        );
    }

    /**
     * Переводит AP в стадию, возвращает Result (не проваливает тест при ошибке).
     * Используется когда ожидается ошибка.
     */
    protected function tryUpdateApStage(int $apId, string $stageId, ?string $reason = null): Result
    {
        $item = $this->apFactory->getItem($apId);
        $this->assertNotNull($item, "AP ID={$apId} не найден");

        $item->setStageId($stageId);

        if ($reason !== null) {
            $item->set(Approvers::REASON_FOR_REJECTION, $reason);
        }

        return $this->apFactory
            ->getUpdateOperation($item)
            ->disableCheckAccess()
            ->launch();
    }

    /**
     * Одобряет AP указанного пользователя в указанной фазе.
     */
    protected function approvePhase(int $erId, int $userId, int $phase, ?int $order = null): void
    {
        $ap = $this->assertApExists($erId, $userId, $phase, $order);
        $this->updateApStage($ap->getId(), $this->apApproveStatus);
    }

    /**
     * Отклоняет AP указанного пользователя.
     * Возвращает Result для проверки.
     */
    protected function rejectPhase(int $erId, int $userId, int $phase, ?string $reason = null): Result
    {
        $ap = $this->assertApExists($erId, $userId, $phase);
        return $this->tryUpdateApStage($ap->getId(), $this->apRejectStatus, $reason);
    }

    // ── Поиск стадий ER ───────────────────────────────────────────────────────

    /**
     * Возвращает ID первой стадии ER, которая не является
     * START / APPROVE / REJECT (используется для теста «откат → повтор»).
     */
    protected function findNeutralErStage(): ?string
    {
        $special = [$this->erStartStatus, $this->erApproveStatus, $this->erRejectStatus];

        foreach ($this->erFactory->getStages() as $stage) {
            if (!in_array($stage->getStatusId(), $special, true)) {
                return $stage->getStatusId();
            }
        }

        return null;
    }

    // ── Assertion-хелперы ─────────────────────────────────────────────────────

    /** @return Item[] */
    protected function getApItems(int $erId): array
    {
        return $this->apFactory->getItems([
            'filter' => ['=PARENT_ID_' . $this->erEntityTypeId => $erId],
        ]);
    }

    protected function findAp(int $erId, int $userId, int $phase, ?int $order = null): ?Item
    {
        $filter = [
            '=PARENT_ID_' . $this->erEntityTypeId => $erId,
            '=ASSIGNED_BY_ID'                      => $userId,
            '=' . Approvers::PHASE                 => $phase,
        ];

        if ($order !== null) {
            $filter['=' . Approvers::ORDER] = $order;
        }

        $items = $this->apFactory->getItems(['filter' => $filter]);

        return $items[0] ?? null;
    }

    protected function assertApExists(int $erId, int $userId, int $phase, ?int $order = null): Item
    {
        $label = "AP(user={$userId}, phase={$phase}" . ($order !== null ? ", order={$order}" : '') . ")";
        $item  = $this->findAp($erId, $userId, $phase, $order);
        $this->assertNotNull($item, "Ожидали найти {$label}, но его нет");
        return $item;
    }

    protected function assertApNotExists(int $erId, int $userId, int $phase): void
    {
        $item = $this->findAp($erId, $userId, $phase);
        $this->assertNull($item, "Не ожидали AP(user={$userId}, phase={$phase}), но он есть");
    }

    protected function assertErStage(int $erId, string $expectedStage): void
    {
        $item = $this->erFactory->getItem($erId);
        $this->assertNotNull($item);
        $this->assertSame($expectedStage, $item->getStageId(), 'Стадия ER не совпадает');
    }

    protected function assertApCount(int $erId, int $expected): void
    {
        $this->assertCount(
            $expected,
            $this->getApItems($erId),
            "Ожидали {$expected} AP-элементов",
        );
    }

    // ── Тестовые данные ───────────────────────────────────────────────────────

    /**
     * Базовый набор полей для ER.
     * Включает тип «отпуск», даты с запасом и РП Орлов (ID=19).
     * Дочерние классы могут переопределить через $extraFields в createErInStartStatus().
     */
    protected function defaultErFields(): array
    {
        return [
            EmployeeRequest::TYPE             => $this->vacationTypeId(),
            EmployeeRequest::DATE_START       => $this->dateFromNow('+15 days'),
            EmployeeRequest::DATE_END         => $this->dateFromNow('+29 days'),
            EmployeeRequest::PROJECT_MANAGERS => [19], // Орлов
            EmployeeRequest::DESCRIPTION      => 'Интеграционный тест',
        ];
    }

    /**
     * ID элемента инфоблока «Заявка на отпуск».
     * Пропускает тест если элемент не найден.
     */
    protected function vacationTypeId(): int
    {
        static $id = null;

        if ($id === null) {
            $res = \CIBlockElement::GetList(
                [],
                ['ACTIVE' => 'Y', '%NAME' => 'отпуск'],
                false,
                ['nTopCount' => 1],
                ['ID'],
            );
            $row = $res->Fetch();
            $id  = $row ? (int)$row['ID'] : 0;
        }

        if ($id === 0) {
            $this->markTestSkipped('Элемент типа «отпуск» не найден в инфоблоке.');
        }

        return $id;
    }

    protected function dateFromNow(string $offset): Date
    {
        return new Date(
            (new \DateTime())->modify($offset)->format('d.m.Y'),
            'd.m.Y',
        );
    }

    // ── Очистка ───────────────────────────────────────────────────────────────

    protected function scheduleErDelete(int $erId): void
    {
        // Временно отключено — данные остаются в БД для ручной проверки
    }
}
