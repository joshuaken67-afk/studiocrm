<?php
/**
 * Document Download Handler
 */

require_once 'config/app.php';
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/document-manager.php';

$auth = new Auth();
$auth->requireLogin();

$documentId = $_GET['id'] ?? '';
if (!$documentId) {
    die('Document ID required');
}

$db = Database::getInstance();
$docManager = new DocumentManager($db->getPdo());

// Get document
$document = $docManager->getDocument($documentId);
if (!$document) {
    die('Document not found');
}

// Check if file exists
if (!file_exists($document['file_path'])) {
    die('File not found on server');
}

// Log the download
$auth->logAction($_SESSION['user_id'], 'download', 'documents', $documentId, null, ['file' => $document['original_name']]);

// Set headers for download
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $document['original_name'] . '"');
header('Content-Length: ' . filesize($document['file_path']));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

// Output file
readfile($document['file_path']);
exit;
?>