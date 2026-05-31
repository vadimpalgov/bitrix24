<?php

declare(strict_types=1);

namespace Parfeon\Er\Tests\Integration;

/**
 * Сценарий 02 — Отклонение на фазе 1 (HR).
 *
 * Проверяет поведение модуля, когда HR-менеджер отклоняет заявку:
 *   - без причины → операция завершается ошибкой, ER не меняет стадию
 *   - с причиной  → ER сразу переходит в REJECT
 */
class Scenario02RejectPhase1Test extends IntegrationTestCase
{
    private const USER_IVANOV   = 5;
    private const USER_SIDOROVA = 21;

    public function testRejectWithoutReasonFails(): void
    {
        $this->actingAs(self::USER_IVANOV);
        $erId = $this->createErInStartStatus();

        $result = $this->rejectPhase($erId, self::USER_SIDOROVA, phase: 1, reason: null);

        $this->assertFalse($result->isSuccess(), 'Отклонение без причины должно возвращать ошибку');
        $this->assertNotEmpty($result->getErrors());

        // Стадия ER не изменилась
        $this->assertErStage($erId, $this->erStartStatus);
    }

    public function testRejectWithReasonMovesErToReject(): void
    {
        $this->actingAs(self::USER_IVANOV);
        $erId = $this->createErInStartStatus();

        $result = $this->rejectPhase($erId, self::USER_SIDOROVA, phase: 1, reason: 'Заявка подана слишком рано');

        $this->assertTrue($result->isSuccess(), implode('; ', $result->getErrorMessages()));

        $this->assertErStage($erId, $this->erRejectStatus);

        // Фаза 2 не создана
        $this->assertApCount($erId, 2); // только фаза 1 (Сидорова + Круглова)
    }
}
