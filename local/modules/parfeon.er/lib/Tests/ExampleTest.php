<?php

declare(strict_types=1);

namespace Parfeon\Er\Tests;

use Parfeon\Er\Tests\Support\ActsAsUser;
use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    use ActsAsUser;

    public function testActingAsSetsGlobalUser(): void
    {
        $this->actingAs(42);

        $this->assertSame(42, $GLOBALS['USER']->GetID());
        $this->assertTrue($GLOBALS['USER']->IsAuthorized());
        $this->assertFalse($GLOBALS['USER']->IsAdmin());
    }

    public function testSwitchingBetweenUsers(): void
    {
        $this->actingAs(10);
        $this->assertSame(10, $GLOBALS['USER']->GetID());

        $this->actingAs(25);
        $this->assertSame(25, $GLOBALS['USER']->GetID());
    }

    public function testActingAsAdmin(): void
    {
        $this->actingAs(1, isAdmin: true);

        $this->assertTrue($GLOBALS['USER']->IsAdmin());
    }

    public function testGlobalUserRestoredAfterTest(): void
    {
        // После каждого теста tearDown восстанавливает оригинал.
        // Этот тест проверяет, что actingAs не вытекает между тестами.
        $this->assertFalse(isset($GLOBALS['USER']) && $GLOBALS['USER']->GetID() === 42);
    }
}
