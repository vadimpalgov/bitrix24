<?php

declare(strict_types=1);

namespace Parfeon\Er\Tests\Support;

/**
 * Подключите этот trait к TestCase, чтобы переключать текущего пользователя
 * прямо в тесте через actingAs(int $userId).
 *
 * Обязательно вызовите parent::tearDown() — trait восстанавливает
 * оригинальный $GLOBALS['USER'] в tearDown автоматически.
 *
 * Пример:
 *
 *   $this->actingAs(10);   // теперь USER->GetID() === 10
 *   // ... действия от имени user 10 ...
 *   $this->actingAs(25);   // переключаемся на user 25
 *   // ... действия от имени user 25 ...
 */
trait ActsAsUser
{
    private mixed $originalUser  = null;
    private bool  $userInstalled = false;

    /**
     * Устанавливает $GLOBALS['USER'] в MockUser с заданным $userId.
     * Первый вызов сохраняет оригинальное значение для восстановления в tearDown.
     */
    protected function actingAs(int $userId, bool $isAdmin = false): void
    {
        if (!$this->userInstalled) {
            $this->originalUser  = $GLOBALS['USER'] ?? null;
            $this->userInstalled = true;
        }

        $GLOBALS['USER'] = new MockUser($userId, $isAdmin);
    }

    /**
     * Восстанавливает $GLOBALS['USER'] в исходное состояние.
     * Вызывается автоматически из tearDown.
     */
    protected function restoreOriginalUser(): void
    {
        if ($this->userInstalled) {
            $GLOBALS['USER']     = $this->originalUser;
            $this->originalUser  = null;
            $this->userInstalled = false;
        }
    }

    protected function tearDown(): void
    {
        $this->restoreOriginalUser();
        parent::tearDown();
    }
}
