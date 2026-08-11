<?php

declare(strict_types=1);

namespace PHPUnit\Framework;

abstract class TestCase
{
    public static function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
    }

    public static function assertNull(mixed $actual, string $message = ''): void
    {
    }
}
