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

require_once __DIR__ . '/alert_trigger.class.php';

class PluginAlertsmanagerAlertTriggerEngine
{
    public static function evaluateAlert(int $alertId, ?DateTimeInterface $now = null): array
    {
        error_log(sprintf('[alertsmanager] evaluateAlert start alertId=%d', $alertId));

        $alert = new PluginAlertsmanagerAlert();
        if (!$alert->getFromDB($alertId)) {
            error_log(sprintf('[alertsmanager] evaluateAlert alertId=%d not found', $alertId));

            return [
                'success' => false,
                'errors'  => [sprintf('Alert %d not found', $alertId)],
                'items'   => [],
            ];
        }

        $trigger = self::loadTrigger($alertId);
        if ($trigger === null) {
            error_log(sprintf('[alertsmanager] evaluateAlert alertId=%d has no trigger', $alertId));

            return [
                'success' => false,
                'errors'  => ['No trigger configured for this alert'],
                'items'   => [],
            ];
        }

        $now = $now ? DateTimeImmutable::createFromInterface($now) : new DateTimeImmutable('now');
        $triggerType = (int) ($trigger['trigger_type'] ?? 0);

        if ($triggerType === PluginAlertsmanagerAlertTrigger::TRIGGER_DATE_FIELD) {
            $result = self::evaluateDateFieldTrigger($alert, $trigger, $now);
            error_log(sprintf('[alertsmanager] evaluateAlert alertId=%d date trigger result items=%d success=%s', $alertId, count((array) ($result['items'] ?? [])), !empty($result['success']) ? 'yes' : 'no'));

            return $result;
        }

        if ($triggerType === PluginAlertsmanagerAlertTrigger::TRIGGER_FREQUENCY) {
            $result = self::evaluateFrequencyTrigger($alert, $trigger, $now);
            error_log(sprintf('[alertsmanager] evaluateAlert alertId=%d frequency trigger result items=%d success=%s', $alertId, count((array) ($result['items'] ?? [])), !empty($result['success']) ? 'yes' : 'no'));

            return $result;
        }

        error_log(sprintf('[alertsmanager] evaluateAlert alertId=%d unsupported trigger type=%d', $alertId, $triggerType));

        return [
            'success' => false,
            'errors'  => ['Unsupported trigger type'],
            'items'   => [],
        ];
    }

    public static function loadTrigger(int $alertId): ?array
    {
        global $DB;

        $alertId = (int) $alertId;
        if ($alertId <= 0) {
            error_log('[alertsmanager] loadTrigger invalid alertId');

            return null;
        }

        $rows = $DB->request([
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
            'LIMIT'  => 1,
        ]);

        foreach ($rows as $row) {
            error_log(sprintf('[alertsmanager] loadTrigger alertId=%d trigger_type=%s observed_field=%s observed_itemtype=%s start_date=%s days_before=%s months_before=%s frequency=%s',
                $alertId,
                (string) ($row['trigger_type'] ?? ''),
                (string) ($row['observed_field'] ?? ''),
                (string) ($row['observed_itemtype'] ?? ''),
                (string) ($row['start_date'] ?? ''),
                (string) ($row['trigger_days_before'] ?? ''),
                (string) ($row['trigger_months_before'] ?? ''),
                (string) ($row['frequency'] ?? '')
            ));

            return [
                'trigger_type'          => (int) ($row['trigger_type'] ?? 0),
                'observed_field'        => (string) ($row['observed_field'] ?? ''),
                'observed_itemtype'     => (string) ($row['observed_itemtype'] ?? ''),
                'start_date'            => (string) ($row['start_date'] ?? ''),
                'trigger_days_before'   => (int) ($row['trigger_days_before'] ?? 0),
                'trigger_months_before' => (int) ($row['trigger_months_before'] ?? 0),
                'frequency'             => (string) ($row['frequency'] ?? ''),
                'frequency_hour'        => (int) ($row['frequency_hour'] ?? 0),
                'frequency_minute'      => (int) ($row['frequency_minute'] ?? 0),
            ];
        }

        error_log(sprintf('[alertsmanager] loadTrigger alertId=%d no rows found', $alertId));

        return null;
    }

    private static function evaluateDateFieldTrigger(PluginAlertsmanagerAlert $alert, array $trigger, DateTimeImmutable $now): array
    {
        global $DB;

        $observedField = trim((string) ($trigger['observed_field'] ?? ''));
        [$tableName, $fieldName] = self::splitObservedField($observedField, (string) ($trigger['observed_itemtype'] ?? ''));

        if ($tableName === '' || $fieldName === '') {
            error_log(sprintf('[alertsmanager] evaluateDateFieldTrigger invalid field config alert=%d observedField=%s observedItemtype=%s', (int) $alert->getID(), $observedField, (string) ($trigger['observed_itemtype'] ?? '')));

            return [
                'success' => false,
                'errors'  => ['Invalid observed field configuration'],
                'items'   => [],
            ];
        }

        if (!self::isSafeIdentifier($tableName) || !self::isSafeIdentifier($fieldName)) {
            error_log(sprintf('[alertsmanager] evaluateDateFieldTrigger unsafe identifiers alert=%d table=%s field=%s', (int) $alert->getID(), $tableName, $fieldName));

            return [
                'success' => false,
                'errors'  => ['Unsafe observed field configuration'],
                'items'   => [],
            ];
        }

        $startDate = self::parseDate((string) ($trigger['start_date'] ?? ''));
        if ($startDate !== null && $now < $startDate) {
            return [
                'success' => true,
                'errors'  => [],
                'items'   => [],
            ];
        }

        if (str_starts_with($observedField, 'plugin_fields_')) {
            return self::evaluatePluginFieldsTrigger($alert, $trigger, $now, $observedField);
        }

        $rows = $DB->request([
            'SELECT' => ['id', $fieldName],
            'FROM'   => $tableName,
        ]);

        error_log(sprintf('[alertsmanager] evaluateDateFieldTrigger alert=%d reading table=%s field=%s', (int) $alert->getID(), $tableName, $fieldName));

        $items = [];
        foreach ($rows as $row) {
            $itemId = (int) ($row['id'] ?? 0);
            $sourceValue = trim((string) ($row[$fieldName] ?? ''));
            $sourceDate = self::parseDate($sourceValue);
            if ($itemId <= 0 || $sourceDate === null) {
                continue;
            }

            $dueDate = self::computeDueDate(
                $sourceDate,
                (int) ($trigger['trigger_months_before'] ?? 0),
                (int) ($trigger['trigger_days_before'] ?? 0)
            );

            if ((int) ($alert->getID()) > 0) {
                error_log(sprintf('[alertsmanager] evaluateDateFieldTrigger row alert=%d item=%d source=%s due=%s now=%s', (int) $alert->getID(), $itemId, $sourceDate->format(DateTimeInterface::ATOM), $dueDate->format(DateTimeInterface::ATOM), $now->format(DateTimeInterface::ATOM)));
            }

            if ($dueDate->format('Y-m-d') !== $now->format('Y-m-d')) {
                continue;
            }

            $itemDetails = self::buildItemDetails($tableName, $itemId);

            $items[] = [
                'itemtype'           => $itemDetails['itemtype'],
                'items_id'           => $itemId,
                'field'              => $fieldName,
                'field_value'        => $sourceValue,
                'due_date'           => $dueDate->format('Y-m-d'),
                'status'             => 'due',
                'item_name'          => $itemDetails['item_name'],
                'item_url'           => $itemDetails['item_url'],
                'item_type_label'    => $itemDetails['item_type_label'],
                'entity_name'        => $itemDetails['entity_name'],
            ];
        }

        return [
            'success' => true,
            'errors'  => [],
            'items'   => $items,
        ];
    }

    private static function evaluatePluginFieldsTrigger(
        PluginAlertsmanagerAlert $alert,
        array $trigger,
        DateTimeImmutable $now,
        string $observedField
    ): array {
        if (!class_exists('PluginFieldsContainer') || !class_exists('PluginFieldsToolbox')) {
            error_log(sprintf('[alertsmanager] evaluatePluginFieldsTrigger alert=%d Plugin Fields classes unavailable', (int) $alert->getID()));

            return [
                'success' => false,
                'errors'  => ['Plugin Fields classes are not available'],
                'items'   => [],
            ];
        }

        if (!preg_match('/^plugin_fields_(\d+)\.(.+)$/', $observedField, $matches)) {
            error_log(sprintf('[alertsmanager] evaluatePluginFieldsTrigger alert=%d invalid observedField=%s', (int) $alert->getID(), $observedField));

            return [
                'success' => false,
                'errors'  => ['Invalid plugin fields configuration'],
                'items'   => [],
            ];
        }

        $containerId = (int) $matches[1];
        $pluginFieldName = trim((string) $matches[2]);

        if ($containerId <= 0 || $pluginFieldName === '') {
            error_log(sprintf('[alertsmanager] evaluatePluginFieldsTrigger alert=%d invalid container or field from observedField=%s', (int) $alert->getID(), $observedField));

            return [
                'success' => false,
                'errors'  => ['Invalid plugin fields configuration'],
                'items'   => [],
            ];
        }

        $container = new PluginFieldsContainer();
        if (!$container->getFromDB($containerId)) {
            error_log(sprintf('[alertsmanager] evaluatePluginFieldsTrigger alert=%d container %d not found', (int) $alert->getID(), $containerId));

            return [
                'success' => false,
                'errors'  => [sprintf('Plugin Fields container %d not found', $containerId)],
                'items'   => [],
            ];
        }

        $itemtypesRaw = (string) ($container->fields['itemtypes'] ?? '');
        $decodedItemtypes = $itemtypesRaw !== '' ? PluginFieldsToolbox::decodeJSONItemtypes($itemtypesRaw) : [];
        error_log(sprintf('[alertsmanager] evaluatePluginFieldsTrigger alert=%d container=%d field=%s itemtypes=%s', (int) $alert->getID(), $containerId, $pluginFieldName, implode(',', (array) $decodedItemtypes)));

        if ($decodedItemtypes === []) {
            return [
                'success' => false,
                'errors'  => ['No itemtypes available for this plugin field'],
                'items'   => [],
            ];
        }

        $items = [];
        $totalScanned = 0;
        $totalWithValue = 0;
        $totalDue = 0;
        foreach ($decodedItemtypes as $itemtype) {
            $itemtype = trim((string) $itemtype);
            if ($itemtype === '' || !class_exists($itemtype)) {
                error_log(sprintf('[alertsmanager] evaluatePluginFieldsTrigger alert=%d skipping unsupported itemtype=%s', (int) $alert->getID(), $itemtype));
                continue;
            }

            $tableName = self::getItemtypeTableName($itemtype);
            if ($tableName === '') {
                error_log(sprintf('[alertsmanager] evaluatePluginFieldsTrigger alert=%d itemtype=%s has no table', (int) $alert->getID(), $itemtype));
                continue;
            }

            $itemObject = new $itemtype();
            $rows = $itemObject->find([], 'id');

            error_log(sprintf('[alertsmanager] evaluatePluginFieldsTrigger alert=%d reading items via object type=%s table=%s field=%s count=%d', (int) $alert->getID(), $itemtype, $tableName, $pluginFieldName, is_countable($rows) ? count($rows) : 0));

            $itemtypeScanned = 0;
            $itemtypeWithValue = 0;
            $itemtypeDue = 0;

            foreach ($rows as $row) {
                $itemId = (int) ($row['id'] ?? 0);
                if ($itemId <= 0) {
                    continue;
                }

                $itemtypeScanned++;
                $totalScanned++;

                $sourceValue = self::extractPluginFieldValue($itemtype, $itemId, $pluginFieldName, $containerId, $container->fields['name'] ?? '', $observedField);
                if ($sourceValue !== '') {
                    $itemtypeWithValue++;
                    $totalWithValue++;
                }

                $sourceDate = self::parseDate($sourceValue);
                if ($sourceDate === null) {
                    continue;
                }

                $dueDate = self::computeDueDate(
                    $sourceDate,
                    (int) ($trigger['trigger_months_before'] ?? 0),
                    (int) ($trigger['trigger_days_before'] ?? 0)
                );

                if ($dueDate->format('Y-m-d') !== $now->format('Y-m-d')) {
                    continue;
                }

                $itemtypeDue++;
                $totalDue++;

                $itemDetails = self::buildItemDetails($tableName, $itemId);
                $items[] = [
                    'itemtype'           => $itemDetails['itemtype'],
                    'items_id'           => $itemId,
                    'field'              => $pluginFieldName,
                    'field_value'        => $sourceValue,
                    'due_date'           => $dueDate->format('Y-m-d'),
                    'status'             => 'due',
                    'item_name'          => $itemDetails['item_name'],
                    'item_url'           => $itemDetails['item_url'],
                    'item_type_label'    => $itemDetails['item_type_label'],
                    'entity_name'        => $itemDetails['entity_name'],
                ];
            }

            error_log(sprintf('[alertsmanager] evaluatePluginFieldsTrigger summary alert=%d itemtype=%s scanned=%d withValue=%d due=%d', (int) $alert->getID(), $itemtype, $itemtypeScanned, $itemtypeWithValue, $itemtypeDue));
        }

        error_log(sprintf('[alertsmanager] evaluatePluginFieldsTrigger total alert=%d container=%d field=%s scanned=%d withValue=%d due=%d', (int) $alert->getID(), $containerId, $pluginFieldName, $totalScanned, $totalWithValue, $totalDue));

        return [
            'success' => true,
            'errors'  => [],
            'items'   => $items,
        ];
    }

    private static function extractPluginFieldValue(
        string $itemtype,
        int $itemId,
        string $pluginFieldName,
        int $containerId,
        string $containerName,
        string $observedField
    ): string {
        if ($itemId <= 0 || $itemtype === '' || $containerName === '') {
            return '';
        }

        if (!class_exists('PluginFieldsContainer')) {
            return '';
        }

        $classname = PluginFieldsContainer::getClassname($itemtype, $containerName);
        if ($classname === '' || !class_exists($classname)) {
            return '';
        }

        $obj = new $classname();
        $rows = $obj->find([
            'plugin_fields_containers_id' => $containerId,
            'items_id'                    => $itemId,
        ]);

        if ($rows === []) {
            return '';
        }

        $row = array_shift($rows);
        if (!is_array($row) || !array_key_exists($pluginFieldName, $row)) {
            foreach ($row as $fieldKey => $fieldValue) {
                $fieldKey = (string) $fieldKey;
                if (in_array($fieldKey, ['id', 'items_id', 'itemtype', 'plugin_fields_containers_id'], true)) {
                    continue;
                }

                if (str_starts_with($fieldKey, 'itemtype_') || str_starts_with($fieldKey, 'items_id_')) {
                    continue;
                }

                $value = trim((string) $fieldValue);
                if ($value === '' || strtoupper($value) === 'N/A') {
                    continue;
                }

                if (!self::looksLikeDateString($value)) {
                    continue;
                }

                $parsedValue = self::parseDate($value);
                if ($parsedValue !== null) {
                    return $value;
                }
            }

            return '';
        }

        $value = trim((string) $row[$pluginFieldName]);
        if ($value !== '' && strtoupper($value) !== 'N/A') {
            return $value;
        }

        return '';
    }

    private static function looksLikeDateString(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2})?)?$/', $value) === 1;
    }

    private static function evaluateFrequencyTrigger(PluginAlertsmanagerAlert $alert, array $trigger, DateTimeImmutable $now): array
    {
        $frequency = strtolower(trim((string) ($trigger['frequency'] ?? '')));
        error_log(sprintf('[alertsmanager] evaluateFrequencyTrigger alert=%d frequency=%s now=%s', (int) $alert->getID(), $frequency, $now->format(DateTimeInterface::ATOM)));

        $startDate = self::parseDate((string) ($trigger['start_date'] ?? ''));
        if ($startDate !== null && $now < $startDate) {
            error_log(sprintf('[alertsmanager] evaluateFrequencyTrigger alert=%d before start date=%s', (int) $alert->getID(), $startDate->format(DateTimeInterface::ATOM)));

            return [
                'success' => true,
                'errors'  => [],
                'items'   => [],
            ];
        }

        $isDue = false;
        switch ($frequency) {
            case 'daily':
                $isDue = true;
                break;
            case 'monday':
            case 'tuesday':
            case 'wednesday':
            case 'thursday':
            case 'friday':
            case 'saturday':
            case 'sunday':
                $isDue = strtolower($now->format('l')) === $frequency;
                break;
            case 'monthly':
                $referenceDay = $startDate !== null ? (int) $startDate->format('j') : (int) $now->format('j');
                $isDue = (int) $now->format('j') === $referenceDay;
                break;
            default:
                $isDue = false;
                break;
        }

        return [
            'success' => true,
            'errors'  => [],
            'items'   => $isDue ? [[
                'itemtype'    => 'frequency',
                'items_id'    => 0,
                'field'       => $frequency,
                'field_value' => $now->format(DateTimeInterface::ATOM),
                'due_date'    => $now->format('Y-m-d'),
                'status'      => 'due',
            ]] : [],
        ];
    }

    private static function splitObservedField(string $observedField, string $observedItemtype): array
    {
        $observedField = trim($observedField);
        $observedItemtype = trim($observedItemtype);

        if ($observedField !== '' && strpos($observedField, '.') !== false) {
            [$tableName, $fieldName] = explode('.', $observedField, 2);

            return [trim($tableName), trim($fieldName)];
        }

        if ($observedItemtype !== '' && $observedField !== '') {
            return [$observedItemtype, $observedField];
        }

        return ['', ''];
    }

    private static function getItemtypeTableName(string $itemtype): string
    {
        $itemtype = trim($itemtype);
        if ($itemtype === '') {
            return '';
        }

        if (function_exists('getTableForItemType')) {
            try {
                $tableName = (string) getTableForItemType($itemtype);
                if ($tableName !== '') {
                    return $tableName;
                }
            } catch (Throwable $e) {
                error_log(sprintf('[alertsmanager] getItemtypeTableName failed for itemtype=%s error=%s', $itemtype, $e->getMessage()));
            }
        }

        if (class_exists($itemtype)) {
            try {
                $item = new $itemtype();
                if (method_exists($item, 'getTable')) {
                    return (string) $item->getTable();
                }
            } catch (Throwable $e) {
                error_log(sprintf('[alertsmanager] getItemtypeTableName fallback failed for itemtype=%s error=%s', $itemtype, $e->getMessage()));
            }
        }

        return '';
    }

    private static function computeDueDate(DateTimeImmutable $sourceDate, int $monthsBefore, int $daysBefore): DateTimeImmutable
    {
        $dueDate = $sourceDate;

        if ($monthsBefore > 0) {
            $dueDate = $dueDate->modify('-' . $monthsBefore . ' months');
        }

        if ($daysBefore > 0) {
            $dueDate = $dueDate->modify('-' . $daysBefore . ' days');
        }

        return $dueDate;
    }

    private static function parseDate(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '' || strtoupper($value) === 'N/A') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable $e) {
            error_log(sprintf('[alertsmanager] parseDate failed for value=%s error=%s', $value, $e->getMessage()));

            return null;
        }
    }

    private static function isSafeIdentifier(string $value): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_]+$/', $value);
    }

    private static function buildItemDetails(string $tableName, int $itemId): array
    {
        $itemClass = self::getItemClassFromTableName($tableName);
        $itemLabel = $itemClass && class_exists($itemClass) ? $itemClass::getTypeName(1) : $tableName;
        $itemName = sprintf('#%d', $itemId);
        $itemUrl = '';
        $entityName = '';

        if ($itemClass !== null && class_exists($itemClass)) {
            $item = new $itemClass();
            if ($item instanceof CommonDBTM && $item->getFromDB($itemId)) {
                $itemName = trim((string) $item->getName());
                if ($itemName === '') {
                    $itemName = sprintf('#%d', $itemId);
                }

                $itemUrl = self::buildAbsoluteUrl($item->getLinkURL());
                if (method_exists($item, 'getEntityID')) {
                    $entityId = (int) $item->getEntityID();
                    if ($entityId > 0) {
                        $entity = new Entity();
                        if ($entity->getFromDB($entityId)) {
                            $entityName = trim((string) $entity->getField('completename'));
                            if ($entityName === '') {
                                $entityName = trim((string) $entity->getField('name'));
                            }
                        }
                    }
                }
            }
        }

        return [
            'itemtype'        => $itemClass ?? $tableName,
            'item_type_label' => $itemLabel,
            'item_name'       => $itemName,
            'item_url'        => $itemUrl,
            'entity_name'     => $entityName,
        ];
    }

    private static function buildAbsoluteUrl(string $relativeUrl): string
    {
        global $CFG_GLPI;

        $relativeUrl = trim($relativeUrl);
        if ($relativeUrl === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $relativeUrl)) {
            return $relativeUrl;
        }

        $baseUrl = rtrim((string) ($CFG_GLPI['url_base'] ?? ''), '/');
        if ($baseUrl === '') {
            return $relativeUrl;
        }

        return $baseUrl . '/' . ltrim($relativeUrl, '/');
    }

    private static function getItemClassFromTableName(string $tableName): ?string
    {
        return match ($tableName) {
            'glpi_tickets'         => Ticket::class,
            'glpi_problems'        => Problem::class,
            'glpi_changes'         => Change::class,
            'glpi_contracts'       => Contract::class,
            'glpi_softwarelicenses'=> SoftwareLicense::class,
            'glpi_computers'       => Computer::class,
            'glpi_printers'        => Printer::class,
            'glpi_monitors'        => Monitor::class,
            'glpi_networkequipments' => NetworkEquipment::class,
            'glpi_peripherals'     => Peripheral::class,
            'glpi_projects'        => Project::class,
            'glpi_projecttasks'    => ProjectTask::class,
            'glpi_users'           => User::class,
            'glpi_groups'          => Group::class,
            'glpi_profiles'        => Profile::class,
            'glpi_entities'        => Entity::class,
            'glpi_locations'       => Location::class,
            'glpi_suppliers'       => Supplier::class,
            'glpi_manufacturers'   => Manufacturer::class,
            'glpi_devicememories'  => DeviceMemory::class,
            'glpi_deviceprocessors'=> DeviceProcessor::class,
            'glpi_devicefirmwares' => DeviceFirmware::class,
            default                => null,
        };
    }
}