<?php

declare(strict_types=1);

namespace Parfeon\Er\Tests\Support;

use Bitrix\Crm\Item;

/**
 * Шпион на CreateApprovers. Записывает все вызовы createPhase(),
 * не обращается к базе данных.
 *
 * Использование в тестах:
 *   $spy = new SpyCreateApprovers();
 *   // Зарегистрировать в ServiceLocator как 'parfeon.er.service.create.approvers'
 *
 *   $spy->assertPhaseCreated($test, phase: 2);
 *   $spy->assertPhaseCreated($test, phase: 3, order: 1);
 */
class SpyCreateApprovers
{
    /** Записи всех вызовов createPhase в формате ['phase', 'erItemId', 'order'] */
    public array $calls = [];

    /** Настраиваемое возвращаемое значение getPhase3TotalSteps */
    public int $phase3TotalSteps = 2;

    public function createPhase(int $phase, Item $erItem, int $order = 1): void
    {
        $this->calls[] = [
            'phase'    => $phase,
            'erItemId' => $erItem->getId(),
            'order'    => $order,
        ];
    }

    public function getPhase3TotalSteps(Item $erItem): int
    {
        return $this->phase3TotalSteps;
    }

    public function reset(): void
    {
        $this->calls = [];
    }

    // ── assertion-хелперы ─────────────────────────────────────────────────────

    public function assertPhaseCreated(\PHPUnit\Framework\TestCase $test, int $phase, int $order = 1): void
    {
        foreach ($this->calls as $call) {
            if ($call['phase'] === $phase && $call['order'] === $order) {
                $test->assertTrue(true);
                return;
            }
        }

        $test->fail(sprintf(
            'createPhase(%d, order=%d) was not called. Actual calls: %s',
            $phase,
            $order,
            json_encode($this->calls),
        ));
    }

    public function assertNoCalls(\PHPUnit\Framework\TestCase $test): void
    {
        $test->assertEmpty($this->calls, 'Expected no createPhase() calls, got: ' . json_encode($this->calls));
    }
}
