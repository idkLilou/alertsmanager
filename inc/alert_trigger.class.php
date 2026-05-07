<?php

/**
 * -------------------------------------------------------------------------
 * Alerts Manager plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of Alerts Manager.
 *
 * Alerts Manager is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Alerts Manager is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Alerts Manager. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2024-2026 by Alerts Manager plugin team.
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/pluginsGLPI/alertsmanager
 * -------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    echo "Sorry. You can't access directly to this file";
    return;
}

class PluginAlertsmanagerAlertTrigger extends CommonDBTM
{
    public static $rightname = 'plugin_alertsmanager_alert';

    // Trigger types
    const TRIGGER_DATE_FIELD = 1;
    const TRIGGER_FREQUENCY = 2;

    public static function getTypeName($nb = 0)
    {
        return _n('Alert trigger', 'Alert triggers', $nb, 'alertsmanager');
    }

    public static function getTriggerTypes()
    {
        return [
            self::TRIGGER_DATE_FIELD => __('Date field', 'alertsmanager'),
            self::TRIGGER_FREQUENCY  => __('Frequency', 'alertsmanager'),
        ];
    }

    /**
     * Get all date fields from GLPI tables
     */
    public static function getAvailableDateFields()
    {
        // Get date fields from common GLPI tables
        $dateFields = [];

        // User table date fields
        $dateFields['User'] = [
            'last_login' => __('Last login'),
            'date_creation' => __('Creation date'),
        ];

        // Ticket table date fields
        $dateFields['Ticket'] = [
            'date' => __('Ticket date'),
            'date_mod' => __('Last modification'),
            'closedate' => __('Closing date'),
            'solvedate' => __('Resolution date'),
        ];

        // Problem table date fields
        $dateFields['Problem'] = [
            'date' => __('Problem date'),
            'date_mod' => __('Last modification'),
            'date_solved' => __('Resolution date'),
        ];

        // Change table date fields
        $dateFields['Change'] = [
            'date' => __('Change date'),
            'date_mod' => __('Last modification'),
            'end_date' => __('End date'),
        ];

        return $dateFields;
    }

    /**
     * Get frequency options
     */
    public static function getFrequencyOptions()
    {
        return [
            'daily' => __('Daily', 'alertsmanager'),
            'monday' => __('Every Monday', 'alertsmanager'),
            'tuesday' => __('Every Tuesday', 'alertsmanager'),
            'wednesday' => __('Every Wednesday', 'alertsmanager'),
            'thursday' => __('Every Thursday', 'alertsmanager'),
            'friday' => __('Every Friday', 'alertsmanager'),
            'saturday' => __('Every Saturday', 'alertsmanager'),
            'sunday' => __('Every Sunday', 'alertsmanager'),
            'monthly' => __('Monthly', 'alertsmanager'),
        ];
    }

    public function getSearchOptionsNew()
    {
        $tab = [];

        $tab[] = [
            'id'   => 'common',
            'name' => __('Characteristics'),
        ];

        $tab[] = [
            'id'            => '1',
            'table'         => $this->getTable(),
            'field'         => 'plugin_alertsmanager_alerts_id',
            'name'          => __('Alert'),
            'datatype'      => 'itemlink',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'       => '2',
            'table'    => $this->getTable(),
            'field'    => 'trigger_type',
            'name'     => __('Trigger type', 'alertsmanager'),
            'datatype' => 'string',
        ];

        return $tab;
    }
}
