<?php

if (!defined('GLPI_ROOT')) {
    include __DIR__ . '/../../../inc/includes.php';
}

require_once __DIR__ . '/../setup.php';

header('Content-Type: application/json; charset=UTF-8');

Session::checkLoginUser();
if (!Session::haveRight('plugin_alertsmanager_alert', READ) && !Session::haveRight('config', READ)) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode([
        'success' => false,
        'error'   => 'Access denied',
    ]);
    return;
}

require_once __DIR__ . '/../inc/alert.class.php';
require_once __DIR__ . '/../inc/alert_trigger_engine.class.php';

$alertId = (int) ($_REQUEST['alert_id'] ?? 0);
if ($alertId <= 0) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode([
        'success' => false,
        'error'   => 'Missing alert_id',
    ]);
    return;
}

$alert = new PluginAlertsmanagerAlert();
if (!$alert->getFromDB($alertId)) {
    header('HTTP/1.1 404 Not Found');
    echo json_encode([
        'success' => false,
        'error'   => 'Alert not found',
    ]);
    return;
}

$result = $alert->getTriggerEvaluation();

echo json_encode([
    'success' => (bool) ($result['success'] ?? false),
    'errors'  => $result['errors'] ?? [],
    'items'   => $result['items'] ?? [],
]);
