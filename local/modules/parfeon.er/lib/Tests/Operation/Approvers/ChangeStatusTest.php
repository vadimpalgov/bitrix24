<?php

declare(strict_types=1);

namespace Parfeon\Er\Tests\Operation\Approvers;

use Bitrix\Crm\Service\Container as CrmContainer;
use Bitrix\Main\Config\Option;
use Bitrix\Main\DI\ServiceLocator;
use Parfeon\Er\Mapping\Approvers;
use Parfeon\Er\Operation\Approvers\ChangeStatus;
use Parfeon\Er\Tests\Support\FakeFactory;
use Parfeon\Er\Tests\Support\FakeItem;
use Parfeon\Er\Tests\Support\SpyCreateApprovers;
use PHPUnit\Framework\TestCase;

/**
 * Тест покрывает сценарий 01-full-cycle-without-profile.md
 *
 * Участники:
 *   Иванов    ID=5  — заявитель (ER)
 *   Сидорова  ID=21 — HR, фаза 1
 *   Круглова  ID=22 — HR, фаза 1
 *   Орлов     ID=19 — РП, фаза 2
 *   Дмитриев  ID=4  — рук. отдела, фаза 3, order=1
 *   Генералов ID=34 — директор, фаза 3, order=2
 */
class ChangeStatusTest extends TestCase
{
    private const MODULE_ID      = 'parfeon.er';
    private const ER_ENTITY_TYPE = 1046;
    private const AP_ENTITY_TYPE = 1050;

    private const AP_APPROVE = 'DT1050:APPROVE';
    private const AP_REJECT  = 'DT1050:REJECT';
    private const ER_APPROVE = 'DT1046:APPROVE';
    private const ER_REJECT  = 'DT1046:REJECT';

    private FakeFactory        $apFactory;
    private FakeFactory        $erFactory;
    private SpyCreateApprovers $spy;

    protected function setUp(): void
    {
        parent::setUp();

        Option::set(self::MODULE_ID, 'AP_APPROVE_STATUS',   self::AP_APPROVE);
        Option::set(self::MODULE_ID, 'AP_REJECT_STATUS',    self::AP_REJECT);
        Option::set(self::MODULE_ID, 'ER_APPROVE_STATUS',   self::ER_APPROVE);
        Option::set(self::MODULE_ID, 'ER_REJECT_STATUS',    self::ER_REJECT);
        Option::set(self::MODULE_ID, 'ER_SMART_PROCESS_ID', (string)self::ER_ENTITY_TYPE);

        $this->apFactory = new FakeFactory();
        $this->erFactory = new FakeFactory();

        CrmContainer::setFactory(self::AP_ENTITY_TYPE, $this->apFactory);
        CrmContainer::setFactory(self::ER_ENTITY_TYPE, $this->erFactory);

        $this->spy = new SpyCreateApprovers();
        ServiceLocator::getInstance()->addInstance(
            'parfeon.er.service.create.approvers',
            $this->spy,
        );
    }

    protected function tearDown(): void
    {
        Option::reset();
        CrmContainer::reset();
        ServiceLocator::reset();
        parent::tearDown();
    }

    // ── Вспомогательные билдеры ───────────────────────────────────────────────

    private function makeErItem(int $id = 100): FakeItem
    {
        return FakeItem::create($id, self::ER_ENTITY_TYPE);
    }

    private function makeApItem(int $id, int $userId, int $phase, string $stage, int $order = 0, bool $changed = false): FakeItem
    {
        return FakeItem::create($id, self::AP_ENTITY_TYPE)
            ->withStage($stage, $changed)
            ->withField('ASSIGNED_BY_ID', $userId)
            ->withField('PARENT_ID_' . self::ER_ENTITY_TYPE, 100)
            ->withField(Approvers::PHASE, $phase)
            ->withField(Approvers::ORDER, $order);
    }

    // ── Тест: стадия не изменилась — никаких действий ────────────────────────

    public function testStageNotChangedDoesNothing(): void
    {
        $ap = FakeItem::create(1, self::AP_ENTITY_TYPE)
            ->withStage(self::AP_APPROVE, changed: false);

        $result = (new ChangeStatus())->process($ap);

        $this->assertTrue($result->isSuccess());
        $this->spy->assertNoCalls($this);
    }

    // ── Тест: отклонение без причины возвращает ошибку ───────────────────────

    public function testRejectWithoutReasonReturnsError(): void
    {
        $ap = $this->makeApItem(id: 1, userId: 21, phase: 1, stage: self::AP_REJECT, changed: true);
        // Поле REASON_FOR_REJECTION не заполнено

        $result = (new ChangeStatus())->process($ap);

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('причина', mb_strtolower($result->getErrorMessages()[0]));
    }

    // ── Шаг 3: Сидорова (HR, фаза 1) одобряет → запуск фазы 2 ──────────────

    public function testStep3Phase1OneHrApprovesTriggersPhase2(): void
    {
        $this->erFactory->addItem($this->makeErItem());

        // Круглова ещё не решила
        $this->apFactory->addItem(
            $this->makeApItem(id: 2, userId: 22, phase: 1, stage: 'DT1050:NEW'),
        );

        // Сидорова одобряет
        $sidorova = $this->makeApItem(id: 1, userId: 21, phase: 1, stage: self::AP_APPROVE, changed: true);

        $result = (new ChangeStatus())->process($sidorova);

        $this->assertTrue($result->isSuccess());
        $this->spy->assertPhaseCreated($this, phase: 2, order: 1);
        $this->assertCount(1, $this->spy->calls);
    }

    // ── Шаг 3 (вариант reject): HR отклоняет → ER отклонена ─────────────────

    public function testPhase1HrRejectPropagatesRejectToEr(): void
    {
        $er = $this->makeErItem();
        $this->erFactory->addItem($er);

        $sidorova = $this->makeApItem(id: 1, userId: 21, phase: 1, stage: self::AP_REJECT, changed: true)
            ->withField(Approvers::REASON_FOR_REJECTION, 'Недостаточно дней накоплено');

        $result = (new ChangeStatus())->process($sidorova);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(self::ER_REJECT, $this->erFactory->getUpdatedItem(100)->getStageId());
        $this->spy->assertNoCalls($this);
    }

    // ── Шаг 3 (ещё один HR тоже одобряет позже) — дубль AP не создаётся ─────

    public function testPhase1SecondHrApproveTriggersPhase2Again(): void
    {
        // Сидорова уже одобрила
        $this->erFactory->addItem($this->makeErItem());
        $this->apFactory->addItem(
            $this->makeApItem(id: 1, userId: 21, phase: 1, stage: self::AP_APPROVE),
        );

        // Теперь Круглова тоже одобряет
        $kruglova = $this->makeApItem(id: 2, userId: 22, phase: 1, stage: self::AP_APPROVE, changed: true);

        $result = (new ChangeStatus())->process($kruglova);

        $this->assertTrue($result->isSuccess());
        // createPhase(2) вызывается повторно, но approverExists() в реальном коде предотвратит дубль
        $this->spy->assertPhaseCreated($this, phase: 2, order: 1);
    }

    // ── Шаг 4: Орлов (РП, фаза 2) один — сразу запускает фазу 3 ────────────

    public function testStep4Phase2SingleManagerApprovesTriggersPhase3(): void
    {
        $this->erFactory->addItem($this->makeErItem());
        // Нет сиблингов в фазе 2 (Орлов единственный)

        $orlov = $this->makeApItem(id: 3, userId: 19, phase: 2, stage: self::AP_APPROVE, changed: true);

        $result = (new ChangeStatus())->process($orlov);

        $this->assertTrue($result->isSuccess());
        $this->spy->assertPhaseCreated($this, phase: 3, order: 1);
        $this->assertCount(1, $this->spy->calls);
    }

    // ── Шаг 4 (вариант): второй РП ещё не решил → ожидаем ──────────────────

    public function testPhase2PendingSiblingHaltsProgression(): void
    {
        $this->erFactory->addItem($this->makeErItem());

        // Второй РП в фазе 2 ещё не принял решение
        $this->apFactory->addItem(
            $this->makeApItem(id: 9, userId: 99, phase: 2, stage: 'DT1050:NEW'),
        );

        $orlov = $this->makeApItem(id: 3, userId: 19, phase: 2, stage: self::AP_APPROVE, changed: true);

        $result = (new ChangeStatus())->process($orlov);

        $this->assertTrue($result->isSuccess());
        $this->spy->assertNoCalls($this);
    }

    // ── Шаг 5: Дмитриев (фаза 3, order=1) одобряет → создаётся order=2 ─────

    public function testStep5Phase3FirstInOrderApprovesCreatesNext(): void
    {
        $this->spy->phase3TotalSteps = 2;
        $this->erFactory->addItem($this->makeErItem());

        $dmitriev = $this->makeApItem(id: 4, userId: 4, phase: 3, stage: self::AP_APPROVE, order: 1, changed: true);

        $result = (new ChangeStatus())->process($dmitriev);

        $this->assertTrue($result->isSuccess());
        $this->spy->assertPhaseCreated($this, phase: 3, order: 2);
        $this->assertCount(1, $this->spy->calls);
    }

    // ── Шаг 6: Генералов (фаза 3, order=2, последний) → ER согласована ──────

    public function testStep6Phase3LastInOrderApprovesErApproved(): void
    {
        $this->spy->phase3TotalSteps = 2;

        $er = $this->makeErItem();
        $this->erFactory->addItem($er);

        $generalov = $this->makeApItem(id: 5, userId: 34, phase: 3, stage: self::AP_APPROVE, order: 2, changed: true);

        $result = (new ChangeStatus())->process($generalov);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(self::ER_APPROVE, $this->erFactory->getUpdatedItem(100)->getStageId());
        $this->spy->assertNoCalls($this);
    }
}
