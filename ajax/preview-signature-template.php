<?php
/**
 * Preview Signature Template AJAX Handler
 */

require_once '../config/app.php';
require_once '../includes/database.php';
require_once '../includes/auth.php';
require_once '../includes/signature-templates.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$templateId = $_GET['id'] ?? '';
if (!$templateId) {
    echo json_encode(['success' => false, 'error' => 'Template ID required']);
    exit;
}

$db = Database::getInstance();
$signatureTemplates = new SignatureTemplates($db->getPdo());

// Sample data for preview
$sampleData = [
    'signer_name' => 'John Doe',
    'signer_title' => 'Project Manager',
    'client_name' => 'Sample Client',
    'client_company' => 'Sample Company Inc.',
    'witness_name' => 'Jane Smith',
    'executive_name' => 'Michael Johnson',
    'executive_title' => 'Chief Executive Officer',
    'date' => date('F j, Y')
];

$result = $signatureTemplates->generateSignatureBlock($templateId, $sampleData);

echo json_encode($result);
?>