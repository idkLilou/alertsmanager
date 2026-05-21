<?php

if (!defined('GLPI_ROOT')) {
    include __DIR__ . '/../../../inc/includes.php';
}

header('Content-Type: application/json; charset=utf-8');

try {
    error_log('[alertsmanager][targets] Request received. Type: ' . $_GET['target_type'] ?? 'NONE');
    
    if (!Session::haveRight('plugin_alertsmanager_alert', READ) && !Session::haveRight('config', READ)) {
        error_log('[alertsmanager][targets] Access denied');
        http_response_code(403);
        echo json_encode([]);
        exit;
    }

    // support as-you-type: q, limit
    $type = $_GET['target_type'] ?? '';
    $q = trim((string) ($_GET['q'] ?? ''));
    $limit = intval($_GET['limit'] ?? 50);
    $limit = max(1, min($limit, 5000));
    $results = [];

    error_log('[alertsmanager][targets] Processing type: ' . $type . ', query: ' . $q . ', limit: ' . $limit);

    /** @var DBmysql $DB */
    global $DB;

    $appendRows = static function ($rows, array &$results, string $query, int $limit): void {
        foreach ($rows as $row) {
            if (count($results) >= $limit) {
                break;
            }

            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            // Only filter if $query is not empty
            if ($query !== '' && stripos($label, $query) === false) {
                continue;
            }

            $results[] = [
                'id'    => (int) ($row['id'] ?? 0),
                'label' => $label,
            ];
        }
    };

    switch ($type) {
        case 'User':
            $rows = $DB->request([
                'SELECT' => ['id', 'name AS label'],
                'FROM'   => 'glpi_users',
                'WHERE'  => ['is_deleted' => 0],
                'ORDER'  => ['name'],
            ]);
            $appendRows($rows, $results, $q, $limit);
            break;

        case 'Group':
            $rows = $DB->request([
                'SELECT' => ['id', 'name AS label'],
                'FROM'   => 'glpi_groups',
                'ORDER'  => ['name'],
            ]);
            $appendRows($rows, $results, $q, $limit);
            break;

        case 'Profile':
            $rows = $DB->request([
                'SELECT' => ['id', 'name AS label'],
                'FROM'   => 'glpi_profiles',
                'ORDER'  => ['name'],
            ]);
            $appendRows($rows, $results, $q, $limit);
            break;

        default:
            break;
    }

    error_log('[alertsmanager][targets] Returning ' . count($results) . ' results for type: ' . $type);
    echo json_encode($results);
    exit;
} catch (Throwable $e) {
    error_log('[alertsmanager][targets] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'targets_failed']);
    exit;
}

