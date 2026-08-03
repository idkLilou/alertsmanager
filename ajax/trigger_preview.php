<?php

if (!defined('GLPI_ROOT')) {
    include __DIR__ . '/../../../inc/includes.php';
}

header('Content-Type: application/json; charset=UTF-8');

Session::checkLoginUser();
if (!Session::haveRight('plugin_alertsmanager_alert', READ) && !Session::haveRight('config', READ)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error'   => 'Access denied',
    ]);
    exit;
}

require_once __DIR__ . '/../inc/alert.class.php';
require_once __DIR__ . '/../inc/alert_trigger_engine.class.php';

$alertId = (int) ($_REQUEST['alert_id'] ?? 0);
if ($alertId <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'Missing alert_id',
    ]);
    exit;
}

$alert = new PluginAlertsmanagerAlert();
if (!$alert->getFromDB($alertId)) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error'   => 'Alert not found',
    ]);
    exit;
}

$result = $alert->getTriggerEvaluation();

echo json_encode([
    'success' => (bool) ($result['success'] ?? false),
    'errors'  => $result['errors'] ?? [],
    'items'   => $result['items'] ?? [],
]);
