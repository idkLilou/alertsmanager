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
    $GLOBALS['TL_LOG_DIR'] = __DIR__;
    include(__DIR__ . '/../../inc/includes.php');
}

// Placeholder for hidden alerts management
echo "<div class='hidden-alerts-container'>";
echo "<!-- Hidden alerts would be managed here -->";
echo "</div>";
