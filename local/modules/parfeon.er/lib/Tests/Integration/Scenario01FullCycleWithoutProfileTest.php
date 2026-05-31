<?php

declare(strict_types=1);

namespace Parfeon\Er\Tests\Integration;

use Parfeon\Er\Mapping\Approvers;
use Parfeon\Er\Mapping\EmployeeRequest;

/**
 * Сценарий 01 — Полный цикл согласования без профиля.
 * Happy path: все фазы проходят в штатном режиме.
 *
 * Участники:
 *   Иванов    ID=5   Заявитель
 *   Сидорова  ID=21  HR (фаза 1)
 *   Круглова  ID=22  HR (фаза 1)
 *   Орлов     ID=19  РП (фаза 2)
 *   Дмитриев  ID=4   Рук. разработки (фаза 3, order=1)
 *   Генералов ID=34  Генеральный директор (фаза 3, order=2)
 */
class Scenario01FullCycleWithoutProfileTest extends IntegrationTestCase
{
    private const USER_IVANOV    = 5;
    private const USER_SIDOROVA  = 21;
    private const USER_KRUGLOVA  = 22;
    private const USER_ORLOV     = 19;
    private const USER_DMITRIEV  = 4;
    private const USER_GENERALOV = 34;

    public function testStep1CreateRequestFillsFieldsButNoAps(): void
    {
        $this->actingAs(self::USER_IVANOV);

        $erId = $this->createEr($this->defaultErFields());
        $er   = $this->erFactory->getItem($erId);

        $this->assertStringContainsString('Иван', $er->get('TITLE'));

        $this->assertContains(self::USER_DMITRIEV,  (array)$er->get(EmployeeRequest::HEAD_OF_DEPARTMENT));
        $this->assertContains(self::USER_GENERALOV, (array)$er->get(EmployeeRequest::HEAD_OF_DEPARTMENT));
        $this->assertContains(self::USER_SIDOROVA,  (array)$er->get(EmployeeRequest::HR_MANAGERS));
        $this->assertContains(self::USER_KRUGLOVA,  (array)$er->get(EmployeeRequest::HR_MANAGERS));

        $this->assertApCount($erId, 0);
    }

    public function testStep2MoveToStartCreatesPhase1Aps(): void
    {
        $this->actingAs(self::USER_IVANOV);
        $erId = $this->createErInStartStatus();

        $this->assertApCount($erId, 2);
        $this->assertApExists($erId, self::USER_SIDOROVA, phase: 1);
        $this->assertApExists($erId, self::USER_KRUGLOVA, phase: 1);
        $this->assertApNotExists($erId, self::USER_ORLOV,    phase: 2);
        $this->assertApNotExists($erId, self::USER_DMITRIEV, phase: 3);
    }

    public function testStep3SidorovaApprovesTriggersPhase2(): void
    {
        $this->actingAs(self::USER_IVANOV);
        $erId = $this->createErInStartStatus();

        $this->actingAs(self::USER_SIDOROVA);
        $this->approvePhase($erId, self::USER_SIDOROVA, phase: 1);

        $this->assertApExists($erId, self::USER_ORLOV, phase: 2);
        $this->assertSame(
            $this->apStartStatus,
            $this->findAp($erId, self::USER_KRUGLOVA, phase: 1)->getStageId(),
            'AP Кругловой не должен меняться',
        );
        $this->assertErStage($erId, $this->erStartStatus);
    }

    public function testStep4OrlovApprovesTriggersPhase3Order1(): void
    {
        $this->actingAs(self::USER_IVANOV);
        $erId = $this->createErInStartStatus();

        $this->actingAs(self::USER_SIDOROVA);
        $this->approvePhase($erId, self::USER_SIDOROVA, phase: 1);

        $this->actingAs(self::USER_ORLOV);
        $this->approvePhase($erId, self::USER_ORLOV, phase: 2);

        $this->assertApExists($erId, self::USER_DMITRIEV, phase: 3, order: 1);
        $this->assertApNotExists($erId, self::USER_GENERALOV, phase: 3);
        $this->assertErStage($erId, $this->erStartStatus);
    }

    public function testStep5DmitrievApprovesCreatesGeneralovAp(): void
    {
        $this->actingAs(self::USER_IVANOV);
        $erId = $this->createErInStartStatus();

        $this->actingAs(self::USER_SIDOROVA);
        $this->approvePhase($erId, self::USER_SIDOROVA, phase: 1);

        $this->actingAs(self::USER_ORLOV);
        $this->approvePhase($erId, self::USER_ORLOV, phase: 2);

        $this->actingAs(self::USER_DMITRIEV);
        $this->approvePhase($erId, self::USER_DMITRIEV, phase: 3, order: 1);

        $this->assertApExists($erId, self::USER_GENERALOV, phase: 3, order: 2);
        $this->assertErStage($erId, $this->erStartStatus);
    }

    public function testStep6GeneralovApprovesErApproved(): void
    {
        $this->actingAs(self::USER_IVANOV);
        $erId = $this->createErInStartStatus();

        $this->actingAs(self::USER_SIDOROVA);
        $this->approvePhase($erId, self::USER_SIDOROVA, phase: 1);

        $this->actingAs(self::USER_ORLOV);
        $this->approvePhase($erId, self::USER_ORLOV, phase: 2);

        $this->actingAs(self::USER_DMITRIEV);
        $this->approvePhase($erId, self::USER_DMITRIEV, phase: 3, order: 1);

        $this->actingAs(self::USER_GENERALOV);
        $this->approvePhase($erId, self::USER_GENERALOV, phase: 3, order: 2);

        $this->assertErStage($erId, $this->erApproveStatus);
        $this->assertApCount($erId, 5);

        $this->assertSame(
            $this->apApproveStatus,
            $this->findAp($erId, self::USER_SIDOROVA, phase: 1)->getStageId(),
        );
        $this->assertSame(
            $this->apStartStatus,
            $this->findAp($erId, self::USER_KRUGLOVA, phase: 1)->getStageId(),
            'AP Кругловой должен остаться в начальной стадии',
        );
    }
}
