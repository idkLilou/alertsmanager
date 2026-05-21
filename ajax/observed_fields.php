<?php

/**
 * -------------------------------------------------------------------------
 * Alerts Manager plugin for GLPI
 * -------------------------------------------------------------------------
 * AJAX endpoint to retrieve available date fields for the observed_field dropdown
 */

if (!defined('GLPI_ROOT')) {
    include __DIR__ . '/../../../inc/includes.php';
}

if (!Session::haveRight('plugin_alertsmanager_alert', READ) && !Session::haveRight('config', READ)) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(403);
    echo json_encode([]);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

if (!class_exists('PluginAlertsmanagerAlert')) {
    include_once __DIR__ . '/../inc/alert.class.php';
}

$fields = class_exists('PluginAlertsmanagerAlert')
    ? PluginAlertsmanagerAlert::getAvailableObservedFields()
    : [];

// Allow filtering by search term
$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $q_lower = strtolower($q);
    $fields = array_filter($fields, function($f) use ($q_lower) {
        return strpos(strtolower($f['label']), $q_lower) !== false 
            || strpos(strtolower($f['id']), $q_lower) !== false;
    });
}

echo json_encode(array_values($fields));
exit;
