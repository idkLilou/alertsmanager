<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AlertTriggerTest extends TestCase
{
    public function testGetTriggerTypes(): void
    {
        self::assertSame(
            [
                PluginAlertsmanagerAlertTrigger::TRIGGER_DATE_FIELD => 'Date field',
                PluginAlertsmanagerAlertTrigger::TRIGGER_FREQUENCY => 'Frequency',
            ],
            PluginAlertsmanagerAlertTrigger::getTriggerTypes(),
        );
    }

    public function testGetFrequencyOptions(): void
    {
        self::assertSame(
            [
                'daily' => 'Daily',
                'monday' => 'Every Monday',
                'tuesday' => 'Every Tuesday',
                'wednesday' => 'Every Wednesday',
                'thursday' => 'Every Thursday',
                'friday' => 'Every Friday',
                'saturday' => 'Every Saturday',
                'sunday' => 'Every Sunday',
                'monthly' => 'Monthly',
            ],
            PluginAlertsmanagerAlertTrigger::getFrequencyOptions(),
        );
    }

    public function testGetAvailableDateFields(): void
    {
        self::assertSame(
            [
                'User' => [
                    'last_login' => 'Last login',
                    'date_creation' => 'Creation date',
                ],
                'Ticket' => [
                    'date' => 'Ticket date',
                    'date_mod' => 'Last modification',
                    'closedate' => 'Closing date',
                    'solvedate' => 'Resolution date',
                ],
                'Problem' => [
                    'date' => 'Problem date',
                    'date_mod' => 'Last modification',
                    'date_solved' => 'Resolution date',
                ],
                'Change' => [
                    'date' => 'Change date',
                    'date_mod' => 'Last modification',
                    'end_date' => 'End date',
                ],
            ],
            PluginAlertsmanagerAlertTrigger::getAvailableDateFields(),
        );
    }
}
