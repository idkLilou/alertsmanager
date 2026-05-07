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

function plugin_alertsmanager_install()
{
    /** @var DBmysql $DB */
    global $DB;

    $migration = new Migration(Plugin::getInfo('alertsmanager', 'version'));

    $default_charset   = DBConnection::getDefaultCharset();
    $default_collation = DBConnection::getDefaultCollation();
    $default_key_sign  = DBConnection::getDefaultPrimaryKeySignOption();

    // Main alerts table
    $alert_table = 'glpi_plugin_alertsmanager_alerts';
    if (!$DB->tableExists($alert_table)) {
        $DB->doQuery("
         CREATE TABLE IF NOT EXISTS `$alert_table` (
         `id`                       INT {$default_key_sign} NOT NULL AUTO_INCREMENT,
         `name`                     VARCHAR(255) NOT NULL DEFAULT '',
         `description`              TEXT,
         `is_active`                TINYINT NOT NULL DEFAULT 1,
         `mail_subject`             VARCHAR(255),
         `mail_content`             LONGTEXT,
         `entities_id`              INT {$default_key_sign} NOT NULL DEFAULT 0,
         `is_recursive`             TINYINT NOT NULL DEFAULT 0,
         `date_creation`            TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
         `date_modification`        TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
         PRIMARY KEY (`id`)
         ) ENGINE = INNODB DEFAULT CHARSET = {$default_charset} COLLATE = {$default_collation}
        ");
    }

    // Alerts targets - Users
    $alert_users_table = 'glpi_plugin_alertsmanager_alert_users';
    if (!$DB->tableExists($alert_users_table)) {
        $DB->doQuery("
         CREATE TABLE IF NOT EXISTS `$alert_users_table` (
         `id`                              INT {$default_key_sign} NOT NULL AUTO_INCREMENT,
         `plugin_alertsmanager_alerts_id`  INT {$default_key_sign} NOT NULL DEFAULT 0,
         `users_id`                        INT {$default_key_sign} NOT NULL DEFAULT 0,
         `date_creation`                   TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
         PRIMARY KEY (`id`),
         UNIQUE KEY `unique_alert_user` (`plugin_alertsmanager_alerts_id`, `users_id`)
         ) ENGINE = INNODB DEFAULT CHARSET = {$default_charset} COLLATE = {$default_collation}
        ");
    }

    // Alerts targets - Groups
    $alert_groups_table = 'glpi_plugin_alertsmanager_alert_groups';
    if (!$DB->tableExists($alert_groups_table)) {
        $DB->doQuery("
         CREATE TABLE IF NOT EXISTS `$alert_groups_table` (
         `id`                              INT {$default_key_sign} NOT NULL AUTO_INCREMENT,
         `plugin_alertsmanager_alerts_id`  INT {$default_key_sign} NOT NULL DEFAULT 0,
         `groups_id`                       INT {$default_key_sign} NOT NULL DEFAULT 0,
         `date_creation`                   TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
         PRIMARY KEY (`id`),
         UNIQUE KEY `unique_alert_group` (`plugin_alertsmanager_alerts_id`, `groups_id`)
         ) ENGINE = INNODB DEFAULT CHARSET = {$default_charset} COLLATE = {$default_collation}
        ");
    }

    // Alerts targets - Profiles
    $alert_profiles_table = 'glpi_plugin_alertsmanager_alert_profiles';
    if (!$DB->tableExists($alert_profiles_table)) {
        $DB->doQuery("
         CREATE TABLE IF NOT EXISTS `$alert_profiles_table` (
         `id`                              INT {$default_key_sign} NOT NULL AUTO_INCREMENT,
         `plugin_alertsmanager_alerts_id`  INT {$default_key_sign} NOT NULL DEFAULT 0,
         `profiles_id`                     INT {$default_key_sign} NOT NULL DEFAULT 0,
         `date_creation`                   TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
         PRIMARY KEY (`id`),
         UNIQUE KEY `unique_alert_profile` (`plugin_alertsmanager_alerts_id`, `profiles_id`)
         ) ENGINE = INNODB DEFAULT CHARSET = {$default_charset} COLLATE = {$default_collation}
        ");
    }

    // Alerts triggers
    $alert_triggers_table = 'glpi_plugin_alertsmanager_alert_triggers';
    if (!$DB->tableExists($alert_triggers_table)) {
        $DB->doQuery("
         CREATE TABLE IF NOT EXISTS `$alert_triggers_table` (
         `id`                              INT {$default_key_sign} NOT NULL AUTO_INCREMENT,
         `plugin_alertsmanager_alerts_id`  INT {$default_key_sign} NOT NULL DEFAULT 0,
         `trigger_type`                    INT {$default_key_sign} NOT NULL DEFAULT 1,
         `observed_field`                  VARCHAR(255),
         `observed_itemtype`               VARCHAR(255),
         `trigger_days_before`             INT {$default_key_sign} DEFAULT 0,
         `trigger_months_before`           INT {$default_key_sign} DEFAULT 0,
         `frequency`                       VARCHAR(50),
         `frequency_hour`                  INT {$default_key_sign} DEFAULT 12,
         `frequency_minute`                INT {$default_key_sign} DEFAULT 0,
         `date_creation`                   TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
         `date_modification`               TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
         PRIMARY KEY (`id`)
         ) ENGINE = INNODB DEFAULT CHARSET = {$default_charset} COLLATE = {$default_collation}
        ");
    }

    $migration->displayMessage("Installation completed successfully");
    return true;
}

function plugin_alertsmanager_uninstall()
{
    /** @var DBmysql $DB */
    global $DB;

    $tables = [
        'glpi_plugin_alertsmanager_alert_triggers',
        'glpi_plugin_alertsmanager_alert_profiles',
        'glpi_plugin_alertsmanager_alert_groups',
        'glpi_plugin_alertsmanager_alert_users',
        'glpi_plugin_alertsmanager_alerts',
    ];

    foreach ($tables as $table) {
        if ($DB->tableExists($table)) {
            $DB->doQuery("DROP TABLE `$table`");
        }
    }

    return true;
}

function plugin_alertsmanager_getProfileRights()
{
    $rights = [
        PluginAlertsmanagerAlert::$rightname => [
            CREATE  => __s('Create', 'alertsmanager'),
            READ    => __s('Read', 'alertsmanager'),
            UPDATE  => __s('Update', 'alertsmanager'),
            DELETE  => __s('Delete', 'alertsmanager'),
            PURGE   => __s('Purge', 'alertsmanager'),
        ],
    ];

    return $rights;
}
