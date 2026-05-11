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
        $this->fields['_available_fields'] = self::getAvailableObservedFields();

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

    public static function getAvailableObservedFields(): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $fields = [];
        $dateTypes = ['date', 'datetime', 'timestamp'];

        error_log('[AlertsManager] getAvailableObservedFields() started');

        $classesToCheck = [
            'Ticket',
            'Problem',
            'Change',
            'Contract',
            'SoftwareLicense',
            'Computer',
            'Printer',
            'Monitor',
            'NetworkEquipment',
            'Peripheral',
            'Project',
            'User',
            'Group',
            'Profile',
            'Entity',
            'Location',
            'Supplier',
            'Manufacturer',
            'DeviceMemory',
            'DeviceProcessor',
            'DeviceFirmware',
        ];

        foreach ($classesToCheck as $itemtype) {
            if (!class_exists($itemtype)) {
                error_log('[AlertsManager] Class not found: ' . $itemtype);
                continue;
            }

            try {
                $item = new $itemtype();
                if (!method_exists($item, 'rawSearchOptions')) {
                    error_log('[AlertsManager] No rawSearchOptions for: ' . $itemtype);
                    continue;
                }

                $searchOptions = (array) $item->rawSearchOptions();
                error_log('[AlertsManager] ' . $itemtype . ' has ' . count($searchOptions) . ' raw search options');
                
                foreach ($searchOptions as $option) {
                    $datatype = (string) ($option['datatype'] ?? '');
                    $field = (string) ($option['field'] ?? '');
                    $name = trim((string) ($option['name'] ?? ''));

                    if (!in_array($datatype, $dateTypes, true)) {
                        continue;
                    }

                    if ($field === '' || $field === 'id') {
                        continue;
                    }

                    $tableName = self::getItemtypeTableName($itemtype);
                    $fieldId = ($tableName !== '' ? $tableName : $itemtype) . '.' . $field;

                    if (!isset($fields[$fieldId])) {
                        $fieldLabel = self::buildObservedFieldLabel($itemtype, $field, $name);
                        error_log('[AlertsManager] Added field: ' . $fieldId . ' => ' . $fieldLabel);
                        $fields[$fieldId] = [
                            'id'    => $fieldId,
                            'label' => $fieldLabel,
                        ];
                    }
                }
            } catch (\Throwable $e) {
                error_log('[AlertsManager] Error processing ' . $itemtype . ': ' . $e->getMessage());
            }
        }

        error_log('[AlertsManager] Found ' . count($fields) . ' standard date fields');

        // Add custom fields from plugin Fields
        $customFields = self::getPluginFieldsDateFields();
        $fields = array_merge($fields, $customFields);

        error_log('[AlertsManager] Found ' . count($customFields) . ' custom date fields from plugin Fields');
        error_log('[AlertsManager] Total: ' . count($fields) . ' date fields');

        ksort($fields, SORT_STRING);

        return array_values($fields);
    }

    private static function buildObservedFieldLabel(string $itemtype, string $field, string $name = ''): string
    {
        $typeLabel = self::getItemtypeLabel($itemtype);
        $fieldLabel = $name !== '' ? $name : self::humanizeFieldName($field);

        return sprintf('%s - %s', $typeLabel, $fieldLabel);
    }

    private static function getItemtypeTableName(string $itemtype): string
    {
        if ($itemtype === '' || !class_exists($itemtype)) {
            return '';
        }

        try {
            $item = new $itemtype();
            if (method_exists($item, 'getTable')) {
                $tableName = (string) $item->getTable();
                if ($tableName !== '') {
                    return $tableName;
                }
            }
        } catch (\Throwable $e) {
            // Fall back to an empty string below.
        }

        return '';
    }

    private static function getItemtypeLabel(string $itemtype): string
    {
        if ($itemtype === '') {
            return __('Unknown');
        }

        if (class_exists($itemtype) && method_exists($itemtype, 'getTypeName')) {
            try {
                $label = call_user_func([$itemtype, 'getTypeName'], 1);
                if (is_string($label) && $label !== '') {
                    return $label;
                }
            } catch (\Throwable $e) {
                // Fall back to a humanized label below.
            }
        }

        return self::humanizeFieldName($itemtype);
    }

    private static function humanizeFieldName(string $value): string
    {
        $value = str_replace(['_', '-'], ' ', $value);
        $value = preg_replace('/(?<!^)([A-Z])/', ' $1', $value) ?? $value;

        return ucwords(trim($value));
    }

    private static function getPluginFieldsDateFields(): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $fields = [];
        $dateTypes = ['date', 'datetime', 'timestamp'];

        if (!class_exists('PluginFieldsContainer') || !class_exists('PluginFieldsField')) {
            error_log('[AlertsManager] Plugin Fields classes are not available');

            return $fields;
        }

        $containerObj = new PluginFieldsContainer();
        $fieldObj = new PluginFieldsField();

        try {
            $containers = $containerObj->find(['is_active' => 1], 'name');

            foreach ($containers as $container) {
                $containerId = (int) ($container['id'] ?? 0);
                $containerLabel = (string) ($container['label'] ?? '');
                $itemtypes = (string) ($container['itemtypes'] ?? '');
                $decodedItemtypes = $itemtypes !== '' ? PluginFieldsToolbox::decodeJSONItemtypes($itemtypes) : [];

                if ($containerId <= 0) {
                    continue;
                }

                if ($containerLabel === '') {
                    $containerLabel = (string) ($container['name'] ?? '');
                }

                $fieldsIterator = $fieldObj->find([
                    'plugin_fields_containers_id' => $containerId,
                    'is_active' => 1,
                ], 'ranking');

                if ($decodedItemtypes === []) {
                    continue;
                }

                foreach ($fieldsIterator as $row) {
                    $fieldType = (string) ($row['type'] ?? '');
                    $fieldName = (string) ($row['name'] ?? '');
                    $fieldLabel = (string) ($row['label'] ?? '');

                    if ($fieldName === '' || !in_array($fieldType, $dateTypes, true)) {
                        continue;
                    }

                    $fieldId = 'plugin_fields_' . $containerId . '.' . $fieldName;
                    $finalLabel = sprintf('%s - %s', $containerLabel, $fieldLabel !== '' ? $fieldLabel : $fieldName);

                    if (!isset($fields[$fieldId])) {
                        error_log('[AlertsManager] Added custom field: ' . $fieldId . ' => ' . $finalLabel . ' (' . implode(', ', $decodedItemtypes) . ')');
                        $fields[$fieldId] = [
                            'id'    => $fieldId,
                            'label' => $finalLabel,
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('[AlertsManager] Error querying plugin Fields: ' . $e->getMessage());
        }

        return $fields;
    }
}
