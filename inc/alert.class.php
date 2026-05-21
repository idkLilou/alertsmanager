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

require_once __DIR__ . '/alert_trigger_engine.class.php';
require_once __DIR__ . '/alert_mailer.class.php';
require_once __DIR__ . '/alert_target.class.php';

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

    public static function getIcon()
    {
        return 'ti ti-bell-ringing';
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

    public static function cronInfo($name)
    {
        return match ($name) {
            'runalerts' => [
                'description' => __('Evaluate alerts and send notifications', 'alertsmanager'),
            ],
            default => [],
        };
    }

    public static function cronRunalerts(CronTask $task): int
    {
        $now = new DateTimeImmutable('now');
        $alerts = new self();
        $iterator = $alerts->find(['is_active' => 1]);

        self::logCronDiagnostic($task, sprintf('[alertsmanager] cronRunalerts start at %s', $now->format(DateTimeInterface::ATOM)));

        $totalDue = 0;
        $activeAlerts = is_countable($iterator) ? count($iterator) : 0;
        self::logCronDiagnostic($task, sprintf('[alertsmanager] Active alerts found: %d', $activeAlerts));

        foreach ($iterator as $row) {
            $alert = new self();
            if (!$alert->getFromDB((int) ($row['id'] ?? 0))) {
                self::logCronDiagnostic($task, sprintf('[alertsmanager] Skipping alert row without DB record: %s', json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
                continue;
            }

            self::logCronDiagnostic($task, sprintf(
                '[alertsmanager] Evaluating alert #%d "%s"',
                (int) $alert->getID(),
                (string) ($alert->fields['name'] ?? '')
            ));

            try {
                $evaluation = $alert->getTriggerEvaluation($now);
            } catch (Throwable $e) {
                self::logCronDiagnostic($task, sprintf('[alertsmanager] Alert #%d evaluation exception: %s', (int) $alert->getID(), $e->getMessage()));
                continue;
            }

            if (!($evaluation['success'] ?? false)) {
                self::logCronDiagnostic($task, sprintf('[alertsmanager] Alert #%d evaluation failed', (int) $alert->getID()));
                foreach (($evaluation['errors'] ?? []) as $error) {
                    self::logCronDiagnostic($task, sprintf('[alertsmanager] Alert %d: %s', (int) $alert->getID(), $error));
                }
                continue;
            }

            $items = (array) ($evaluation['items'] ?? []);
            self::logCronDiagnostic($task, sprintf('[alertsmanager] Alert #%d due items: %d', (int) $alert->getID(), count($items)));

            foreach ($items as $item) {
                $context = [
                    'event'                   => 'cron_run',
                    'trigger_itemtype'        => (string) ($item['itemtype'] ?? ''),
                    'trigger_items_id'        => (int) ($item['items_id'] ?? 0),
                    'trigger_field'           => (string) ($item['field'] ?? ''),
                    'trigger_field_value'     => (string) ($item['field_value'] ?? ''),
                    'trigger_due_date'        => (string) ($item['due_date'] ?? ''),
                    'trigger_item_name'       => (string) ($item['item_name'] ?? ''),
                    'trigger_item_url'        => (string) ($item['item_url'] ?? ''),
                    'trigger_item_type_label' => (string) ($item['item_type_label'] ?? ''),
                    'entity_name'             => (string) ($item['entity_name'] ?? ''),
                ];

                self::logCronDiagnostic($task, sprintf(
                    '[alertsmanager] Alert #%d item due: %s #%d field=%s value=%s due=%s entity=%s',
                    (int) $alert->getID(),
                    (string) ($item['item_type_label'] ?? (string) ($item['itemtype'] ?? '')),
                    (int) ($item['items_id'] ?? 0),
                    (string) ($item['field'] ?? ''),
                    (string) ($item['field_value'] ?? ''),
                    (string) ($item['due_date'] ?? ''),
                    (string) ($item['entity_name'] ?? '')
                ));

                try {
                    $result = $alert->sendMail($context);
                } catch (Throwable $e) {
                    self::logCronDiagnostic($task, sprintf('[alertsmanager] Alert #%d send exception: %s', (int) $alert->getID(), $e->getMessage()));
                    continue;
                }
                if (($result['success'] ?? false) === true) {
                    $totalDue++;
                    $task->addVolume(1);
                    self::logCronDiagnostic($task, sprintf('[alertsmanager] Alert #%d item sent successfully to %s recipient(s)', (int) $alert->getID(), (string) count((array) ($result['recipients'] ?? []))));
                    continue;
                }

                self::logCronDiagnostic($task, sprintf('[alertsmanager] Alert #%d item send failed', (int) $alert->getID()));
                foreach (($result['errors'] ?? []) as $error) {
                    self::logCronDiagnostic($task, sprintf('[alertsmanager] Alert %d send failed: %s', (int) $alert->getID(), $error));
                }
            }
        }

        self::logCronDiagnostic($task, sprintf('[alertsmanager] cronRunalerts end: %d item(s) sent', $totalDue));

        return $totalDue > 0 ? 1 : 0;
    }

    private static function logCronDiagnostic(CronTask $task, string $message): void
    {
        $task->log($message);
        error_log($message);
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        return [];
    }

    public function defineTabs($options = [])
    {
        $ong = [];
        $this->addDefaultFormTab($ong);
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

    public function sendMail(array $context = []): array
    {
        if (!isset($this->fields['id'])) {
            return [
                'success' => false,
                'sent'    => 0,
                'errors'  => ['Alert is not loaded'],
            ];
        }

        return PluginAlertsmanagerAlertMailer::sendAlertItem($this, $context);
    }

    public function getTriggerEvaluation(?DateTimeInterface $now = null): array
    {
        if (!isset($this->fields['id'])) {
            return [
                'success' => false,
                'errors'  => ['Alert is not loaded'],
                'items'   => [],
            ];
        }

        return PluginAlertsmanagerAlertTriggerEngine::evaluateAlert((int) $this->fields['id'], $now);
    }

    public function showForm($ID, $options = [])
    {
        error_log('[AlertsManager] showForm() called with ID=' . $ID);
        
        if ($ID > 0) {
            $this->getFromDB($ID);
        } else {
            $this->getEmpty();
        }

        $trigger = $this->getTriggerForDisplay((int) ($this->fields['id'] ?? 0));
        if (!empty($trigger)) {
            $this->fields = array_merge($this->fields, $trigger);
        }

        $this->fields['_targets'] = $this->getTargetsForDisplay((int) ($this->fields['id'] ?? 0));
        $this->fields['_target_ids'] = array_map(static function (array $target): int {
            return (int) ($target['id'] ?? 0);
        }, $this->fields['_targets']);
        $this->fields['target_type'] = $this->getTargetTypeForDisplay((int) ($this->fields['id'] ?? 0));
        $this->fields['_available_fields'] = self::getAvailableObservedFields();
        $grouped = ['core' => [], 'glpi' => [], 'plugin_fields' => []];
        foreach ($this->fields['_available_fields'] as $f) {
            $src = $f['source'] ?? 'glpi';
            if (!isset($grouped[$src])) {
                $grouped[$src] = [];
            }
            $grouped[$src][] = $f;
        }
        $this->fields['_available_fields_grouped'] = $grouped;

        error_log('[AlertsManager] Fields loaded: _targets=' . count($this->fields['_targets']) . ', target_type=' . $this->fields['target_type'] . ', _available_fields=' . count($this->fields['_available_fields']));

        $twig = TemplateRenderer::getInstance();
        $twig->display('@alertsmanager/alert_form.html.twig', [
            'item' => $this,
        ]);

        return true;
    }

    private function getTriggerForDisplay(int $alertId): array
    {
        if ($alertId <= 0) {
            return [];
        }

        /** @var DBmysql $DB */
        global $DB;

        $res = $DB->request([
            'SELECT' => [
                'trigger_type',
                'observed_field',
                'observed_itemtype',
                'start_date',
                'trigger_days_before',
                'trigger_months_before',
                'frequency',
                'frequency_hour',
                'frequency_minute',
            ],
            'FROM'   => 'glpi_plugin_alertsmanager_alert_triggers',
            'WHERE'  => ['plugin_alertsmanager_alerts_id' => $alertId],
            'ORDER'  => ['id'],
            'LIMIT'  => 1,
        ]);

        foreach ($res as $row) {
            return [
                'trigger_type'          => $row['trigger_type'] ?? '',
                'observed_field'        => $row['observed_field'] ?? '',
                'observed_itemtype'     => $row['observed_itemtype'] ?? '',
                'trigger_start_date'    => $row['start_date'] ?? null,
                'trigger_days_before'   => $row['trigger_days_before'] ?? '',
                'trigger_months_before' => $row['trigger_months_before'] ?? '',
                'frequency'             => $row['frequency'] ?? '',
                'frequency_hour'        => $row['frequency_hour'] ?? '',
                'frequency_minute'      => $row['frequency_minute'] ?? '',
            ];
        }

        return [];
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
            $res = $DB->request([
                'SELECT' => ['id'],
                'FROM'   => $table,
                'WHERE'  => ['plugin_alertsmanager_alerts_id' => $alertId],
                'LIMIT'  => 1,
            ]);
            if (count($res) > 0) {
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

        $appendTarget = static function (int $id, string $label) use (&$targets): void {
            $label = trim($label);
            if ($id > 0 && $label !== '') {
                $targets[] = [
                    'id'    => $id,
                    'label' => $label,
                ];
            }
        };

        if ($type === 'User') {
            $links = $DB->request([
                'SELECT' => ['users_id'],
                'FROM'   => 'glpi_plugin_alertsmanager_alert_users',
                'WHERE'  => ['plugin_alertsmanager_alerts_id' => $alertId],
            ]);

            $userIds = [];
            foreach ($links as $link) {
                $userIds[] = (int) $link['users_id'];
            }

            if (!empty($userIds)) {
                foreach ($userIds as $userId) {
                    $userRes = $DB->request([
                        'SELECT' => ['id', 'firstname', 'name'],
                        'FROM'   => 'glpi_users',
                        'WHERE'  => ['id' => $userId],
                        'LIMIT'  => 1,
                    ]);

                    foreach ($userRes as $user) {
                        $label = trim((string) ($user['firstname'] ?? '') . ' ' . (string) ($user['name'] ?? ''));
                        $appendTarget($userId, $label !== '' ? $label : (string) ($user['name'] ?? ''));
                    }
                }
            }
        } elseif ($type === 'Group') {
            $links = $DB->request([
                'SELECT' => ['groups_id'],
                'FROM'   => 'glpi_plugin_alertsmanager_alert_groups',
                'WHERE'  => ['plugin_alertsmanager_alerts_id' => $alertId],
            ]);

            $groupIds = [];
            foreach ($links as $link) {
                $groupIds[] = (int) $link['groups_id'];
            }

            if (!empty($groupIds)) {
                foreach ($groupIds as $groupId) {
                    $groupRes = $DB->request([
                        'SELECT' => ['id', 'name'],
                        'FROM'   => 'glpi_groups',
                        'WHERE'  => ['id' => $groupId],
                        'LIMIT'  => 1,
                    ]);

                    foreach ($groupRes as $group) {
                        $appendTarget($groupId, (string) ($group['name'] ?? ''));
                    }
                }
            }
        } elseif ($type === 'Profile') {
            $links = $DB->request([
                'SELECT' => ['profiles_id'],
                'FROM'   => 'glpi_plugin_alertsmanager_alert_profiles',
                'WHERE'  => ['plugin_alertsmanager_alerts_id' => $alertId],
            ]);

            $profileIds = [];
            foreach ($links as $link) {
                $profileIds[] = (int) $link['profiles_id'];
            }

            if (!empty($profileIds)) {
                foreach ($profileIds as $profileId) {
                    $profileRes = $DB->request([
                        'SELECT' => ['id', 'name'],
                        'FROM'   => 'glpi_profiles',
                        'WHERE'  => ['id' => $profileId],
                        'LIMIT'  => 1,
                    ]);

                    foreach ($profileRes as $profile) {
                        $appendTarget($profileId, (string) ($profile['name'] ?? ''));
                    }
                }
            }
        }

        return $targets;
    }

    public static function getAvailableObservedFields(): array
    {
        $fields = [];

        error_log('[AlertsManager] getAvailableObservedFields() started');
        $standardFields = self::getStandardDateFieldsFromInformationSchema();
        $fallbackFields = self::getStandardDateFieldsFromItemtypes();
        $fields = $standardFields + $fallbackFields;

        error_log('[AlertsManager] Found ' . count($standardFields) . ' standard date fields from information_schema');
        error_log('[AlertsManager] Found ' . count($fallbackFields) . ' fallback standard date fields from itemtypes');

        // Add custom fields from plugin Fields
        $customFields = self::getPluginFieldsDateFields();
        $fields = array_merge($fields, $customFields);

        error_log('[AlertsManager] Found ' . count($customFields) . ' custom date fields from plugin Fields');
        error_log('[AlertsManager] Total: ' . count($fields) . ' date fields');

        ksort($fields, SORT_STRING);

        return array_values($fields);
    }

    private static function getStandardDateFieldsFromInformationSchema(): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $fields = [];

        try {
            $query = "SELECT table_name, column_name, data_type
                      FROM information_schema.columns
                      WHERE table_name LIKE 'glpi_%'
                        AND LOWER(data_type) IN ('date', 'datetime', 'timestamp')
                        AND table_name NOT LIKE 'glpi_plugin_fields_%'
                      ORDER BY table_name, column_name";

            $result = $DB->query($query);
            if ($result === false) {
                throw new RuntimeException('information_schema query failed');
            }

            while ($row = $result->fetch_assoc()) {
                $tableName = trim((string) ($row['table_name'] ?? ''));
                $columnName = trim((string) ($row['column_name'] ?? ''));

                if ($tableName === '' || $columnName === '' || $columnName === 'id') {
                    continue;
                }

                $fieldId = $tableName . '.' . $columnName;
                if (isset($fields[$fieldId])) {
                    continue;
                }

                $fieldLabel = self::buildObservedFieldLabelFromTable($tableName, $columnName);
                error_log('[AlertsManager] Added field from information_schema: ' . $fieldId . ' => ' . $fieldLabel);
                $fields[$fieldId] = [
                    'id'    => $fieldId,
                    'label' => $fieldLabel,
                    'source'=> 'core',
                ];
            }

            if (method_exists($result, 'free')) {
                $result->free();
            }
        } catch (\Throwable $e) {
            error_log('[AlertsManager] Error querying information_schema for date fields: ' . $e->getMessage());
        }

        return $fields;
    }

    private static function getStandardDateFieldsFromItemtypes(): array
    {
        $fields = [];
        $dateTypes = ['date', 'datetime', 'timestamp'];

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
            'ProjectTask',
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
                continue;
            }

            try {
                $item = new $itemtype();
                if (!method_exists($item, 'rawSearchOptions')) {
                    continue;
                }

                foreach ((array) $item->rawSearchOptions() as $option) {
                    $datatype = (string) ($option['datatype'] ?? '');
                    $field = (string) ($option['field'] ?? '');
                    $name = trim((string) ($option['name'] ?? ''));

                    if (!in_array($datatype, $dateTypes, true) || $field === '' || $field === 'id') {
                        continue;
                    }

                    $tableName = self::getItemtypeTableName($itemtype);
                    if ($tableName === '') {
                        continue;
                    }

                    $fieldId = $tableName . '.' . $field;
                    if (!isset($fields[$fieldId])) {
                        $fields[$fieldId] = [
                            'id'    => $fieldId,
                            'label' => self::buildObservedFieldLabel($itemtype, $field, $name),
                            'source'=> 'glpi',
                        ];
                    }
                }
            } catch (\Throwable $e) {
                error_log('[AlertsManager] Error processing fallback itemtype ' . $itemtype . ': ' . $e->getMessage());
            }
        }

        return $fields;
    }

    private static function buildObservedFieldLabel(string $itemtype, string $field, string $name = ''): string
    {
        $typeLabel = self::getItemtypeLabel($itemtype);
        $fieldLabel = $name !== '' ? $name : self::humanizeFieldName($field);

        return sprintf('%s - %s', $typeLabel, $fieldLabel);
    }

    private static function buildObservedFieldLabelFromTable(string $tableName, string $fieldName): string
    {
        return sprintf(
            '%s - %s',
            self::getTableLabel($tableName),
            self::humanizeFieldName($fieldName)
        );
    }

    private static function getTableLabel(string $tableName): string
    {
        $tableName = trim($tableName);
        if ($tableName === '') {
            return __('Unknown');
        }

        if (function_exists('getItemTypeForTable')) {
            try {
                $itemtype = (string) getItemTypeForTable($tableName);
                if ($itemtype !== '') {
                    return self::getItemtypeLabel($itemtype);
                }
            } catch (\Throwable $e) {
                // Fallback below.
            }
        }

        $baseName = preg_replace('/^glpi_/', '', $tableName) ?? $tableName;
        return self::humanizeFieldName($baseName);
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
                            'source'=> 'plugin_fields',
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
