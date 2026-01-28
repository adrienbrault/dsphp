<?php

declare(strict_types=1);

namespace AdrienBrault\DsPhp\Tests;

use AdrienBrault\DsPhp\Greeting;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GreetingTest extends TestCase
{
    #[Test]
    public function hello(): void
    {
        self::assertSame('Hello, World!', Greeting::hello('World'));
    }
}
