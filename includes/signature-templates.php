<?php
/**
 * Signature Block Templates System
 */

class SignatureTemplates {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get all signature templates
     */
    public function getTemplates() {
        $sql = "SELECT * FROM signature_templates ORDER BY template_name";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get template by ID
     */
    public function getTemplate($id) {
        $sql = "SELECT * FROM signature_templates WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Create new signature template
     */
    public function createTemplate($data) {
        try {
            $sql = "INSERT INTO signature_templates (
                        template_name, template_type, html_content, css_styles,
                        signature_fields, default_values, created_by, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['template_name'],
                $data['template_type'],
                $data['html_content'],
                $data['css_styles'],
                json_encode($data['signature_fields']),
                json_encode($data['default_values']),
                $data['created_by']
            ]);
            
            return [
                'success' => true,
                'template_id' => $this->pdo->lastInsertId()
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Update signature template
     */
    public function updateTemplate($id, $data) {
        try {
            $sql = "UPDATE signature_templates SET 
                        template_name = ?, template_type = ?, html_content = ?, 
                        css_styles = ?, signature_fields = ?, default_values = ?,
                        updated_at = NOW()
                    WHERE id = ?";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['template_name'],
                $data['template_type'],
                $data['html_content'],
                $data['css_styles'],
                json_encode($data['signature_fields']),
                json_encode($data['default_values']),
                $id
            ]);
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Delete signature template
     */
    public function deleteTemplate($id) {
        try {
            $sql = "DELETE FROM signature_templates WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$id]);
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Generate signature block HTML
     */
    public function generateSignatureBlock($templateId, $data = []) {
        $template = $this->getTemplate($templateId);
        if (!$template) {
            return ['success' => false, 'error' => 'Template not found'];
        }
        
        $html = $template['html_content'];
        $css = $template['css_styles'];
        $fields = json_decode($template['signature_fields'], true);
        $defaults = json_decode($template['default_values'], true);
        
        // Merge provided data with defaults
        $mergedData = array_merge($defaults ?: [], $data);
        
        // Replace placeholders in HTML
        foreach ($mergedData as $key => $value) {
            $html = str_replace('{{' . $key . '}}', htmlspecialchars($value), $html);
        }
        
        // Add CSS styles
        $fullHtml = "<style>{$css}</style>\n{$html}";
        
        return [
            'success' => true,
            'html' => $fullHtml,
            'fields' => $fields
        ];
    }
    
    /**
     * Get predefined template types
     */
    public function getTemplateTypes() {
        return [
            'basic' => 'Basic Signature Block',
            'executive' => 'Executive Signature',
            'witness' => 'Witness Signature',
            'client_approval' => 'Client Approval',
            'financial_approval' => 'Financial Approval',
            'project_signoff' => 'Project Sign-off',
            'contract_signature' => 'Contract Signature',
            'invoice_approval' => 'Invoice Approval'
        ];
    }
    
    /**
     * Create default templates
     */
    public function createDefaultTemplates($userId) {
        $templates = [
            [
                'template_name' => 'Basic Signature Block',
                'template_type' => 'basic',
                'html_content' => '
                    <div class="signature-block">
                        <div class="signature-line"></div>
                        <div class="signature-info">
                            <div class="signer-name">{{signer_name}}</div>
                            <div class="signer-title">{{signer_title}}</div>
                            <div class="signature-date">Date: {{date}}</div>
                        </div>
                    </div>
                ',
                'css_styles' => '
                    .signature-block {
                        margin: 40px 0;
                        width: 300px;
                    }
                    .signature-line {
                        border-top: 1px solid #333;
                        margin-bottom: 5px;
                    }
                    .signature-info {
                        font-size: 12px;
                        line-height: 1.4;
                    }
                    .signer-name {
                        font-weight: bold;
                    }
                    .signer-title {
                        color: #666;
                    }
                    .signature-date {
                        margin-top: 5px;
                    }
                ',
                'signature_fields' => [
                    'signer_name' => 'Signer Name',
                    'signer_title' => 'Title/Position',
                    'date' => 'Date'
                ],
                'default_values' => [
                    'date' => date('F j, Y')
                ]
            ],
            [
                'template_name' => 'Executive Approval',
                'template_type' => 'executive',
                'html_content' => '
                    <div class="executive-signature">
                        <div class="approval-section">
                            <h4>Executive Approval</h4>
                            <div class="signature-row">
                                <div class="signature-field">
                                    <div class="signature-line"></div>
                                    <div class="field-label">{{executive_name}}</div>
                                    <div class="field-title">{{executive_title}}</div>
                                </div>
                                <div class="date-field">
                                    <div class="signature-line"></div>
                                    <div class="field-label">Date</div>
                                </div>
                            </div>
                        </div>
                    </div>
                ',
                'css_styles' => '
                    .executive-signature {
                        margin: 40px 0;
                        border: 1px solid #ddd;
                        padding: 20px;
                        background: #f9f9f9;
                    }
                    .executive-signature h4 {
                        margin: 0 0 20px 0;
                        color: #333;
                        border-bottom: 2px solid #007bff;
                        padding-bottom: 5px;
                    }
                    .signature-row {
                        display: flex;
                        justify-content: space-between;
                    }
                    .signature-field, .date-field {
                        width: 45%;
                    }
                    .signature-line {
                        border-top: 1px solid #333;
                        margin-bottom: 5px;
                        height: 40px;
                    }
                    .field-label {
                        font-weight: bold;
                        font-size: 12px;
                    }
                    .field-title {
                        font-size: 11px;
                        color: #666;
                    }
                ',
                'signature_fields' => [
                    'executive_name' => 'Executive Name',
                    'executive_title' => 'Executive Title'
                ],
                'default_values' => [
                    'executive_title' => 'Chief Executive Officer'
                ]
            ],
            [
                'template_name' => 'Client Approval',
                'template_type' => 'client_approval',
                'html_content' => '
                    <div class="client-approval">
                        <div class="approval-header">
                            <h4>Client Approval & Sign-off</h4>
                            <p>By signing below, the client acknowledges receipt and approval of the deliverables.</p>
                        </div>
                        <div class="signature-section">
                            <div class="client-signature">
                                <div class="signature-line"></div>
                                <div class="signature-details">
                                    <div class="client-name">{{client_name}}</div>
                                    <div class="client-company">{{client_company}}</div>
                                    <div class="signature-date">Date: {{date}}</div>
                                </div>
                            </div>
                            <div class="witness-signature">
                                <div class="signature-line"></div>
                                <div class="signature-details">
                                    <div class="witness-name">{{witness_name}}</div>
                                    <div class="witness-title">148 Studios Representative</div>
                                    <div class="signature-date">Date: {{date}}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                ',
                'css_styles' => '
                    .client-approval {
                        margin: 40px 0;
                        padding: 20px;
                        border: 2px solid #007bff;
                    }
                    .approval-header h4 {
                        color: #007bff;
                        margin: 0 0 10px 0;
                    }
                    .approval-header p {
                        font-size: 12px;
                        color: #666;
                        margin-bottom: 20px;
                    }
                    .signature-section {
                        display: flex;
                        justify-content: space-between;
                    }
                    .client-signature, .witness-signature {
                        width: 45%;
                    }
                    .signature-line {
                        border-top: 1px solid #333;
                        margin-bottom: 5px;
                        height: 50px;
                    }
                    .signature-details {
                        font-size: 12px;
                        line-height: 1.4;
                    }
                    .client-name, .witness-name {
                        font-weight: bold;
                    }
                    .client-company, .witness-title {
                        color: #666;
                        font-style: italic;
                    }
                ',
                'signature_fields' => [
                    'client_name' => 'Client Name',
                    'client_company' => 'Client Company',
                    'witness_name' => 'Witness Name',
                    'date' => 'Date'
                ],
                'default_values' => [
                    'date' => date('F j, Y')
                ]
            ]
        ];
        
        foreach ($templates as $template) {
            $template['created_by'] = $userId;
            $this->createTemplate($template);
        }
    }
    
    /**
     * Get template usage statistics
     */
    public function getUsageStats() {
        $sql = "SELECT 
                    st.template_name,
                    st.template_type,
                    COUNT(du.id) as usage_count,
                    MAX(du.created_at) as last_used
                FROM signature_templates st
                LEFT JOIN document_signatures du ON st.id = du.template_id
                GROUP BY st.id, st.template_name, st.template_type
                ORDER BY usage_count DESC";
        
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}