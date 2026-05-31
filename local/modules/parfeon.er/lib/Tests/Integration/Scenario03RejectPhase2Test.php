<?php

declare(strict_types=1);

namespace Parfeon\Er\Tests\Integration;

/**
 * Сценарий 03 — Отклонение на фазе 2 (РП).
 *
 * HR одобрил → РП отклоняет → ER уходит в REJECT.
 * Фаза 3 не запускается.
 */
class Scenario03RejectPhase2Test extends IntegrationTestCase
{
    private const USER_IVANOV   = 5;
    private const USER_SIDOROVA = 21;
    private const USER_ORLOV    = 19;
    private const USER_DMITRIEV = 4;

    public function testPmRejectsMovesErToReject(): void
    {
        $this->actingAs(self::USER_IVANOV);
        $erId = $this->createErInStartStatus();

        // Фаза 1: Сидорова одобряет → создаётся AP для Орлова
        $this->actingAs(self::USER_SIDOROVA);
        $this->approvePhase($erId, self::USER_SIDOROVA, phase: 1);
        $this->assertApExists($erId, self::USER_ORLOV, phase: 2);

        // Фаза 2: Орлов отклоняет
        $result = $this->rejectPhase($erId, self::USER_ORLOV, phase: 2, reason: 'Нет необходимости');

        $this->assertTrue($result->isSuccess(), implode('; ', $result->getErrorMessages()));
        $this->assertErStage($erId, $this->erRejectStatus);

        // Фаза 3 не создана
        $this->assertApNotExists($erId, self::USER_DMITRIEV, phase: 3);
    }

    public function testPmRejectWithoutReasonFails(): void
    {
        $this->actingAs(self::USER_IVANOV);
        $erId = $this->createErInStartStatus();

        $this->actingAs(self::USER_SIDOROVA);
        $this->approvePhase($erId, self::USER_SIDOROVA, phase: 1);

        $result = $this->rejectPhase($erId, self::USER_ORLOV, phase: 2, reason: null);

        $this->assertFalse($result->isSuccess());
        $this->assertErStage($erId, $this->erStartStatus);
    }
}
