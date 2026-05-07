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

use Glpi\Application\View\TemplateRenderer;

class PluginAlertsmanagerAlert extends CommonDBTM
{
    public static $rightname = 'plugin_alertsmanager_alert';
    public $dohistory        = true;

    public static function getTypeName($nb = 0)
    {
        return __s('Alertes mail', 'alertsmanager');
    }

    public static function getType()
    {
        return 'PluginAlertsmanagerAlert';
    }

    public static function canCreate(): bool
    {
        return Session::haveRight(self::$rightname, CREATE) || Session::haveRight('config', UPDATE);
    }

    public static function canView(): bool
    {
        return Session::haveRight(self::$rightname, READ) || Session::haveRight('config', READ);
    }

    public static function canDelete(): bool
    {
        return Session::haveRight(self::$rightname, DELETE) || Session::haveRight('config', UPDATE);
    }

    public static function canUpdate(): bool
    {
        return Session::haveRight(self::$rightname, UPDATE) || Session::haveRight('config', UPDATE);
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        return [];
    }

    public function defineTabs($options = [])
    {
        $ong = [];
        $this->addDefaultFormTabs($ong);
        return $ong;
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
            'field'         => 'name',
            'name'          => __('Name'),
            'datatype'      => 'itemlink',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'       => '2',
            'table'    => $this->getTable(),
            'field'    => 'description',
            'name'     => __('Description'),
            'datatype' => 'text',
        ];

        $tab[] = [
            'id'       => '3',
            'table'    => $this->getTable(),
            'field'    => 'is_active',
            'name'     => __('Active'),
            'datatype' => 'bool',
        ];

        $tab[] = [
            'id'       => '4',
            'table'    => $this->getTable(),
            'field'    => 'mail_subject',
            'name'     => __('Mail Subject', 'alertsmanager'),
            'datatype' => 'string',
        ];

        $tab[] = [
            'id'       => '5',
            'table'    => $this->getTable(),
            'field'    => 'mail_content',
            'name'     => __('Mail Content', 'alertsmanager'),
            'datatype' => 'text',
        ];

        $tab[] = [
            'id'       => '6',
            'table'    => $this->getTable(),
            'field'    => 'date_creation',
            'name'     => __('Creation date'),
            'datatype' => 'datetime',
        ];

        return $tab;
    }

    public function rawSearchOptions()
    {
        return $this->getSearchOptionsNew();
    }

    public function getRecipientEmails()
    {
        if (!isset($this->fields['id'])) {
            return [];
        }

        return PluginAlertsmanagerAlertTarget::getRecipientEmailsForAlert((int) $this->fields['id']);
    }

    public function getRecipientUserIds()
    {
        if (!isset($this->fields['id'])) {
            return [];
        }

        return PluginAlertsmanagerAlertTarget::getRecipientUserIdsForAlert((int) $this->fields['id']);
    }

    public function showForm($ID, $options = [])
    {
        error_log('[AlertsManager] showForm() called with ID=' . $ID);
        
        if ($ID > 0) {
            $this->getFromDB($ID);
        } else {
            $this->getEmpty();
        }

        $this->fields['_targets'] = $this->getTargetsForDisplay((int) ($this->fields['id'] ?? 0));
        $this->fields['target_type'] = $this->getTargetTypeForDisplay((int) ($this->fields['id'] ?? 0));
        $this->fields['_available_fields'] = $this->getAvailableObservedFields();

        error_log('[AlertsManager] Fields loaded: _targets=' . count($this->fields['_targets']) . ', target_type=' . $this->fields['target_type'] . ', _available_fields=' . count($this->fields['_available_fields']));

        $twig = TemplateRenderer::getInstance();
        $twig->display('@alertsmanager/alert_form.html.twig', [
            'item' => $this,
        ]);

        return true;
    }

    private function getTargetTypeForDisplay(int $alertId): string
    {
        if ($alertId <= 0) {
            return '';
        }

        /** @var DBmysql $DB */
        global $DB;

        $checks = [
            'User'    => 'glpi_plugin_alertsmanager_alert_users',
            'Group'   => 'glpi_plugin_alertsmanager_alert_groups',
            'Profile' => 'glpi_plugin_alertsmanager_alert_profiles',
        ];

        foreach ($checks as $type => $table) {
            $res = $DB->query("SELECT id FROM `$table` WHERE `plugin_alertsmanager_alerts_id` = '" . intval($alertId) . "' LIMIT 1");
            if ($res && $DB->numrows($res) > 0) {
                return $type;
            }
        }

        return '';
    }

    private function getTargetsForDisplay(int $alertId): array
    {
        if ($alertId <= 0) {
            return [];
        }

        /** @var DBmysql $DB */
        global $DB;

        $type = $this->getTargetTypeForDisplay($alertId);
        $targets = [];

        if ($type === 'User') {
            $res = $DB->query(
                "SELECT u.id, CONCAT(u.firstname, ' ', u.name) AS label
                 FROM `glpi_plugin_alertsmanager_alert_users` au
                 INNER JOIN `glpi_users` u ON (u.id = au.users_id)
                 WHERE au.plugin_alertsmanager_alerts_id = '" . intval($alertId) . "'
                 ORDER BY u.name"
            );
        } elseif ($type === 'Group') {
            $res = $DB->query(
                "SELECT g.id, g.name AS label
                 FROM `glpi_plugin_alertsmanager_alert_groups` ag
                 INNER JOIN `glpi_groups` g ON (g.id = ag.groups_id)
                 WHERE ag.plugin_alertsmanager_alerts_id = '" . intval($alertId) . "'
                 ORDER BY g.name"
            );
        } elseif ($type === 'Profile') {
            $res = $DB->query(
                "SELECT p.id, p.name AS label
                 FROM `glpi_plugin_alertsmanager_alert_profiles` ap
                 INNER JOIN `glpi_profiles` p ON (p.id = ap.profiles_id)
                 WHERE ap.plugin_alertsmanager_alerts_id = '" . intval($alertId) . "'
                 ORDER BY p.name"
            );
        } else {
            return [];
        }

        while ($row = $res->fetch_assoc()) {
            $targets[] = [
                'id'    => (int) $row['id'],
                'label' => (string) $row['label'],
            ];
        }

        return $targets;
    }

    private function getAvailableObservedFields(): array
    {
        return [
            ['id' => 'Ticket.due_date', 'label' => __('Ticket Due Date')],
            ['id' => 'Ticket.date_creation', 'label' => __('Ticket Creation Date')],
            ['id' => 'Ticket.date_mod', 'label' => __('Ticket Last Update')],
            ['id' => 'Contract.begin_date', 'label' => __('Contract Begin Date')],
            ['id' => 'Contract.end_date', 'label' => __('Contract End Date')],
            ['id' => 'SoftwareLicense.expiration_date', 'label' => __('License Expiration Date')],
            ['id' => 'SoftwareLicense.buy_date', 'label' => __('License Buy Date')],
            ['id' => 'Computer.date_creation', 'label' => __('Computer Creation Date')],
            ['id' => 'Printer.date_creation', 'label' => __('Printer Creation Date')],
            ['id' => 'Monitor.date_creation', 'label' => __('Monitor Creation Date')],
            ['id' => 'Project.begin_date', 'label' => __('Project Begin Date')],
            ['id' => 'Project.end_date', 'label' => __('Project End Date')],
        ];
    }
}
