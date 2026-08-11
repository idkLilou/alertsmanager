<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AlertTargetTest extends TestCase
{
    protected function setUp(): void
    {
        global $DB;

        $DB = new class () {
            /** @var array<int, array<string, mixed>> */
            public array $rows = [];

            public function request(array $criteria): array
            {
                return $this->rows;
            }
        };
    }

    public function testGetTargetTypes(): void
    {
        self::assertSame(
            [
                'User' => 'User',
                'Group' => 'Group',
                'Profile' => 'Profile',
            ],
            PluginAlertsmanagerAlertTarget::getTargetTypes(),
        );
    }

    public function testGetTargetClass(): void
    {
        self::assertSame('PluginAlertsmanagerAlertTargetUser', PluginAlertsmanagerAlertTarget::getTargetClass('User'));
        self::assertSame('PluginAlertsmanagerAlertTargetGroup', PluginAlertsmanagerAlertTarget::getTargetClass('Group'));
        self::assertSame('PluginAlertsmanagerAlertTargetProfile', PluginAlertsmanagerAlertTarget::getTargetClass('Profile'));
        self::assertNull(PluginAlertsmanagerAlertTarget::getTargetClass('Unknown'));
    }

    public function testGetEmailsForUserIdsFiltersDuplicatesAndInvalidValues(): void
    {
        global $DB;

        $DB->rows = [
            ['users_id' => 1, 'email' => 'alice@example.com'],
            ['users_id' => 1, 'email' => 'alice@example.com'],
            ['users_id' => 2, 'email' => 'not-an-email'],
            ['users_id' => 3, 'email' => 'bob@example.com'],
            ['users_id' => 4, 'email' => ''],
        ];

        self::assertSame(
            ['alice@example.com', 'bob@example.com'],
            PluginAlertsmanagerAlertTarget::getEmailsForUserIds([1, 2, 3, 4]),
        );
    }
}
