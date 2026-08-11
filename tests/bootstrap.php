<?php

declare(strict_types=1);

namespace PHPUnit\Framework {
	if (!class_exists(TestCase::class)) {
		abstract class TestCase
		{
			public static function assertSame(mixed $expected, mixed $actual, string $message = ''): void {}

			public static function assertNull(mixed $actual, string $message = ''): void {}
		}
	}
}

namespace {

define('GLPI_ROOT', dirname(__DIR__));

require_once GLPI_ROOT . '/setup.php';
require_once GLPI_ROOT . '/inc/alert_target.class.php';
require_once GLPI_ROOT . '/inc/alert_trigger.class.php';
}
