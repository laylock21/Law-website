<?php
/**
 * Lightweight DOCX Generator
 * Creates DOCX files without PHPWord library using native PHP ZipArchive
 * Matches email template formatting for consultation notifications
 */

class DocxGenerator {
    
    private $tempDir;
    
    public function __construct() {
        $this->tempDir = sys_get_temp_dir() . '/docx_temp_' . uniqid();
    }
    
    /**
     * Generate DOCX file from consultation data
     */
    public function generateConsultationDocument($consultation_data, $template_type = 'confirmation') {
        try {
            // Create temporary directory structure
            $this->createDocxStructure();
            
            // Generate document content based on template type
            switch ($template_type) {
                case 'confirmation':
                    $content = $this->generateConfirmationContent($consultation_data);
                    break;
                case 'cancellation':
                    $content = $this->generateCancellationContent($consultation_data);
                    break;
                case 'completion':
                    $content = $this->generateCompletionContent($consultation_data);
                    break;
                default:
                    throw new Exception("Unknown template type: $template_type");
            }
            
            // Write document.xml
            file_put_contents($this->tempDir . '/word/document.xml', $content);
            
            // Create ZIP file
            $docx_path = $this->createZipFile($consultation_data, $template_type);
            
            // Cleanup
            $this->cleanup();
            
            return $docx_path;
            
        } catch (Exception $e) {
            $this->cleanup();
            throw $e;
        }
    }
    
    /**
     * Create DOCX directory structure and base files
     */
    private function createDocxStructure() {
        // Create directories
        mkdir($this->tempDir, 0777, true);
        mkdir($this->tempDir . '/word', 0777, true);
        mkdir($this->tempDir . '/_rels', 0777, true);
        mkdir($this->tempDir . '/word/_rels', 0777, true);
        mkdir($this->tempDir . '/docProps', 0777, true);
        
        // Content Types
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
    <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
    <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
</Types>';
        file_put_contents($this->tempDir . '/[Content_Types].xml', $contentTypes);
        
        // Main relationships
        $mainRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
    <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
</Relationships>';
        file_put_contents($this->tempDir . '/_rels/.rels', $mainRels);
        
        // Document relationships
        $docRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
</Relationships>';
        file_put_contents($this->tempDir . '/word/_rels/document.xml.rels', $docRels);
        
        // Core properties
        $coreProps = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
    <dc:title>Consultation Notification</dc:title>
    <dc:creator>MD Law Firm System</dc:creator>
    <dcterms:created xsi:type="dcterms:W3CDTF">' . date('c') . '</dcterms:created>
    <dcterms:modified xsi:type="dcterms:W3CDTF">' . date('c') . '</dcterms:modified>
</cp:coreProperties>';
        file_put_contents($this->tempDir . '/docProps/core.xml', $coreProps);
        
        // App properties
        $appProps = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">
    <Application>MD Law Firm System</Application>
    <DocSecurity>0</DocSecurity>
    <ScaleCrop>false</ScaleCrop>
    <SharedDoc>false</SharedDoc>
    <HyperlinksChanged>false</HyperlinksChanged>
    <AppVersion>1.0000</AppVersion>
</Properties>';
        file_put_contents($this->tempDir . '/docProps/app.xml', $appProps);
    }
    
    /**
     * Generate confirmation document content
     */
    private function generateConfirmationContent($data) {
        $client_name = htmlspecialchars($data['client_name'] ?? 'Client');
        $lawyer_name = htmlspecialchars($data['lawyer_name'] ?? 'Lawyer');
        $practice_area = htmlspecialchars($data['practice_area'] ?? 'Legal Consultation');
        $date = htmlspecialchars($data['formatted_date'] ?? date('l, F j, Y'));
        $time = htmlspecialchars($data['formatted_time'] ?? '2:00 PM');
        
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:body>
        <!-- Header -->
        <w:p>
            <w:pPr>
                <w:jc w:val="center"/>
                <w:spacing w:after="240"/>
            </w:pPr>
            <w:r>
                <w:rPr>
                    <w:b/>
                    <w:sz w:val="32"/>
                    <w:color w:val="1a2332"/>
                </w:rPr>
                <w:t>✅ APPOINTMENT CONFIRMED</w:t>
            </w:r>
        </w:p>
        
        <!-- Client greeting -->
        <w:p>
            <w:pPr>
                <w:spacing w:after="120"/>
            </w:pPr>
            <w:r>
                <w:rPr>
                    <w:sz w:val="24"/>
                </w:rPr>
                <w:t>Dear ' . $client_name . ',</w:t>
            </w:r>
        </w:p>
        
        <!-- Success message -->
        <w:p>
            <w:pPr>
                <w:spacing w:after="240"/>
                <w:pBdr>
                    <w:left w:val="single" w:sz="12" w:space="4" w:color="28a745"/>
                </w:pBdr>
                <w:shd w:val="clear" w:color="auto" w:fill="d4edda"/>
            </w:pPr>
            <w:r>
                <w:rPr>
                    <w:b/>
                    <w:sz w:val="24"/>
                </w:rPr>
                <w:t>✅ Great News! Your consultation appointment has been confirmed.</w:t>
            </w:r>
        </w:p>
        
        <!-- Appointment Details Header -->
        <w:p>
            <w:pPr>
                <w:spacing w:after="120"/>
            </w:pPr>
            <w:r>
                <w:rPr>
                    <w:b/>
                    <w:sz w:val="28"/>
                    <w:color w:val="1a2332"/>
                </w:rPr>
                <w:t>Appointment Details:</w:t>
            </w:r>
        </w:p>
        
        <!-- Details Box -->
        <w:p>
            <w:pPr>
                <w:spacing w:after="60"/>
                <w:shd w:val="clear" w:color="auto" w:fill="f8f9fa"/>
            </w:pPr>
            <w:r>
                <w:rPr>
                    <w:b/>
                </w:rPr>
                <w:t>Lawyer: </w:t>
            </w:r>
            <w:r>
                <w:t>Atty. ' . $lawyer_name . '</w:t>
            </w:r>
        </w:p>
        
        <w:p>
            <w:pPr>
                <w:spacing w:after="60"/>
                <w:shd w:val="clear" w:color="auto" w:fill="f8f9fa"/>
            </w:pPr>
            <w:r>
                <w:rPr>
                    <w:b/>
                </w:rPr>
                <w:t>Practice Area: </w:t>
            </w:r>
            <w:r>
                <w:t>' . $practice_area . '</w:t>
            </w:r>
        </w:p>
        
        <w:p>
            <w:pPr>
                <w:spacing w:after="60"/>
                <w:shd w:val="clear" w:color="auto" w:fill="f8f9fa"/>
            </w:pPr>
            <w:r>
                <w:rPr>
                    <w:b/>
                </w:rPr>
                <w:t>Date: </w:t>
            </w:r>
            <w:r>
                <w:rPr>
                    <w:color w:val="c5a253"/>
                    <w:b/>
                </w:rPr>
                <w:t>' . $date . '</w:t>
            </w:r>
        </w:p>
        
        <w:p>
            <w:pPr>
                <w:spacing w:after="240"/>
                <w:shd w:val="clear" w:color="auto" w:fill="f8f9fa"/>
            </w:pPr>
            <w:r>
                <w:rPr>
                    <w:b/>
                </w:rPr>
                <w:t>Time: </w:t>
            </w:r>
            <w:r>
                <w:rPr>
                    <w:color w:val="c5a253"/>
                    <w:b/>
                </w:rPr>
                <w:t>' . $time . '</w:t>
            </w:r>
        </w:p>
        
        <!-- Instructions -->
        <w:p>
            <w:pPr>
                <w:spacing w:after="120"/>
            </w:pPr>
            <w:r>
                <w:t>We\'re looking forward to meeting with you. Please arrive 10 minutes early to complete any necessary paperwork.</w:t>
            </w:r>
        </w:p>
        
        <!-- What to Bring -->
        <w:p>
            <w:pPr>
                <w:spacing w:after="120"/>
            </w:pPr>
            <w:r>
                <w:rPr>
                    <w:b/>
                </w:rPr>
                <w:t>What to Bring:</w:t>
            </w:r>
        </w:p>
        
        <w:p>
            <w:pPr>
                <w:numPr>
                    <w:ilvl w:val="0"/>
                    <w:numId w:val="1"/>
                </w:numPr>
            </w:pPr>
            <w:r>
                <w:t>Valid government-issued ID</w:t>
            </w:r>
        </w:p>
        
        <w:p>
            <w:pPr>
                <w:numPr>
                    <w:ilvl w:val="0"/>
                    <w:numId w:val="1"/>
                </w:numPr>
            </w:pPr>
            <w:r>
                <w:t>Any relevant documents related to your case</w:t>
            </w:r>
        </w:p>
        
        <w:p>
            <w:pPr>
                <w:numPr>
                    <w:ilvl w:val="0"/>
                    <w:numId w:val="1"/>
                </w:numPr>
                <w:spacing w:after="240"/>
            </w:pPr>
            <w:r>
                <w:t>List of questions or concerns you\'d like to discuss</w:t>
            </w:r>
        </w:p>
        
        <!-- Footer -->
        <w:p>
            <w:pPr>
                <w:spacing w:after="120"/>
            </w:pPr>
            <w:r>
                <w:t>Best regards,</w:t>
            </w:r>
        </w:p>
        
        <w:p>
            <w:r>
                <w:rPr>
                    <w:b/>
                </w:rPr>
                <w:t>MD Law Firm</w:t>
            </w:r>
        </w:p>
        
        <w:p>
            <w:r>
                <w:rPr>
                    <w:i/>
                </w:rPr>
                <w:t>Your Trusted Legal Partner</w:t>
            </w:r>
        </w:p>
        
    </w:body>
</w:document>';
    }
    
    /**
     * Generate cancellation document content
     */
    private function generateCancellationContent($data) {
        $client_name = htmlspecialchars($data['client_name'] ?? 'Client');
        $lawyer_name = htmlspecialchars($data['lawyer_name'] ?? 'Lawyer');
        $date = htmlspecialchars($data['formatted_date'] ?? date('l, F j, Y'));
        $time = htmlspecialchars($data['formatted_time'] ?? '2:00 PM');
        $reason = htmlspecialchars($data['reason'] ?? 'scheduling conflicts');
        
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:body>
        <!-- Header -->
        <w:p>
            <w:pPr>
                <w:jc w:val="center"/>
                <w:spacing w:after="240"/>
            </w:pPr>
            <w:r>
                <w:rPr>
                    <w:b/>
                    <w:sz w:val="32"/>
                    <w:color w:val="1a2332"/>
                </w:rPr>
                <w:t>APPOINTMENT CANCELLATION NOTICE</w:t>
            </w:r>
        </w:p>
        
        <!-- Client greeting -->
        <w:p>
            <w:pPr>
                <w:spacing w:after="120"/>
            </w:pPr>
            <w:r>
                <w:rPr>
                    <w:sz w:val="24"/>
                </w:rPr>
                <w:t>Dear ' . $client_name . ',</w:t>
            </w:r>
        </w:p>
        
        <!-- Alert message -->
        <w:p>
            <w:pPr>
                <w:spacing w:after="240"/>
                <w:pBdr>
                    <w:left w:val="single" w:sz="12" w:space="4" w:color="ffc107"/>
                </w:pBdr>
                <w:shd w:val="clear" w:color="auto" w:fill="fff3cd"/>
            </w:pPr>
            <w:r>
                <w:rPr>
                    <w:b/>
                    <w:sz w:val="24"/>
                </w:rPr>
                <w:t>⚠️ Important Notice: Your scheduled appointment has been cancelled due to ' . $reason . '.</w:t>
            </w:r>
        </w:p>
        
        <!-- Cancelled Appointment Details -->
        <w:p>
            <w:pPr>
                <w:spacing w:after="120"/>
            </w:pPr>
            <w:r>
                <w:rPr>
                    <w:b/>
                    <w:sz w:val="28"/>
                    <w:color w:val="1a2332"/>
                </w:rPr>
                <w:t>Cancelled Appointment Details:</w:t>
            </w:r>
        </w:p>
        
        <!-- Details -->
        <w:p>
            <w:pPr>
                <w:spacing w:after="60"/>
                <w:shd w:val="clear" w:color="auto" w:fill="f8f9fa"/>
            </w:pPr>
            <w:r>
                <w:rPr>
                    <w:b/>
                </w:rPr>
                <w:t>Lawyer: </w:t>
            </w:r>
            <w:r>
                <w:t>' . $lawyer_name . '</w:t>
            </w:r>
        </w:p>
        
        <w:p>
            <w:pPr>
                <w:spacing w:after="60"/>
                <w:shd w:val="clear" w:color="auto" w:fill="f8f9fa"/>
            </w:pPr>
            <w:r>
                <w:rPr>
                    <w:b/>
                </w:rPr>
                <w:t>Date: </w:t>
            </w:r>
            <w:r>
                <w:t>' . $date . '</w:t>
            </w:r>
        </w:p>
        
        <w:p>
            <w:pPr>
                <w:spacing w:after="240"/>
                <w:shd w:val="clear" w:color="auto" w:fill="f8f9fa"/>
            </w:pPr>
            <w:r>
                <w:rPr>
                    <w:b/>
                </w:rPr>
                <w:t>Time: </w:t>
            </w:r>
            <w:r>
                <w:t>' . $time . '</w:t>
            </w:r>
        </w:p>
        
        <!-- Apology and next steps -->
        <w:p>
            <w:pPr>
                <w:spacing w:after="120"/>
            </w:pPr>
            <w:r>
                <w:t>We sincerely apologize for any inconvenience this may cause. We understand this is unexpected and we\'re committed to serving you at the earliest available time.</w:t>
            </w:r>
        </w:p>
        
        <!-- Next Steps -->
        <w:p>
            <w:pPr>
                <w:spacing w:after="120"/>
            </w:pPr>
            <w:r>
                <w:rPr>
                    <w:b/>
                </w:rPr>
                <w:t>Next Steps:</w:t>
            </w:r>
        </w:p>
        
        <w:p>
            <w:pPr>
                <w:numPr>
                    <w:ilvl w:val="0"/>
                    <w:numId w:val="1"/>
                </w:numPr>
            </w:pPr>
            <w:r>
                <w:t>Please visit our website to reschedule your appointment</w:t>
            </w:r>
        </w:p>
        
        <w:p>
            <w:pPr>
                <w:numPr>
                    <w:ilvl w:val="0"/>
                    <w:numId w:val="1"/>
                </w:numPr>
            </w:pPr>
            <w:r>
                <w:t>Choose a new available date that works for you</w:t>
            </w:r>
        </w:p>
        
        <w:p>
            <w:pPr>
                <w:numPr>
                    <w:ilvl w:val="0"/>
                    <w:numId w:val="1"/>
                </w:numPr>
                <w:spacing w:after="240"/>
            </w:pPr>
            <w:r>
                <w:t>Contact us if you have any questions or concerns</w:t>
            </w:r>
        </w:p>
        
        <!-- Footer -->
        <w:p>
            <w:pPr>
                <w:spacing w:after="120"/>
            </w:pPr>
            <w:r>
                <w:t>Best regards,</w:t>
            </w:r>
        </w:p>
        
        <w:p>
            <w:r>
                <w:rPr>
                    <w:b/>
                </w:rPr>
                <w:t>MD Law Firm</w:t>
            </w:r>
        </w:p>
        
    </w:body>
</w:document>';
    }
    
    /**
     * Generate completion document content
     */
    private function generateCompletionContent($data) {
        $client_name = htmlspecialchars($data['client_name'] ?? 'Client');
        $lawyer_name = htmlspecialchars($data['lawyer_name'] ?? 'Lawyer');
        $date = htmlspecialchars($data['formatted_date'] ?? date('l, F j, Y'));
        $time = htmlspecialchars($data['formatted_time'] ?? '2:00 PM');
        $practice_area = htmlspecialchars($data['practice_area'] ?? 'Legal Consultation');
        
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:body>
        
        <!-- Header -->
        <w:p>
            <w:pPr>
                <w:jc w:val="center"/>
                <w:spacing w:after="240"/>
            </w:pPr>
            <w:r>
                <w:rPr>
                    <w:b/>
                    <w:sz w:val="32"/>
                    <w:color w:val="1a2332"/>
                </w:rPr>
                <w:t>MD Law Firm</w:t>
            </w:r>
        </w:p>
        
        <w:p>
            <w:pPr>
                <w:jc w:val="center"/>
                <w:spacing w:after="480"/>
            </w:pPr>
            <w:r>
                <w:rPr>
                    <w:sz w:val="24"/>
                    <w:color w:val="666666"/>
                </w:rPr>
                <w:t>Your Trusted Legal Partner</w:t>
            </w:r>
        </w:p>
        
        <!-- Title -->
        <w:p>
            <w:pPr>
                <w:jc w:val="center"/>
                <w:spacing w:after="360"/>
            </w:pPr>
            <w:r>
                <w:rPr>
                    <w:b/>
                    <w:sz w:val="36"/>
                    <w:color w:val="28a745"/>
                </w:rPr>
                <w:t>✅ CONSULTATION COMPLETED</w:t>
            </w:r>
        </w:p>
        
        <!-- Success Message -->
        <w:p>
            <w:pPr>
                <w:spacing w:after="240"/>
                <w:pBdr>
                    <w:left w:val="single" w:sz="12" w:space="4" w:color="28a745"/>
                </w:pBdr>
                <w:shd w:val="clear" w:color="auto" w:fill="d4edda"/>
            </w:pPr>
            <w:r>
                <w:rPr>
                    <w:b/>
                    <w:sz w:val="24"/>
                </w:rPr>
                <w:t>🎉 Thank you for choosing MD Law Firm! Your consultation has been successfully completed.</w:t>
            </w:r>
        </w:p>
        
        <!-- Completed Consultation Details -->
        <w:p>
            <w:pPr>
                <w:spacing w:after="120"/>
            </w:pPr>
            <w:r>
                <w:rPr>
                    <w:b/>
                    <w:sz w:val="28"/>
                    <w:color w:val="1a2332"/>
                </w:rPr>
                <w:t>Consultation Summary:</w:t>
            </w:r>
        </w:p>
        
        <w:p>
            <w:pPr>
                <w:spacing w:after="120"/>
            </w:pPr>
            <w:r>
                <w:rPr>
                    <w:b/>
                </w:rPr>
                <w:t>Client: </w:t>
            </w:r>
            <w:r>
                <w:t>' . $client_name . '</w:t>
            </w:r>
        </w:p>
        
        <w:p>
            <w:pPr>
                <w:spacing w:after="120"/>
            </w:pPr>
            <w:r>
                <w:rPr>
                    <w:b/>
                </w:rPr>
                <w:t>Lawyer: </w:t>
            </w:r>
            <w:r>
                <w:t>' . $lawyer_name . '</w:t>
            </w:r>
        </w:p>
        
        <w:p>
            <w:pPr>
                <w:spacing w:after="120"/>
            </w:pPr>
            <w:r>
                <w:rPr>
                    <w:b/>
                </w:rPr>
                <w:t>Practice Area: </w:t>
            </w:r>
            <w:r>
                <w:t>' . $practice_area . '</w:t>
            </w:r>
        </w:p>
        
        <w:p>
            <w:pPr>
                <w:spacing w:after="120"/>
            </w:pPr>
            <w:r>
                <w:rPr>
                    <w:b/>
                </w:rPr>
                <w:t>Date: </w:t>
            </w:r>
            <w:r>
                <w:t>' . $date . '</w:t>
            </w:r>
        </w:p>
        
        <w:p>
            <w:pPr>
                <w:spacing w:after="360"/>
            </w:pPr>
            <w:r>
                <w:rPr>
                    <w:b/>
                </w:rPr>
                <w:t>Time: </w:t>
            </w:r>
            <w:r>
                <w:t>' . $time . '</w:t>
            </w:r>
        </w:p>
        
        <!-- Next Steps Section -->
        <w:p>
            <w:pPr>
                <w:spacing w:after="120"/>
            </w:pPr>
            <w:r>
                <w:rPr>
                    <w:b/>
                    <w:sz w:val="24"/>
                    <w:color w:val="1a2332"/>
                </w:rPr>
                <w:t>📋 Next Steps:</w:t>
            </w:r>
        </w:p>
        
        <w:p>
            <w:pPr>
                <w:numPr>
                    <w:ilvl w:val="0"/>
                    <w:numId w:val="1"/>
                </w:numPr>
                <w:spacing w:after="120"/>
            </w:pPr>
            <w:r>
                <w:t>Review any documents or advice provided during the consultation</w:t>
            </w:r>
        </w:p>
        
        <w:p>
            <w:pPr>
                <w:numPr>
                    <w:ilvl w:val="0"/>
                    <w:numId w:val="1"/>
                </w:numPr>
                <w:spacing w:after="120"/>
            </w:pPr>
            <w:r>
                <w:t>Keep this document for your records</w:t>
            </w:r>
        </w:p>
        
        <w:p>
            <w:pPr>
                <w:numPr>
                    <w:ilvl w:val="0"/>
                    <w:numId w:val="1"/>
                </w:numPr>
                <w:spacing w:after="120"/>
            </w:pPr>
            <w:r>
                <w:t>Contact us if you have any follow-up questions</w:t>
            </w:r>
        </w:p>
        
        <w:p>
            <w:pPr>
                <w:numPr>
                    <w:ilvl w:val="0"/>
                    <w:numId w:val="1"/>
                </w:numPr>
                <w:spacing w:after="240"/>
            </w:pPr>
            <w:r>
                <w:t>Consider scheduling additional consultations if needed</w:t>
            </w:r>
        </w:p>
        
        <!-- Follow-up Services -->
        <w:p>
            <w:pPr>
                <w:spacing w:after="120"/>
            </w:pPr>
            <w:r>
                <w:rPr>
                    <w:b/>
                    <w:sz w:val="24"/>
                    <w:color w:val="1a2332"/>
                </w:rPr>
                <w:t>🔄 Available Follow-Up Services:</w:t>
            </w:r>
        </w:p>
        
        <w:p>
            <w:pPr>
                <w:numPr>
                    <w:ilvl w:val="0"/>
                    <w:numId w:val="2"/>
                </w:numPr>
                <w:spacing w:after="120"/>
            </w:pPr>
            <w:r>
                <w:t>Document preparation and review</w:t>
            </w:r>
        </w:p>
        
        <w:p>
            <w:pPr>
                <w:numPr>
                    <w:ilvl w:val="0"/>
                    <w:numId w:val="2"/>
                </w:numPr>
                <w:spacing w:after="120"/>
            </w:pPr>
            <w:r>
                <w:t>Ongoing legal representation</w:t>
            </w:r>
        </w:p>
        
        <w:p>
            <w:pPr>
                <w:numPr>
                    <w:ilvl w:val="0"/>
                    <w:numId w:val="2"/>
                </w:numPr>
                <w:spacing w:after="120"/>
            </w:pPr>
            <w:r>
                <w:t>Additional consultations in related practice areas</w:t>
            </w:r>
        </w:p>
        
        <w:p>
            <w:pPr>
                <w:numPr>
                    <w:ilvl w:val="0"/>
                    <w:numId w:val="2"/>
                </w:numPr>
                <w:spacing w:after="360"/>
            </w:pPr>
            <w:r>
                <w:t>Legal document templates and resources</w:t>
            </w:r>
        </w:p>
        
        <!-- Feedback Section -->
        <w:p>
            <w:pPr>
                <w:spacing w:after="240"/>
                <w:pBdr>
                    <w:left w:val="single" w:sz="12" w:space="4" w:color="007bff"/>
                </w:pBdr>
                <w:shd w:val="clear" w:color="auto" w:fill="e7f3ff"/>
            </w:pPr>
            <w:r>
                <w:rPr>
                    <w:b/>
                    <w:sz w:val="22"/>
                </w:rPr>
                <w:t>📝 We Value Your Feedback! Your experience matters to us. Please share your thoughts about today\'s consultation.</w:t>
            </w:r>
        </w:p>
        
        <!-- Footer -->
        <w:p>
            <w:pPr>
                <w:spacing w:after="120"/>
            </w:pPr>
            <w:r>
                <w:t>Thank you for trusting MD Law Firm with your legal needs.</w:t>
            </w:r>
        </w:p>
        
        <w:p>
            <w:pPr>
                <w:spacing w:after="120"/>
            </w:pPr>
            <w:r>
                <w:t>Best regards,</w:t>
            </w:r>
        </w:p>
        
        <w:p>
            <w:r>
                <w:rPr>
                    <w:b/>
                </w:rPr>
                <w:t>MD Law Firm</w:t>
            </w:r>
        </w:p>
        
        <w:p>
            <w:r>
                <w:rPr>
                    <w:i/>
                </w:rPr>
                <w:t>Your Trusted Legal Partner</w:t>
            </w:r>
        </w:p>
        
    </w:body>
</w:document>';
    }
    
    /**
     * Create ZIP file (DOCX format)
     */
    private function createZipFile($consultation_data, $template_type) {
        $filename = 'consultation_' . $template_type . '_' . date('Y-m-d_H-i-s') . '.docx';
        $output_path = __DIR__ . '/../uploads/generated_docs/' . $filename;
        
        // Ensure output directory exists
        $output_dir = dirname($output_path);
        if (!is_dir($output_dir)) {
            mkdir($output_dir, 0777, true);
        }
        
        // Guard: Ensure ZipArchive extension is available
        if (!class_exists('ZipArchive')) {
            throw new Exception('ZipArchive class not found. Enable the PHP Zip extension (php_zip) in php.ini and restart Apache.');
        }
        
        $zip = new ZipArchive();
        if ($zip->open($output_path, ZipArchive::CREATE) !== TRUE) {
            throw new Exception("Cannot create DOCX file: $output_path");
        }
        
        // Add all files to ZIP
        $this->addDirectoryToZip($zip, $this->tempDir, '');
        
        $zip->close();
        
        return $output_path;
    }
    
    /**
     * Recursively add directory contents to ZIP
     */
    private function addDirectoryToZip($zip, $dir, $zipPath) {
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $filePath = $dir . '/' . $file;
            $zipFilePath = $zipPath ? $zipPath . '/' . $file : $file;
            
            if (is_dir($filePath)) {
                $zip->addEmptyDir($zipFilePath);
                $this->addDirectoryToZip($zip, $filePath, $zipFilePath);
            } else {
                $zip->addFile($filePath, $zipFilePath);
            }
        }
    }
    
    /**
     * Clean up temporary files
     */
    private function cleanup() {
        if (is_dir($this->tempDir)) {
            $this->deleteDirectory($this->tempDir);
        }
    }
    
    /**
     * Recursively delete directory
     */
    private function deleteDirectory($dir) {
        if (!is_dir($dir)) return;
        
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $filePath = $dir . '/' . $file;
            if (is_dir($filePath)) {
                $this->deleteDirectory($filePath);
            } else {
                unlink($filePath);
            }
        }
        rmdir($dir);
    }
}
?>
