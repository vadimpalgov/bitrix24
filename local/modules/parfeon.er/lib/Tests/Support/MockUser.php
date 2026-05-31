<?php

declare(strict_types=1);

namespace Parfeon\Er\Tests\Support;

/**
 * Минимальный stub CUser для тестов.
 * Покрывает методы, которые Bitrix-код вызывает у $GLOBALS['USER'].
 */
class MockUser
{
    public function __construct(
        private int  $id,
        private bool $isAdmin = false,
    ) {}

    public function GetID(): int
    {
        return $this->id;
    }

    public function IsAuthorized(): bool
    {
        return $this->id > 0;
    }

    public function IsAdmin(): bool
    {
        return $this->isAdmin;
    }

    /** Заглушка — всегда разрешает. Переопределите при необходимости. */
    public function CanDoOperation(string $operation): bool
    {
        return true;
    }
}
