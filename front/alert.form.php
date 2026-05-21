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
 * @link      https://github.com/idkLilou/alertsmanager
 * -------------------------------------------------------------------------
 */

use Glpi\Event;

require_once __DIR__ . '/../inc/alert_trigger.class.php';

Session::checkLoginUser();

if (!isset($_GET['id'])) {
    $_GET['id'] = '';
}

$alert = new PluginAlertsmanagerAlert();

function alertsmanager_save_trigger(int $alertId, array $input = []): void {
    /** @var DBmysql $DB */
    global $DB;

    if ($alertId <= 0) {
        return;
    }

    $observedField = trim((string) ($input['observed_field'] ?? ''));
    $triggerType = $observedField !== '' ? PluginAlertsmanagerAlertTrigger::TRIGGER_DATE_FIELD : PluginAlertsmanagerAlertTrigger::TRIGGER_FREQUENCY;
    $observedItemtype = '';
    if ($observedField !== '' && strpos($observedField, '.') !== false) {
        [$tableName] = explode('.', $observedField, 2);
        $observedItemtype = $tableName;
    }

    $payload = [
        'plugin_alertsmanager_alerts_id' => $alertId,
        'trigger_type'                   => $triggerType,
        'observed_field'                 => $observedField,
        'observed_itemtype'              => $observedItemtype,
        'start_date'                     => (isset($input['trigger_start_date']) && trim((string)$input['trigger_start_date']) !== '') ? trim((string)$input['trigger_start_date']) : null,
        'trigger_days_before'            => (int) ($input['trigger_days_before'] ?? 0),
        'trigger_months_before'          => (int) ($input['trigger_months_before'] ?? 0),
        'frequency'                      => trim((string) ($input['frequency'] ?? '')),
        'frequency_hour'                 => (int) ($input['frequency_hour'] ?? 12),
        'frequency_minute'               => (int) ($input['frequency_minute'] ?? 0),
        'date_creation'                  => date('Y-m-d H:i:s'),
    ];

    $DB->delete('glpi_plugin_alertsmanager_alert_triggers', [
        'plugin_alertsmanager_alerts_id' => $alertId,
    ]);

    $DB->insert('glpi_plugin_alertsmanager_alert_triggers', $payload);
}

/**
 * Save target relations (users/groups/profiles)
 */
function alertsmanager_save_targets(int $alertId, string $type, array $targets = []) {
    /** @var DBmysql $DB */
    global $DB;

    // Normalize targets to integers
    $targets = array_map('intval', $targets);

    // Delete existing relations for this alert in all target tables
    $tables = [
        'glpi_plugin_alertsmanager_alert_users',
        'glpi_plugin_alertsmanager_alert_groups',
        'glpi_plugin_alertsmanager_alert_profiles',
    ];
    foreach ($tables as $t) {
        $DB->delete($t, [
            'plugin_alertsmanager_alerts_id' => $alertId,
        ]);
    }

    if (empty($targets)) {
        return;
    }

    $now = date('Y-m-d H:i:s');

    switch ($type) {
        case 'User':
            foreach ($targets as $u) {
                $DB->insert('glpi_plugin_alertsmanager_alert_users', [
                    'plugin_alertsmanager_alerts_id' => $alertId,
                    'users_id'                        => $u,
                    'date_creation'                   => $now,
                ]);
            }
            break;
        case 'Group':
            foreach ($targets as $g) {
                $DB->insert('glpi_plugin_alertsmanager_alert_groups', [
                    'plugin_alertsmanager_alerts_id' => $alertId,
                    'groups_id'                      => $g,
                    'date_creation'                  => $now,
                ]);
            }
            break;
        case 'Profile':
            foreach ($targets as $p) {
                $DB->insert('glpi_plugin_alertsmanager_alert_profiles', [
                    'plugin_alertsmanager_alerts_id' => $alertId,
                    'profiles_id'                    => $p,
                    'date_creation'                  => $now,
                ]);
            }
            break;
    }
}

if (isset($_POST['update'])) {
    $alert->check($_POST['id'], UPDATE);
    if ($alert->update($_POST)) {
        Event::log(
            $_POST['id'],
            'PluginAlertsmanagerAlert',
            4,
            'admin',
            sprintf(__s('%s updates an item', 'alertsmanager'), $_SESSION['glpiname']),
        );
        alertsmanager_save_trigger((int) $_POST['id'], $_POST);
        // Save targets relations
        alertsmanager_save_targets((int) $_POST['id'], $_POST['target_type'] ?? '', $_POST['targets'] ?? []);
    }
    Html::back();
} elseif (isset($_POST['add'])) {
    $alert->check(-1, CREATE, $_POST);
    if ($newID = $alert->add($_POST)) {
        Event::log(
            $newID,
            'PluginAlertsmanagerAlert',
            4,
            'admin',
            sprintf(__s('%1$s adds the item %2$s', 'alertsmanager'), $_SESSION['glpiname'], $_POST['name']),
        );

        // Save targets relations
    alertsmanager_save_trigger((int) $newID, $_POST);
        alertsmanager_save_targets((int) $newID, $_POST['target_type'] ?? '', $_POST['targets'] ?? []);

        if ($_SESSION['glpibackcreated']) {
            Html::redirect($alert->getLinkURL());
        }
    }
    Html::back();
} elseif (isset($_POST['delete'])) {
    $alert->check($_POST['id'], DELETE);
    if ($alert->delete($_POST)) {
        Event::log(
            $_POST['id'],
            'PluginAlertsmanagerAlert',
            4,
            'admin',
            sprintf(__s('%s deletes an item', 'alertsmanager'), $_SESSION['glpiname']),
        );
    }
    $alert->redirectToList();
} elseif (isset($_POST['restore'])) {
    $alert->check($_POST['id'], DELETE);
    if ($alert->restore($_POST)) {
        Event::log(
            $_POST['id'],
            'PluginAlertsmanagerAlert',
            4,
            'admin',
            sprintf(__s('%s restores an item', 'alertsmanager'), $_SESSION['glpiname']),
        );
    }
    Html::back();
} elseif (isset($_POST['purge'])) {
    $alert->check($_POST['id'], PURGE);
    if ($alert->delete($_POST, true)) {
        Event::log(
            $_POST['id'],
            'PluginAlertsmanagerAlert',
            4,
            'admin',
            sprintf(__s('%s purges an item', 'alertsmanager'), $_SESSION['glpiname']),
        );
    }
    $alert->redirectToList();
}

Html::header(
    __s('Alertes mail', 'alertsmanager'),
    $_SERVER['PHP_SELF'],
    'tools',
    'PluginAlertsmanagerAlert',
);

$alert->display(['id' => $_GET['id']]);

Html::footer();
