<?php

if (!defined('GLPI_ROOT')) {
    include __DIR__ . '/../../../inc/includes.php';
}

header('Content-Type: application/json; charset=UTF-8');

ob_start();

try {
    Session::checkLoginUser();
    if (!Session::haveRight('plugin_alertsmanager_alert', UPDATE) && !Session::haveRight('config', UPDATE)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error'   => __s('Access denied', 'alertsmanager'),
        ]);
        exit;
    }

    require_once __DIR__ . '/../inc/alert_target.class.php';
    require_once __DIR__ . '/../inc/alert.class.php';
    require_once __DIR__ . '/../inc/alert_mailer.class.php';

    $alertId = (int) ($_POST['alert_id'] ?? 0);
    if ($alertId <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => __s('Missing alert_id', 'alertsmanager'),
        ]);
        exit;
    }

    $alert = new PluginAlertsmanagerAlert();
    if (!$alert->getFromDB($alertId)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error'   => __s('Alert not found', 'alertsmanager'),
        ]);
        exit;
    }

    $result = $alert->sendMail([
        'event' => 'manual_test',
    ]);
    echo json_encode([
        'success'    => (bool) ($result['success'] ?? false),
        'sent'       => (int) ($result['sent'] ?? 0),
        'recipients' => $result['recipients'] ?? [],
        'errors'     => $result['errors'] ?? [],
        'error'      => implode('; ', $result['errors'] ?? []),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    if (ob_get_length()) {
        ob_clean();
    }

    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ]);
} finally {
    if (ob_get_length()) {
        ob_end_flush();
    }
}
