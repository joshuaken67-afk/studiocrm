<?php
/**
 * Document Management System
 */

class DocumentManager {
    private $pdo;
    private $uploadPath;
    private $allowedTypes;
    private $maxFileSize;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->uploadPath = __DIR__ . '/../uploads/documents/';
        $this->allowedTypes = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif'];
        $this->maxFileSize = 10 * 1024 * 1024; // 10MB
        
        // Create upload directory if it doesn't exist
        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0755, true);
        }
    }
    
    /**
     * Upload a document
     */
    public function uploadDocument($file, $metadata) {
        try {
            // Validate file
            $validation = $this->validateFile($file);
            if (!$validation['valid']) {
                throw new Exception($validation['error']);
            }
            
            // Generate unique filename
            $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $fileName = uniqid() . '_' . time() . '.' . $fileExtension;
            $filePath = $this->uploadPath . $fileName;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                throw new Exception('Failed to upload file');
            }
            
            // Save to database
            $sql = "INSERT INTO documents (
                        original_name, file_name, file_path, file_size, file_type,
                        document_type, category, description, tags, project_id, client_id,
                        uploaded_by, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $file['name'],
                $fileName,
                $filePath,
                $file['size'],
                $fileExtension,
                $metadata['document_type'] ?? 'general',
                $metadata['category'] ?? 'uncategorized',
                $metadata['description'] ?? '',
                $metadata['tags'] ?? '',
                $metadata['project_id'] ?? null,
                $metadata['client_id'] ?? null,
                $metadata['uploaded_by']
            ]);
            
            return [
                'success' => true,
                'document_id' => $this->pdo->lastInsertId(),
                'file_name' => $fileName
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get documents with filters
     */
    public function getDocuments($filters = []) {
        $sql = "SELECT d.*, 
                       c.name as client_name,
                       p.service as project_name,
                       u.name as uploaded_by_name
                FROM documents d
                LEFT JOIN clients c ON d.client_id = c.id
                LEFT JOIN projects p ON d.project_id = p.id
                LEFT JOIN users u ON d.uploaded_by = u.id
                WHERE 1=1";
        
        $params = [];
        
        if (!empty($filters['category'])) {
            $sql .= " AND d.category = ?";
            $params[] = $filters['category'];
        }
        
        if (!empty($filters['document_type'])) {
            $sql .= " AND d.document_type = ?";
            $params[] = $filters['document_type'];
        }
        
        if (!empty($filters['client_id'])) {
            $sql .= " AND d.client_id = ?";
            $params[] = $filters['client_id'];
        }
        
        if (!empty($filters['project_id'])) {
            $sql .= " AND d.project_id = ?";
            $params[] = $filters['project_id'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (d.original_name LIKE ? OR d.description LIKE ? OR d.tags LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $sql .= " ORDER BY d.created_at DESC";
        
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . intval($filters['limit']);
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get document by ID
     */
    public function getDocument($id) {
        $sql = "SELECT d.*, 
                       c.name as client_name,
                       p.service as project_name,
                       u.name as uploaded_by_name
                FROM documents d
                LEFT JOIN clients c ON d.client_id = c.id
                LEFT JOIN projects p ON d.project_id = p.id
                LEFT JOIN users u ON d.uploaded_by = u.id
                WHERE d.id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update document metadata
     */
    public function updateDocument($id, $metadata) {
        try {
            $sql = "UPDATE documents SET 
                        document_type = ?, category = ?, description = ?, 
                        tags = ?, project_id = ?, client_id = ?, updated_at = NOW()
                    WHERE id = ?";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $metadata['document_type'],
                $metadata['category'],
                $metadata['description'],
                $metadata['tags'],
                $metadata['project_id'] ?? null,
                $metadata['client_id'] ?? null,
                $id
            ]);
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Delete document
     */
    public function deleteDocument($id) {
        try {
            // Get document info
            $document = $this->getDocument($id);
            if (!$document) {
                throw new Exception('Document not found');
            }
            
            // Delete physical file
            if (file_exists($document['file_path'])) {
                unlink($document['file_path']);
            }
            
            // Delete from database
            $sql = "DELETE FROM documents WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$id]);
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get document categories
     */
    public function getCategories() {
        return [
            'contracts' => 'Contracts',
            'invoices' => 'Invoices',
            'receipts' => 'Receipts',
            'reports' => 'Reports',
            'presentations' => 'Presentations',
            'designs' => 'Designs',
            'photos' => 'Photos',
            'legal' => 'Legal Documents',
            'marketing' => 'Marketing Materials',
            'uncategorized' => 'Uncategorized'
        ];
    }
    
    /**
     * Get document types
     */
    public function getDocumentTypes() {
        return [
            'general' => 'General Document',
            'contract' => 'Contract',
            'invoice' => 'Invoice',
            'receipt' => 'Receipt',
            'report' => 'Report',
            'presentation' => 'Presentation',
            'design' => 'Design File',
            'photo' => 'Photo',
            'template' => 'Template',
            'signature_block' => 'Signature Block'
        ];
    }
    
    /**
     * Get storage statistics
     */
    public function getStorageStats() {
        $sql = "SELECT 
                    COUNT(*) as total_documents,
                    SUM(file_size) as total_size,
                    AVG(file_size) as avg_size,
                    category,
                    COUNT(*) as category_count
                FROM documents 
                GROUP BY category";
        
        $stmt = $this->pdo->query($sql);
        $categoryStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $sql = "SELECT 
                    COUNT(*) as total_documents,
                    SUM(file_size) as total_size,
                    AVG(file_size) as avg_size
                FROM documents";
        
        $stmt = $this->pdo->query($sql);
        $totalStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'total' => $totalStats,
            'by_category' => $categoryStats
        ];
    }
    
    /**
     * Validate uploaded file
     */
    private function validateFile($file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => 'File upload error'];
        }
        
        if ($file['size'] > $this->maxFileSize) {
            return ['valid' => false, 'error' => 'File too large. Maximum size is ' . ($this->maxFileSize / 1024 / 1024) . 'MB'];
        }
        
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExtension, $this->allowedTypes)) {
            return ['valid' => false, 'error' => 'File type not allowed'];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Generate download URL
     */
    public function getDownloadUrl($id) {
        return "download-document.php?id=" . $id;
    }
    
    /**
     * Search documents
     */
    public function searchDocuments($query, $filters = []) {
        $filters['search'] = $query;
        return $this->getDocuments($filters);
    }
}