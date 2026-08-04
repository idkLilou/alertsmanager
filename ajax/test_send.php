<?php

if (!defined('GLPI_ROOT')) {
    include __DIR__ . '/../../../inc/includes.php';
}

require_once __DIR__ . '/../setup.php';

header('Content-Type: application/json; charset=UTF-8');

ob_start();

try {
    Session::checkLoginUser();
    if (!Session::haveRight('plugin_alertsmanager_alert', UPDATE) && !Session::haveRight('config', UPDATE)) {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode([
            'success' => false,
            'error'   => __s('Access denied', 'alertsmanager'),
        ]);
        return;
    }

    require_once __DIR__ . '/../inc/alert_target.class.php';
    require_once __DIR__ . '/../inc/alert.class.php';
    require_once __DIR__ . '/../inc/alert_mailer.class.php';

    $alertId = (int) ($_POST['alert_id'] ?? 0);
    if ($alertId <= 0) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode([
            'success' => false,
            'error'   => __s('Missing alert_id', 'alertsmanager'),
        ]);
        return;
    }

    $alert = new PluginAlertsmanagerAlert();
    if (!$alert->getFromDB($alertId)) {
        header('HTTP/1.1 404 Not Found');
        echo json_encode([
            'success' => false,
            'error'   => __s('Alert not found', 'alertsmanager'),
        ]);
        return;
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
    header('HTTP/1.1 500 Internal Server Error');
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
