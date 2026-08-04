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

namespace Glpi {
    if (!class_exists(Event::class)) {
        class Event
        {
            public static function log(mixed ...$args): void {}
        }
    }
}

namespace Glpi\Application\View {
    if (!class_exists(TemplateRenderer::class)) {
        class TemplateRenderer
        {
            public static function getInstance(): self
            {
                return new self();
            }

            public function display(string $template, array $context = []): void {}
        }
    }
}

namespace {

    if (!defined('READ')) {
        define('READ', 1);
    }

    if (!defined('CREATE')) {
        define('CREATE', 2);
    }

    if (!defined('UPDATE')) {
        define('UPDATE', 4);
    }

    if (!defined('DELETE')) {
        define('DELETE', 8);
    }

    if (!defined('PURGE')) {
        define('PURGE', 16);
    }

    if (!defined('DAY_TIMESTAMP')) {
        define('DAY_TIMESTAMP', 86400);
    }

    if (!function_exists('__')) {
        function __(string $string, ?string $domain = null): string
        {
            return $string;
        }
    }

    if (!function_exists('__s')) {
        function __s(string $string, ?string $domain = null): string
        {
            return $string;
        }
    }

    if (!class_exists('CommonGLPI')) {
        class CommonGLPI
        {
            public static function getSearchURL(bool $useItemtype = true): string
            {
                return '';
            }

            public static function getFormURL(bool $useItemtype = true): string
            {
                return '';
            }

            public function addDefaultFormTab(array &$ong): void {}
        }
    }

    if (!class_exists('CommonDBTM')) {
        class CommonDBTM extends CommonGLPI
        {
            /** @var array<string, mixed> */
            public array $fields = [];

            public int $id = 0;

            public function check($ID, $right, ?array $input = null): bool
            {
                return true;
            }

            public function add(array $input)
            {
                return 1;
            }

            public function update(array $input): bool
            {
                return true;
            }

            public function delete(array $input, bool $force = false): bool
            {
                return true;
            }

            public function restore(array $input): bool
            {
                return true;
            }

            public function getFromDB($id): bool
            {
                return true;
            }

            public function getEmpty(): bool
            {
                return true;
            }

            public function getID(): int
            {
                return $this->id;
            }

            public function getTable(): string
            {
                return '';
            }

            public function getLinkURL(): string
            {
                return '';
            }

            public static function getSearchOptions(): array
            {
                return [];
            }

            public function find(array $criteria, string $orderBy = ''): array
            {
                return [];
            }

            public function display(array $options = []): void {}

            public function redirectToList(): void {}
        }
    }

    if (!class_exists('Session')) {
        class Session
        {
            public static function checkLoginUser(): void {}

            public static function haveRight(string $right, int $permission): bool
            {
                return true;
            }

            public static function getPluralNumber(): int
            {
                return 1;
            }
        }
    }

    if (!class_exists('Plugin')) {
        class Plugin
        {
            public function isInstalled(string $plugin): bool
            {
                return true;
            }

            public function isActivated(string $plugin): bool
            {
                return true;
            }

            public static function registerClass(string $classname, array $options = []): void {}

            public static function getInfo(string $plugin, string $field): string
            {
                return '';
            }
        }
    }

    if (!class_exists('Migration')) {
        class Migration
        {
            public function __construct(string $version) {}

            public function displayMessage(string $message): void {}
        }
    }

    if (!class_exists('DBConnection')) {
        class DBConnection
        {
            public static function getDefaultCharset(): string
            {
                return 'utf8mb4';
            }

            public static function getDefaultCollation(): string
            {
                return 'utf8mb4_unicode_ci';
            }

            public static function getDefaultPrimaryKeySignOption(): string
            {
                return 'UNSIGNED';
            }
        }
    }

    if (!class_exists('CronTask')) {
        class CronTask
        {
            public const MODE_INTERNAL = 1;
            public const MODE_EXTERNAL = 2;
            public const STATE_WAITING = 0;

            public static function register(string $itemtype, string $name, int $frequency, array $options = []): void {}

            public static function unregister(string $itemtype): void {}

            public function addVolume(int $value): void {}

            public function log(string $message): void {}
        }
    }

    if (!class_exists('Html')) {
        class Html
        {
            public static function back(): void {}

            public static function redirect(string $url): void {}

            public static function header(string $title, string $url = '', string $menu = '', string $item = '', string $subitem = ''): void {}

            public static function footer(): void {}
        }
    }

    if (!class_exists('DBmysqlResult')) {
        class DBmysqlResult
        {
            public function fetch_assoc(): array|false
            {
                return false;
            }

            public function free(): void {}
        }
    }

    if (!class_exists('DBmysql')) {
        class DBmysql
        {
            public function tableExists(string $table): bool
            {
                return true;
            }

            public function doQuery(string $query): DBmysqlResult|false
            {
                return new DBmysqlResult();
            }

            public function delete(string $table, array $criteria): void {}

            public function insert(string $table, array $values): void {}

            public function request(array $criteria): array
            {
                return [];
            }
        }
    }

    if (!class_exists('PluginFieldsToolbox')) {
        class PluginFieldsToolbox
        {
            public static function decodeJSONItemtypes(string $json): array
            {
                return [];
            }
        }
    }

    if (!class_exists('PluginFieldsContainer')) {
        class PluginFieldsContainer extends CommonDBTM
        {
            public function find($condition = [], $order = [], $limit = null): array
            {
                return [];
            }
        }
    }

    if (!class_exists('PluginFieldsField')) {
        class PluginFieldsField extends CommonDBTM
        {
            public function find($condition = [], $order = [], $limit = null): array
            {
                return [];
            }
        }
    }

    if (!class_exists('Config')) {
        class Config
        {
            public static function getEmailSender(): array
            {
                return [];
            }
        }
    }

    if (!class_exists('TemplateRenderer')) {
        class TemplateRenderer {}
    }

    define('PLUGIN_ALERTSMANAGER_VERSION', '1.3.0');

    // Minimal GLPI version, inclusive
    define('PLUGIN_ALERTSMANAGER_MIN_GLPI', '11.0.0');
    // Maximum GLPI version, exclusive
    define('PLUGIN_ALERTSMANAGER_MAX_GLPI', '11.0.99');

    function plugin_init_alertsmanager(): void
    {
        /**
         * @var array<string, mixed> $PLUGIN_HOOKS
         * @var array<string, mixed> $CFG_GLPI
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

            CronTask::register(
                PluginAlertsmanagerAlert::class,
                'runalerts',
                DAY_TIMESTAMP,
                [
                    'allowmode'     => CronTask::MODE_INTERNAL | CronTask::MODE_EXTERNAL,
                    'state'         => CronTask::STATE_WAITING,
                    'hourmin'       => 0,
                    'hourmax'       => 24,
                    'logs_lifetime' => 30,
                ],
            );

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

    function plugin_version_alertsmanager(): array
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

}
