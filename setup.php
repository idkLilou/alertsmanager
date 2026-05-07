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

use function Safe\define;

define('PLUGIN_ALERTSMANAGER_VERSION', '1.0.0');

// Minimal GLPI version, inclusive
define('PLUGIN_ALERTSMANAGER_MIN_GLPI', '11.0.0');
// Maximum GLPI version, exclusive
define('PLUGIN_ALERTSMANAGER_MAX_GLPI', '11.0.99');

function plugin_init_alertsmanager()
{
    /**
     * @var array $PLUGIN_HOOKS
     * @var array $CFG_GLPI
     */
    global $PLUGIN_HOOKS, $CFG_GLPI;

    $PLUGIN_HOOKS['csrf_compliant']['alertsmanager'] = true;

    $plugin = new Plugin();
    if (
        $plugin->isInstalled('alertsmanager')
        && $plugin->isActivated('alertsmanager')
    ) {
        Plugin::registerClass('PluginAlertsmanagerProfile', ['addtabon' => 'Profile']);

        $PLUGIN_HOOKS['add_css']['alertsmanager']          = 'css/styles.css';
        $PLUGIN_HOOKS['add_javascript']['alertsmanager'][] = 'js/alertsmanager.js';

        if (Session::haveRight('plugin_alertsmanager_alert', READ) || Session::haveRight('config', UPDATE)) {
            $PLUGIN_HOOKS['menu_toadd']['alertsmanager'] = [
                'tools' => 'PluginAlertsmanagerAlert',
            ];
            $PLUGIN_HOOKS['config_page']['alertsmanager'] = 'front/alert.php';

            // require tinymce (for glpi >= 9.2)
            $CFG_GLPI['javascript']['tools']['pluginalertsmanageralert'] = ['tinymce'];
        }
    }
}

function plugin_version_alertsmanager()
{
    return [
        'name'         => __s('Alerts Manager', 'alertsmanager'),
        'version'      => PLUGIN_ALERTSMANAGER_VERSION,
        'author'       => 'Lilou DUFAU',
        'license'      => 'GPLv2+',
        'homepage'     => 'https://github.com/idkLilou/alertsmanager',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_ALERTSMANAGER_MIN_GLPI,
                'max' => PLUGIN_ALERTSMANAGER_MAX_GLPI,
            ],
        ],
    ];
}
