<?php

declare(strict_types=1);

namespace Parfeon\Er\Tests\Integration;

/**
 * Сценарий 06 — Защита от дублей AP при повторном запуске согласования.
 *
 * Если заявка уходит обратно в нейтральную стадию, а затем снова
 * переводится в ER_START_STATUS — AP-элементы не дублируются.
 *
 * Тест пропускается, если в смарт-процессе нет «нейтральной» стадии
 * (кроме START / APPROVE / REJECT).
 */
class Scenario06NoDuplicateApsTest extends IntegrationTestCase
{
    private const USER_IVANOV = 5;

    public function testRestartDoesNotDuplicateAps(): void
    {
        $neutralStage = $this->findNeutralErStage();

        if ($neutralStage === null) {
            $this->markTestSkipped(
                'В смарт-процессе ER нет нейтральной стадии для отката — тест пропущен.'
            );
        }

        $this->actingAs(self::USER_IVANOV);
        $erId = $this->createErInStartStatus();

        // Первый запуск: созданы AP фазы 1
        $countAfterFirst = count($this->getApItems($erId));
        $this->assertGreaterThan(0, $countAfterFirst, 'После первого START должны быть AP');

        // Откат в нейтральную стадию
        $this->updateErStage($erId, $neutralStage);

        // Повторный перевод в START_STATUS
        $this->updateErStage($erId, $this->erStartStatus);

        $countAfterSecond = count($this->getApItems($erId));

        $this->assertSame(
            $countAfterFirst,
            $countAfterSecond,
            "После повторного START AP-элементов стало {$countAfterSecond}, ожидали {$countAfterFirst} (без дублей)",
        );
    }
}
