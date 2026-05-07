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

/**
 * Generic Alert Target class for managing all types of targets
 */
class PluginAlertsmanagerAlertTarget
{
    const TARGET_USER = 'User';
    const TARGET_GROUP = 'Group';
    const TARGET_PROFILE = 'Profile';

    public static function getTargetTypes()
    {
        return [
            self::TARGET_USER    => __('User'),
            self::TARGET_GROUP   => __('Group'),
            self::TARGET_PROFILE => __('Profile'),
        ];
    }

    /**
     * Get the class name for a target type
     */
    public static function getTargetClass($targetType)
    {
        switch ($targetType) {
            case self::TARGET_USER:
                return 'PluginAlertsmanagerAlertTargetUser';
            case self::TARGET_GROUP:
                return 'PluginAlertsmanagerAlertTargetGroup';
            case self::TARGET_PROFILE:
                return 'PluginAlertsmanagerAlertTargetProfile';
            default:
                return null;
        }
    }

    /**
     * Get all targets for an alert
     */
    public static function getAlertTargets($alertId)
    {
        $targets = [];
        foreach (self::getTargetTypes() as $type => $label) {
            $class = self::getTargetClass($type);
            if ($class) {
                $items = new $class();
                $targetItems = $items->find([
                    'plugin_alertsmanager_alerts_id' => $alertId
                ]);
                $targets[$type] = $targetItems;
            }
        }
        return $targets;
    }

    public static function getRecipientEmailsForAlert($alertId)
    {
        $userIds = self::getRecipientUserIdsForAlert($alertId);
        return self::getEmailsForUserIds($userIds);
    }

    public static function getRecipientUserIdsForAlert($alertId)
    {
        global $DB;

        $alertId = (int) $alertId;
        if ($alertId <= 0) {
            return [];
        }

        $userIds = [];

        foreach (self::getDirectUserIds($alertId) as $userId) {
            $userIds[(int) $userId] = (int) $userId;
        }

        foreach (self::getGroupIds($alertId) as $groupId) {
            foreach (self::getUserIdsFromGroup((int) $groupId) as $userId) {
                $userIds[(int) $userId] = (int) $userId;
            }
        }

        foreach (self::getProfileIds($alertId) as $profileId) {
            foreach (self::getUserIdsFromProfile((int) $profileId) as $userId) {
                $userIds[(int) $userId] = (int) $userId;
            }
        }

        return array_values($userIds);
    }

    public static function getEmailsForUserIds(array $userIds)
    {
        global $DB;

        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if ($userIds === []) {
            return [];
        }

        $emails = [];

        $userRows = $DB->request([
            'SELECT' => ['id', 'email'],
            'FROM'   => 'glpi_users',
            'WHERE'  => [
                'id'    => $userIds,
                'email' => ['<>', ''],
            ],
        ]);

        foreach ($userRows as $row) {
            $email = trim((string) ($row['email'] ?? ''));
            if ($email !== '') {
                $emails[strtolower($email)] = $email;
            }
        }

        $extraEmailRows = $DB->request([
            'SELECT' => ['users_id', 'email'],
            'FROM'   => 'glpi_useremails',
            'WHERE'  => [
                'users_id' => $userIds,
                'email'    => ['<>', ''],
            ],
        ]);

        foreach ($extraEmailRows as $row) {
            $email = trim((string) ($row['email'] ?? ''));
            if ($email !== '') {
                $emails[strtolower($email)] = $email;
            }
        }

        return array_values($emails);
    }

    public static function getDirectUserIds($alertId)
    {
        global $DB;

        return self::getIdsFromTable('glpi_plugin_alertsmanager_alert_users', 'users_id', $alertId);
    }

    public static function getGroupIds($alertId)
    {
        global $DB;

        return self::getIdsFromTable('glpi_plugin_alertsmanager_alert_groups', 'groups_id', $alertId);
    }

    public static function getProfileIds($alertId)
    {
        global $DB;

        return self::getIdsFromTable('glpi_plugin_alertsmanager_alert_profiles', 'profiles_id', $alertId);
    }

    public static function getUserIdsFromGroup($groupId)
    {
        global $DB;

        $groupId = (int) $groupId;
        if ($groupId <= 0) {
            return [];
        }

        $userIds = [];
        $rows = $DB->request([
            'SELECT' => ['users_id'],
            'FROM'   => 'glpi_groups_users',
            'WHERE'  => [
                'groups_id' => $groupId,
            ],
        ]);

        foreach ($rows as $row) {
            $userId = (int) ($row['users_id'] ?? 0);
            if ($userId > 0) {
                $userIds[$userId] = $userId;
            }
        }

        return array_values($userIds);
    }

    public static function getUserIdsFromProfile($profileId)
    {
        global $DB;

        $profileId = (int) $profileId;
        if ($profileId <= 0) {
            return [];
        }

        $userIds = [];
        $rows = $DB->request([
            'SELECT' => ['users_id'],
            'FROM'   => 'glpi_profiles_users',
            'WHERE'  => [
                'profiles_id' => $profileId,
            ],
        ]);

        foreach ($rows as $row) {
            $userId = (int) ($row['users_id'] ?? 0);
            if ($userId > 0) {
                $userIds[$userId] = $userId;
            }
        }

        return array_values($userIds);
    }

    protected static function getIdsFromTable($table, $field, $alertId)
    {
        global $DB;

        $alertId = (int) $alertId;
        if ($alertId <= 0) {
            return [];
        }

        $ids = [];
        $rows = $DB->request([
            'SELECT' => [$field],
            'FROM'   => $table,
            'WHERE'  => [
                'plugin_alertsmanager_alerts_id' => $alertId,
            ],
        ]);

        foreach ($rows as $row) {
            $id = (int) ($row[$field] ?? 0);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }
}
