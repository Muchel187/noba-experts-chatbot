<?php
/**
 * Document Upload Handler - CV & Job Description
 * Extracts text from PDF/DOCX for AI analysis
 *
 * Features:
 * - File validation (type, size)
 * - Text extraction (PDF: Smalot\PdfParser, DOCX: simple XML)
 * - Session-based storage (temporary)
 * - DSGVO-compliant (auto-deletion)
 */

header('Content-Type: application/json');

// CORS Headers
$allowed_origins = [
    'https://www.noba-experts.de',
    'https://chatbot.noba-experts.de',
    'http://localhost',
    'http://localhost:8080',
    'http://127.0.0.1'
];

$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
if (in_array($origin, $allowed_origins) || strpos($origin, 'localhost') !== false) {
    header('Access-Control-Allow-Origin: ' . $origin);
}

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Configuration
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10 MB
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('ALLOWED_TYPES', ['pdf', 'doc', 'docx']);

// Ensure upload directory exists
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

/**
 * Extract text from PDF using pdftotext (robust & professional)
 */
function extractTextFromPDF($filepath) {
    // Use pdftotext command-line tool (much better than regex)
    $output = [];
    $returnCode = 0;

    // Execute pdftotext: -enc UTF-8, output to stdout (-)
    exec('pdftotext -enc UTF-8 ' . escapeshellarg($filepath) . ' - 2>&1', $output, $returnCode);

    if ($returnCode !== 0) {
        error_log("⚠️ pdftotext failed with code $returnCode: " . implode("\n", $output));
        return ''; // Return empty string on failure
    }

    $text = implode("\n", $output);

    // Clean up excessive whitespace
    $text = preg_replace('/\s+/', ' ', $text);

    return trim($text);
}

/**
 * Extract text from DOCX (ZIP-based format)
 */
function extractTextFromDOCX($filepath) {
    $zip = new ZipArchive();
    $text = '';

    if ($zip->open($filepath) === true) {
        // DOCX structure: word/document.xml contains the text
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml) {
            // Remove XML tags
            $text = strip_tags($xml);
            // Clean up whitespace
            $text = preg_replace('/\s+/', ' ', $text);
        }
    }

    return trim($text);
}

/**
 * Extract text from DOC (old binary format)
 * Note: Limited support, modern docs should be DOCX
 */
function extractTextFromDOC($filepath) {
    $content = file_get_contents($filepath);

    // DOC format is complex, this is a very basic extraction
    // Remove non-printable characters
    $content = preg_replace('/[\x00-\x1F\x7F-\xFF]/', ' ', $content);
    $content = preg_replace('/\s+/', ' ', $content);

    return trim($content);
}

/**
 * Extract contact data from CV text (Email, Phone, Name)
 */
function extractContactData($text) {
    $contactData = [
        'email' => null,
        'phone' => null,
        'name' => null
    ];

    // E-Mail extrahieren (erste gefundene E-Mail)
    if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $text, $matches)) {
        $contactData['email'] = $matches[0];
    }

    // Telefonnummer extrahieren (verschiedene Formate)
    // Variante 1: Format wie "+49(0)2102/4989-0" oder "T +49(0)2102/4989-0"
    if (preg_match('/T?\s?\+?49\s?\(0\)?\d{3,5}[\s\/\-]?\d{3,}[\s\/\-]?\d+/', $text, $matches)) {
        $phone = preg_replace('/^T\s?/', '', $matches[0]); // Entferne führendes "T"
        $contactData['phone'] = trim($phone);
    }
    // Variante 2: Format wie "Tel. 02102 -49 89 - 762" (mit Spaces und Minus gemischt)
    elseif (preg_match('/(?:Tel\.?|Telefon|Phone)[\s:]*(\d{4,5})\s?[\s\-]+(\d{2})\s?[\s\-]+(\d{2})\s?[\s\-]+(\d{3})/', $text, $matches)) {
        // Kombiniere die Gruppen
        $contactData['phone'] = $matches[1] . $matches[2] . $matches[3] . $matches[4];
    }
    // Variante 3: Standard-Format "+49 211 975 324 74" oder "0211 975 324 74"
    elseif (preg_match('/(?:\+49[\s\(\)]?|0)[\s]?(\d{2,4})[\s\-\/]?(\d{3,})[\s\-\/]?(\d{3,})/', $text, $matches)) {
        $contactData['phone'] = trim($matches[0]);
    }
    // Variante 4: Einfacher Fallback - 10-15 Ziffern am Stück
    elseif (preg_match('/\b\d{10,15}\b/', $text, $matches)) {
        $contactData['phone'] = $matches[0];
    }

    // Name extrahieren - suche nach "Name:" gefolgt von 2 Wörtern mit Großbuchstaben
    if (preg_match('/Name:\s*([A-ZÄÖÜ][a-zäöüß]+\s+[A-ZÄÖÜ][a-zäöüß]+)/i', $text, $matches)) {
        $contactData['name'] = $matches[1];
    }
    // Alternativ: Erster Satz mit zwei großgeschriebenen Wörtern
    elseif (preg_match('/^([A-ZÄÖÜ][a-zäöüß]+\s+[A-ZÄÖÜ][a-zäöüß]+)/', $text, $matches)) {
        $contactData['name'] = $matches[1];
    }

    return $contactData;
}

/**
 * Validate uploaded file
 */
function validateFile($file) {
    // Check if file was uploaded
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['valid' => false, 'error' => 'Keine Datei hochgeladen'];
    }

    // Check file size
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['valid' => false, 'error' => 'Datei zu groß (max. 10 MB)'];
    }

    if ($file['size'] === 0) {
        return ['valid' => false, 'error' => 'Datei ist leer'];
    }

    // Check file extension
    $filename = $file['name'];
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if (!in_array($extension, ALLOWED_TYPES)) {
        return ['valid' => false, 'error' => 'Ungültiger Dateityp (nur PDF, DOC, DOCX erlaubt)'];
    }

    // Check MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowedMimeTypes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];

    if (!in_array($mimeType, $allowedMimeTypes)) {
        return ['valid' => false, 'error' => 'Ungültiger Dateityp'];
    }

    return ['valid' => true, 'extension' => $extension, 'filename' => $filename];
}

// ========================================
// Main Logic
// ========================================

try {
    // Check if file was uploaded
    if (!isset($_FILES['document'])) {
        throw new Exception('Keine Datei erhalten');
    }

    $file = $_FILES['document'];

    // Validate file
    $validation = validateFile($file);
    if (!$validation['valid']) {
        throw new Exception($validation['error']);
    }

    $extension = $validation['extension'];
    $originalFilename = $validation['filename'];

    // Generate unique filename
    $sessionId = $_POST['session_id'] ?? uniqid('doc_', true);
    $documentType = $_POST['document_type'] ?? 'unknown'; // 'cv' or 'job_description'
    $uniqueFilename = $sessionId . '_' . time() . '.' . $extension;
    $filepath = UPLOAD_DIR . $uniqueFilename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Fehler beim Speichern der Datei');
    }

    // Extract text based on file type
    $extractedText = '';

    switch ($extension) {
        case 'pdf':
            $extractedText = extractTextFromPDF($filepath);
            error_log("📄 PDF Text extracted: " . strlen($extractedText) . " chars | Preview: " . substr($extractedText, 0, 200));
            break;
        case 'docx':
            $extractedText = extractTextFromDOCX($filepath);
            error_log("📄 DOCX Text extracted: " . strlen($extractedText) . " chars");
            break;
        case 'doc':
            $extractedText = extractTextFromDOC($filepath);
            error_log("📄 DOC Text extracted: " . strlen($extractedText) . " chars");
            break;
    }

    // Validate extracted text
    if (strlen($extractedText) < 50) {
        // Delete file if extraction failed
        error_log("❌ Text extraction failed: Only " . strlen($extractedText) . " chars extracted from " . $originalFilename);
        unlink($filepath);
        throw new Exception('Konnte keinen Text aus der Datei extrahieren. Bitte versuchen Sie eine andere Datei.');
    }

    // Limit text length (max 5000 characters for AI)
    if (strlen($extractedText) > 5000) {
        $extractedText = substr($extractedText, 0, 5000) . '...';
    }

    // Extract contact data (Email, Phone, Name) aus CV
    $contactData = extractContactData($extractedText);
    error_log("📇 Kontaktdaten extrahiert: Name=" . ($contactData['name'] ?? 'N/A') .
              " | E-Mail=" . ($contactData['email'] ?? 'N/A') .
              " | Telefon=" . ($contactData['phone'] ?? 'N/A'));

    // ===== AUTOMATISCHES MATCHING =====
    $matchingResults = null;
    
    // Lade Matching-Funktionen aus chatbot-api.php
    require_once __DIR__ . '/chatbot-api.php';
    
    if ($documentType === 'cv') {
        // CV hochgeladen → Finde passende Jobs
        $vacancies = fetchCurrentVacancies();
        $matchedJobs = findMatchingVacancies($extractedText, $vacancies);
        
        if (!empty($matchedJobs)) {
            $matchingResults = [
                'type' => 'jobs',
                'count' => count($matchedJobs),
                'matches' => array_map(function($job) {
                    return [
                        'id' => $job['id'],
                        'title' => $job['title'],
                        'location' => $job['location'] ?? 'Remote',
                        'experience_level' => $job['experience_level'] ?? 'Mid',
                        'skills' => array_slice($job['required_skills'] ?? [], 0, 5),
                        'description_preview' => mb_substr($job['anonymized_description'] ?? '', 0, 150) . '...'
                    ];
                }, array_slice($matchedJobs, 0, 3))
            ];
            error_log("✅ CV-Matching: " . count($matchedJobs) . " passende Jobs gefunden");
        }
    } elseif ($documentType === 'job_description') {
        // Stellenbeschreibung hochgeladen → Finde passende Kandidaten
        $candidates = fetchCandidateProfiles();
        $matchedCandidates = findMatchingCandidates($extractedText, $candidates);
        
        if (!empty($matchedCandidates)) {
            $matchingResults = [
                'type' => 'candidates',
                'count' => count($matchedCandidates),
                'matches' => array_map(function($candidate, $idx) {
                    return [
                        'id' => $candidate['id'],
                        'label' => 'Kandidat #' . ($idx + 1),
                        'seniority_level' => $candidate['seniority_level'] ?? 'Mid',
                        'experience_years' => $candidate['experience_years'] ?? 0,
                        'skills' => array_slice($candidate['skills'] ?? [], 0, 8),
                        'location' => $candidate['location'] ?? 'Remote',
                        'availability' => $candidate['availability'] ?? 'Vollzeit'
                    ];
                }, array_slice($matchedCandidates, 0, 3), array_keys(array_slice($matchedCandidates, 0, 3)))
            ];
            error_log("✅ Stellen-Matching: " . count($matchedCandidates) . " passende Kandidaten gefunden");
        }
    }

    // Log upload
    error_log(sprintf(
        'Document uploaded: Type=%s, Size=%d, Filename=%s, Session=%s, Matches=%s',
        $documentType,
        $file['size'],
        $originalFilename,
        $sessionId,
        $matchingResults ? $matchingResults['count'] : 0
    ));

    // Schedule file deletion (after 2 hours)
    $deleteAt = time() + 7200;

    // Response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Dokument erfolgreich hochgeladen und analysiert',
        'data' => [
            'extracted_text' => $extractedText,
            'filename' => $originalFilename,
            'document_type' => $documentType,
            'session_id' => $sessionId,
            'file_size' => $file['size'],
            'word_count' => str_word_count($extractedText),
            'delete_at' => $deleteAt,
            'server_filename' => $uniqueFilename,  // Unique filename auf Server
            'server_path' => $filepath,             // Absoluter Pfad zur Datei
            'contact_data' => $contactData,         // Extrahierte Kontaktdaten (Name, E-Mail, Telefon)
            'matching_results' => $matchingResults  // Automatisches Matching
        ]
    ]);

} catch (Exception $e) {
    error_log('Document upload error: ' . $e->getMessage());

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// Cleanup old files (older than 2 hours)
$files = glob(UPLOAD_DIR . '*');
foreach ($files as $file) {
    if (is_file($file) && (time() - filemtime($file)) > 7200) {
        unlink($file);
    }
}
