<?php

declare(strict_types=1);

namespace Parfeon\Er\Tests\Integration;

use Parfeon\Er\Mapping\EmployeeRequest;

/**
 * Сценарий 05 — Пропуск фазы 2 (нет руководителей проекта).
 *
 * Если поле PROJECT_MANAGERS пустое, фаза 2 пропускается:
 * после одобрения HR сразу создаётся AP фазы 3, order=1.
 *
 * Полный цикл: HR одобряет → фаза 3 → Дмитриев → Генералов → ER APPROVE.
 */
class Scenario05SkipPhase2NoPmTest extends IntegrationTestCase
{
    private const USER_IVANOV    = 5;
    private const USER_SIDOROVA  = 21;
    private const USER_ORLOV     = 19;
    private const USER_DMITRIEV  = 4;
    private const USER_GENERALOV = 34;

    public function testAfterHrApproveSkipsDirectlyToPhase3(): void
    {
        $this->actingAs(self::USER_IVANOV);

        // Создаём ER без PROJECT_MANAGERS
        $erId = $this->createErInStartStatus([
            EmployeeRequest::PROJECT_MANAGERS => [],
        ]);

        $this->actingAs(self::USER_SIDOROVA);
        $this->approvePhase($erId, self::USER_SIDOROVA, phase: 1);

        // Фаза 2 пропущена — AP для Орлова нет
        $this->assertApNotExists($erId, self::USER_ORLOV, phase: 2);

        // Фаза 3 уже создана
        $this->assertApExists($erId, self::USER_DMITRIEV, phase: 3, order: 1);
    }

    public function testFullCycleWithoutPm(): void
    {
        $this->actingAs(self::USER_IVANOV);
        $erId = $this->createErInStartStatus([
            EmployeeRequest::PROJECT_MANAGERS => [],
        ]);

        $this->actingAs(self::USER_SIDOROVA);
        $this->approvePhase($erId, self::USER_SIDOROVA, phase: 1);

        $this->actingAs(self::USER_DMITRIEV);
        $this->approvePhase($erId, self::USER_DMITRIEV, phase: 3, order: 1);

        $this->actingAs(self::USER_GENERALOV);
        $this->approvePhase($erId, self::USER_GENERALOV, phase: 3, order: 2);

        $this->assertErStage($erId, $this->erApproveStatus);

        // Итого AP: 2 (HR) + 2 (фаза 3) = 4, AP фазы 2 нет
        $this->assertApCount($erId, 4);
        $this->assertApNotExists($erId, self::USER_ORLOV, phase: 2);
    }
}
