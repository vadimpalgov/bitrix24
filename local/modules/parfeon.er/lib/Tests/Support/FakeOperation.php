<?php

declare(strict_types=1);

namespace Parfeon\Er\Tests\Support;

use Bitrix\Main\Result;

/**
 * Fake-операция для цепочки getUpdateOperation()->disableCheckAccess()->launch().
 */
class FakeOperation
{
    public function __construct(
        private readonly FakeFactory $factory,
        private readonly FakeItem    $item,
    ) {}

    public function disableCheckAccess(): static
    {
        return $this;
    }

    public function launch(): Result
    {
        $this->factory->recordUpdate($this->item);
        return new Result();
    }
}
