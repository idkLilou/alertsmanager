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

/**
 * Return list of date-type fields available in the system
 * These are the fields that can trigger alerts based on date comparison
 */
$fields = [
    // Ticket fields
    ['id' => 'Ticket.due_date', 'label' => __('Ticket Due Date')],
    ['id' => 'Ticket.date_creation', 'label' => __('Ticket Creation Date')],
    ['id' => 'Ticket.date_mod', 'label' => __('Ticket Last Update')],
    ['id' => 'Ticket.solve_delay_stat', 'label' => __('Ticket Solve Time')],
    
    // Contract fields
    ['id' => 'Contract.begin_date', 'label' => __('Contract Begin Date')],
    ['id' => 'Contract.end_date', 'label' => __('Contract End Date')],
    ['id' => 'Contract.date_creation', 'label' => __('Contract Creation Date')],
    
    // SoftwareLicense fields
    ['id' => 'SoftwareLicense.expiration_date', 'label' => __('License Expiration Date')],
    ['id' => 'SoftwareLicense.buy_date', 'label' => __('License Buy Date')],
    
    // Asset fields (generic)
    ['id' => 'Computer.date_creation', 'label' => __('Computer Creation Date')],
    ['id' => 'Printer.date_creation', 'label' => __('Printer Creation Date')],
    ['id' => 'Monitor.date_creation', 'label' => __('Monitor Creation Date')],
    ['id' => 'NetworkEquipment.date_creation', 'label' => __('Network Equipment Creation Date')],
    ['id' => 'Peripheral.date_creation', 'label' => __('Peripheral Creation Date')],
    
    // Project fields
    ['id' => 'Project.begin_date', 'label' => __('Project Begin Date')],
    ['id' => 'Project.end_date', 'label' => __('Project End Date')],
];

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
