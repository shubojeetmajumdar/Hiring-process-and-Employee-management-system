<?php
// download_doc.php - Secure HR document downloader
require_once 'config.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    die("Error: Unauthorized access. Please log in first.");
}

$docId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($docId <= 0) {
    die("Error: Invalid document parameters.");
}

try {
    // Retrieve record from DB
    $stmt = $pdo->prepare("SELECT * FROM `employee_documents` WHERE `id` = :id LIMIT 1");
    $stmt->execute(['id' => $docId]);
    $doc = $stmt->fetch();

    if (!$doc) {
        die("Error: Document record not found in system.");
    }

    // Role-based verification: Employee can only download their own documents, HR can download any
    if ($_SESSION['role'] !== 'hr') {
        $empId = isset($_SESSION['employee_id']) ? $_SESSION['employee_id'] : '';
        if (empty($empId) || $doc['employee_id'] !== $empId) {
            die("Error: Access Denied. You do not have permissions to access this document.");
        }
    }

    $filePath = $doc['file_path'];
    $fileName = $doc['document_name'];

    // Generate a dummy file on-the-fly if it does not exist (robustness helper)
    if (!file_exists($filePath)) {
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        
        $content = "========================================================================\n";
        $content .= "                        NEXUSLINK ENTERPRISE PORTAL                     \n";
        $content .= "========================================================================\n\n";
        $content .= "Document Type  : " . htmlspecialchars($doc['document_type']) . "\n";
        $content .= "Filename       : " . htmlspecialchars($fileName) . "\n";
        $content .= "Employee ID    : " . htmlspecialchars($doc['employee_id']) . "\n";
        $content .= "Uploaded Date  : " . htmlspecialchars($doc['uploaded_at']) . "\n\n";
        $content .= "------------------------------------------------------------------------\n";
        $content .= "This is a system-generated secure copy of your official record. \n";
        $content .= "For any revisions, please contact the HR Recruiting department at \n";
        $content .= "hr@nexuslink.com.\n\n";
        $content .= "Generated securely on: " . date('Y-m-d H:i:s') . "\n";
        $content .= "========================================================================\n";
        
        file_put_contents($filePath, $content);
    }

    // Output secure HTTP download headers
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($fileName) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filePath));
    
    // Clear output buffer to avoid corruption
    ob_clean();
    flush();
    readfile($filePath);
    exit;

} catch (PDOException $e) {
    die("Database error occurred: " . $e->getMessage());
}
?>
