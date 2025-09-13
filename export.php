<?php
/**
 * Export Data to CSV
 */

require_once 'config/app.php';
require_once 'includes/database.php';
require_once 'includes/auth.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();

$type = $_GET['type'] ?? '';
$clientId = $_GET['client_id'] ?? '';

if (!$type) {
    die('Export type required');
}

// Build filename
$filename = $type . '_export_' . date('Y-m-d_H-i-s') . '.csv';

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

// Open output stream
$output = fopen('php://output', 'w');

$whereClause = '';
$params = [];
if ($clientId) {
    $whereClause = "WHERE client_id = ?";
    $params = [$clientId];
}

switch ($type) {
    case 'clients':
        // Export clients
        fputcsv($output, ['Name', 'Email', 'Phone', 'Service Interest', 'Source', 'Notes', 'Status', 'Created Date']);
        
        $clients = $db->fetchAll("SELECT * FROM clients ORDER BY created_at DESC");
        foreach ($clients as $client) {
            fputcsv($output, [
                $client['name'],
                $client['email'],
                $client['phone_number'],
                $client['service_interest'],
                $client['source'],
                $client['notes'],
                $client['status'],
                $client['created_at']
            ]);
        }
        break;
        
    case 'staff':
        if (!$auth->hasRole(['admin', 'manager'])) {
            die('Access denied');
        }
        
        fputcsv($output, ['Name', 'Email', 'Username', 'Role', 'Status', 'Created Date']);
        
        $staff = $db->fetchAll("SELECT * FROM users WHERE role IN ('staff', 'manager', 'admin') ORDER BY created_at DESC");
        foreach ($staff as $member) {
            fputcsv($output, [
                $member['name'],
                $member['email'],
                $member['username'],
                $member['role'],
                $member['status'],
                $member['created_at']
            ]);
        }
        break;
        
    case 'bookings':
        fputcsv($output, ['Client Name', 'Client Email', 'Service', 'Date', 'Duration', 'Status', 'Assigned Staff', 'Notes', 'Created By', 'Created Date']);
        
        $bookings = $db->fetchAll("
            SELECT b.*, c.name as client_name, c.email as client_email,
                   u.name as staff_name, creator.name as created_by_name
            FROM bookings b
            JOIN clients c ON b.client_id = c.id
            LEFT JOIN users u ON b.assigned_staff = u.id
            LEFT JOIN users creator ON b.created_by = creator.id
            $whereClause
            ORDER BY b.booking_date DESC
        ", $params);
        
        foreach ($bookings as $booking) {
            fputcsv($output, [
                $booking['client_name'],
                $booking['client_email'],
                $booking['service'],
                $booking['booking_date'],
                $booking['duration'] . ' minutes',
                $booking['status'],
                $booking['staff_name'] ?: 'Unassigned',
                $booking['notes'],
                $booking['created_by_name'],
                $booking['created_at']
            ]);
        }
        break;
        
    case 'projects':
        fputcsv($output, ['Client Name', 'Client Email', 'Service', 'Status', 'Assigned Staff', 'Deliverables', 'Notes', 'Created Date', 'Updated Date']);
        
        $projects = $db->fetchAll("
            SELECT p.*, c.name as client_name, c.email as client_email,
                   u.name as staff_name
            FROM projects p
            JOIN clients c ON p.client_id = c.id
            LEFT JOIN users u ON p.assigned_staff = u.id
            $whereClause
            ORDER BY p.updated_at DESC
        ", $params);
        
        foreach ($projects as $project) {
            fputcsv($output, [
                $project['client_name'],
                $project['client_email'],
                $project['service'],
                $project['status'],
                $project['staff_name'] ?: 'Unassigned',
                $project['deliverables'],
                $project['notes'],
                $project['created_at'],
                $project['updated_at']
            ]);
        }
        break;
        
    case 'payments':
        fputcsv($output, ['Client Name', 'Client Email', 'Amount', 'Payment Method', 'Payment Date', 'Description', 'Recorded By', 'Created Date']);
        
        $payments = $db->fetchAll("
            SELECT p.*, c.name as client_name, c.email as client_email,
                   u.name as recorded_by_name
            FROM payments p
            JOIN clients c ON p.client_id = c.id
            LEFT JOIN users u ON p.recorded_by = u.id
            $whereClause
            ORDER BY p.payment_date DESC
        ", $params);
        
        foreach ($payments as $payment) {
            fputcsv($output, [
                $payment['client_name'],
                $payment['client_email'],
                '$' . number_format($payment['amount'], 2),
                ucfirst(str_replace('_', ' ', $payment['payment_method'])),
                $payment['payment_date'],
                $payment['description'],
                $payment['recorded_by_name'],
                $payment['created_at']
            ]);
        }
        break;
        
    case 'invoices':
        fputcsv($output, ['Invoice Number', 'Client Name', 'Client Email', 'Amount', 'Due Date', 'Status', 'Created By', 'Created Date']);
        
        $invoices = $db->fetchAll("
            SELECT i.*, c.name as client_name, c.email as client_email,
                   u.name as created_by_name
            FROM invoices i
            JOIN clients c ON i.client_id = c.id
            LEFT JOIN users u ON i.created_by = u.id
            $whereClause
            ORDER BY i.created_at DESC
        ", $params);
        
        foreach ($invoices as $invoice) {
            fputcsv($output, [
                $invoice['invoice_number'],
                $invoice['client_name'],
                $invoice['client_email'],
                '$' . number_format($invoice['amount'], 2),
                $invoice['due_date'],
                ucfirst($invoice['status']),
                $invoice['created_by_name'],
                $invoice['created_at']
            ]);
        }
        break;
        
    case 'clarity_forms':
        fputcsv($output, ['Client Name', 'Client Email', 'Budget', 'Timeline', 'Preferred Contact', 'Notes', 'Created Date']);
        
        $forms = $db->fetchAll("
            SELECT cf.*, c.name as client_name, c.email as client_email
            FROM clarity_forms cf
            JOIN clients c ON cf.client_id = c.id
            $whereClause
            ORDER BY cf.created_at DESC
        ", $params);
        
        foreach ($forms as $form) {
            fputcsv($output, [
                $form['client_name'],
                $form['client_email'],
                $form['budget'] ? '$' . number_format($form['budget'], 2) : 'Not specified',
                $form['timeline'] ?: 'Not specified',
                ucfirst($form['preferred_contact']),
                $form['notes'],
                $form['created_at']
            ]);
        }
        break;
        
    default:
        die('Invalid export type');
}

// Log the export
$auth->logAction($_SESSION['user_id'], 'export', $type, 0, null, ['type' => $type, 'client_id' => $clientId]);

fclose($output);
exit;
?>
