<?php

declare(strict_types=1);

namespace Parfeon\Er\Tests\Integration;

/**
 * Сценарий 04 — Отклонение на фазе 3 (руководитель).
 *
 * Фазы 1 и 2 прошли успешно.
 * Первый руководитель в иерархии (Дмитриев) отклоняет заявку.
 * → ER → REJECT, второй руководитель (Генералов) не получает AP.
 */
class Scenario04RejectPhase3Test extends IntegrationTestCase
{
    private const USER_IVANOV    = 5;
    private const USER_SIDOROVA  = 21;
    private const USER_ORLOV     = 19;
    private const USER_DMITRIEV  = 4;
    private const USER_GENERALOV = 34;

    public function testManagerRejectsMovesErToReject(): void
    {
        $this->actingAs(self::USER_IVANOV);
        $erId = $this->createErInStartStatus();

        $this->actingAs(self::USER_SIDOROVA);
        $this->approvePhase($erId, self::USER_SIDOROVA, phase: 1);

        $this->actingAs(self::USER_ORLOV);
        $this->approvePhase($erId, self::USER_ORLOV, phase: 2);

        // Фаза 3, order=1: Дмитриев отклоняет
        $result = $this->rejectPhase($erId, self::USER_DMITRIEV, phase: 3, reason: 'Недостаточно дней');

        $this->assertTrue($result->isSuccess(), implode('; ', $result->getErrorMessages()));
        $this->assertErStage($erId, $this->erRejectStatus);

        // AP для Генералова не создан
        $this->assertApNotExists($erId, self::USER_GENERALOV, phase: 3);
    }

    public function testManagerRejectWithoutReasonFails(): void
    {
        $this->actingAs(self::USER_IVANOV);
        $erId = $this->createErInStartStatus();

        $this->actingAs(self::USER_SIDOROVA);
        $this->approvePhase($erId, self::USER_SIDOROVA, phase: 1);

        $this->actingAs(self::USER_ORLOV);
        $this->approvePhase($erId, self::USER_ORLOV, phase: 2);

        $result = $this->rejectPhase($erId, self::USER_DMITRIEV, phase: 3, reason: null);

        $this->assertFalse($result->isSuccess());
        $this->assertErStage($erId, $this->erStartStatus);
    }
}
