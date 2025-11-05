<?php
/**
 * NOBA EXPERTS - ADMIN DASHBOARD API
 * Version: 1.0
 * Backend für Admin Dashboard PWA
 */

// Zeitzone für Deutschland setzen
date_default_timezone_set('Europe/Berlin');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// CORS
$allowed_origins = [
    'https://chatbot.noba-experts.de',
    'http://localhost:5173',
    'http://localhost:3000',
    'http://127.0.0.1:5173',
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
}

// OPTIONS Request (Preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Konstanten
define('JWT_SECRET', 'NOBA_Admin_Dashboard_2025_SECRET_KEY'); // In Produktion aus .env
define('ADMIN_EMAIL', 'Jurak.Bahrambaek@noba-experts.de');
define('ADMIN_PASSWORD_HASH', '$2y$10$F7qfPcMzpc9wkvXRrPUyreupCg.OvmBPR/Nywv6QpRakStqF5FCBy'); // "admin123" - ÄNDERN!

// HubSpot Integration
// WICHTIG: Token aus Sicherheitsgründen in hubspot-config.php ausgelagert
$hubspotConfigFile = __DIR__ . '/hubspot-config.php';
if (file_exists($hubspotConfigFile)) {
    require_once $hubspotConfigFile;
} else {
    define('HUBSPOT_ACCESS_TOKEN', getenv('HUBSPOT_ACCESS_TOKEN') ?: '');
    define('HUBSPOT_PORTAL_ID', '146015266');
}

// Action bestimmen
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Public Actions (kein Auth erforderlich)
if ($action === 'login') {
    handleLogin();
    exit;
}

// Alle anderen Actions: Auth prüfen
$token = getBearerToken();
if (!$token || !validateJWT($token)) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized - Invalid or missing token']));
}

// Router
switch ($action) {
    case 'verify':
        handleVerifyToken();
        break;

    case 'get_stats':
        handleGetStats();
        break;

    case 'get_conversations':
        handleGetConversations();
        break;

    case 'get_conversation':
        handleGetConversation();
        break;

    case 'get_analytics':
        handleGetAnalytics();
        break;

    case 'export':
        handleExport();
        break;

    case 'delete_conversation':
        handleDeleteConversation();
        break;

    case 'favorite':
        handleFavorite();
        break;

    case 'ai_analyze':
        handleAIAnalyze();
        break;

    case 'sync_to_hubspot':
        handleSyncToHubSpot();
        break;

    case 'batch_delete':
        handleBatchDelete();
        break;

    case 'batch_email':
        handleBatchEmail();
        break;

    case 'batch_sync_hubspot':
        handleBatchSyncHubSpot();
        break;

    case 'send_summary_email':
        handleSendSummaryEmail();
        break;

    // ===== VAKANZEN MANAGEMENT =====
    case 'upload_vacancy':
        handleUploadVacancy();
        break;

    case 'get_vacancies':
        handleGetVacancies();
        break;

    case 'update_vacancy':
        handleUpdateVacancy();
        break;

    case 'delete_vacancy':
        handleDeleteVacancy();
        break;

    // ===== KANDIDATENPROFILE MANAGEMENT =====
    case 'upload_candidate':
        handleUploadCandidate();
        break;

    case 'get_candidates':
        handleGetCandidates();
        break;

    case 'update_candidate':
        handleUpdateCandidate();
        break;

    case 'delete_candidate':
        handleDeleteCandidate();
        break;

    // ===== PROJEKT-ANALYSE =====
    case 'upload_project':
        handleUploadProject();
        break;

    case 'get_projects':
        handleGetProjects();
        break;

    case 'update_project':
        handleUpdateProject();
        break;

    case 'delete_project':
        handleDeleteProject();
        break;

    case 'analyze_project':
        handleAnalyzeProject();
        break;

    // ===== MATCHING/INTEREST TRACKING =====
    case 'save_interest':
        handleSaveInterest();
        break;

    case 'get_matches':
        handleGetMatches();
        break;

    case 'delete_match':
        handleDeleteMatch();
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Unknown action: ' . $action]);
}

// ===== HANDLERS =====

function handleLogin() {
    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? '';
    $password = $input['password'] ?? '';

    if ($email !== ADMIN_EMAIL || !password_verify($password, ADMIN_PASSWORD_HASH)) {
        http_response_code(401);
        die(json_encode(['error' => 'Ungültige Anmeldedaten']));
    }

    // JWT generieren
    $token = generateJWT([
        'email' => $email,
        'role' => 'admin',
        'exp' => time() + (8 * 60 * 60), // 8 Stunden
    ]);

    echo json_encode([
        'success' => true,
        'token' => $token,
        'user' => [
            'id' => '1',
            'email' => $email,
            'name' => 'Admin',
            'role' => 'admin',
        ],
        'expires_at' => date('c', time() + (8 * 60 * 60)),
    ]);
}

function handleVerifyToken() {
    // Token wurde bereits in Zeile 62-66 validiert
    // Wenn wir hier sind, ist das Token gültig
    $token = getBearerToken();
    $payload = decodeJWT($token);

    echo json_encode([
        'success' => true,
        'user' => [
            'id' => '1',
            'email' => $payload['email'] ?? ADMIN_EMAIL,
            'name' => 'Admin',
            'role' => $payload['role'] ?? 'admin',
        ],
    ]);
}

function handleGetStats() {
    $conversations = loadConversations();

    // Statistiken berechnen
    $total_conversations = count($conversations);
    $qualified_leads = 0;
    $hot_leads = 0;
    $document_uploads = 0;
    $with_email = 0;
    $with_phone = 0;
    $today_conversations = 0;
    $weekly_conversations = 0;
    $total_lead_score = 0;
    $conversations_with_score = 0;

    $now = time();
    $today_start = strtotime('today midnight');
    $week_start = strtotime('monday this week midnight');

    foreach ($conversations as $conv) {
        $leadScore = $conv['extracted_data']['lead_score'] ?? 0;
        $timestamp = strtotime($conv['timestamp']);

        // Qualified leads (score >= 40)
        if ($leadScore >= 40) {
            $qualified_leads++;
        }

        // Hot leads (score >= 70)
        if ($leadScore >= 70) {
            $hot_leads++;
        }

        // Documents
        if (!empty($conv['document_context'])) {
            $document_uploads++;
        }

        // Contact info
        if (!empty($conv['extracted_data']['email'])) {
            $with_email++;
        }

        if (!empty($conv['extracted_data']['phone'])) {
            $with_phone++;
        }

        // Today conversations
        if ($timestamp >= $today_start) {
            $today_conversations++;
        }

        // Weekly conversations
        if ($timestamp >= $week_start) {
            $weekly_conversations++;
        }

        // Average lead score
        if ($leadScore > 0) {
            $total_lead_score += $leadScore;
            $conversations_with_score++;
        }
    }

    // Calculate averages
    $avg_lead_score = $conversations_with_score > 0
        ? round($total_lead_score / $conversations_with_score, 1)
        : 0;

    $conversion_rate = $total_conversations > 0
        ? round(($with_email / $total_conversations) * 100, 1)
        : 0;

    echo json_encode([
        'success' => true,
        'stats' => [
            'total_conversations' => $total_conversations,
            'qualified_leads' => $qualified_leads,
            'hot_leads' => $hot_leads,
            'today_conversations' => $today_conversations,
            'weekly_conversations' => $weekly_conversations,
            'document_uploads' => $document_uploads,
            'with_email' => $with_email,
            'with_phone' => $with_phone,
            'avg_lead_score' => $avg_lead_score,
            'conversion_rate' => $conversion_rate,
        ],
    ]);
}

function handleGetConversations() {
    $conversations = loadConversations();

    // Query-Parameter für Filterung
    $timeframe = $_GET['timeframe'] ?? '30d';
    $lead_score = $_GET['lead_score'] ?? 'all';
    $has_document = $_GET['has_document'] ?? 'all';
    $has_contact = $_GET['has_contact'] ?? 'all';
    $lead_type = $_GET['lead_type'] ?? 'all';
    $search = $_GET['search'] ?? '';
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = (int)($_GET['offset'] ?? 0);

    // Filtern
    $filtered = array_filter($conversations, function($conv) use ($timeframe, $lead_score, $has_document, $has_contact, $lead_type, $search) {
        // Zeitraum
        if ($timeframe !== 'all') {
            $timestamp = strtotime($conv['timestamp']);
            $cutoff = match($timeframe) {
                '24h' => time() - (24 * 60 * 60),
                '7d' => time() - (7 * 24 * 60 * 60),
                '30d' => time() - (30 * 24 * 60 * 60),
                default => 0,
            };
            if ($timestamp < $cutoff) return false;
        }

        // Lead-Score
        if ($lead_score !== 'all') {
            $score = $conv['extracted_data']['lead_score'] ?? 0;
            $match = match($lead_score) {
                'hot' => $score >= 70,
                'warm' => $score >= 40 && $score < 70,
                'cold' => $score < 40,
                default => true,
            };
            if (!$match) return false;
        }

        // Dokument
        if ($has_document !== 'all') {
            $hasDoc = !empty($conv['document_context']);
            if ($has_document === 'yes' && !$hasDoc) return false;
            if ($has_document === 'no' && $hasDoc) return false;
        }

        // Kontaktdaten
        if ($has_contact !== 'all') {
            $hasContact = !empty($conv['extracted_data']['email']) || !empty($conv['extracted_data']['phone']);
            if ($has_contact === 'yes' && !$hasContact) return false;
            if ($has_contact === 'no' && $hasContact) return false;
        }

        // Lead-Typ
        if ($lead_type !== 'all') {
            $type = $conv['extracted_data']['lead_type'] ?? 'unknown';
            if ($lead_type !== $type) return false;
        }

        // Suche
        if ($search) {
            $searchable = json_encode($conv, JSON_UNESCAPED_UNICODE);
            if (stripos($searchable, $search) === false) return false;
        }

        return true;
    });

    // Sortieren (neueste zuerst)
    usort($filtered, fn($a, $b) => strtotime($b['timestamp']) <=> strtotime($a['timestamp']));

    // Paginierung
    $total = count($filtered);
    $paginated = array_slice($filtered, $offset, $limit);

    // Translate all messages to German for admin dashboard
    $paginated = translateConversations($paginated);

    echo json_encode([
        'success' => true,
        'data' => array_values($paginated),
        'meta' => [
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $total,
        ],
    ]);
}

function handleGetConversation() {
    $session_id = $_GET['session_id'] ?? '';

    if (!$session_id) {
        http_response_code(400);
        die(json_encode(['error' => 'Missing session_id']));
    }

    $conversations = loadConversations();
    $conversation = array_values(array_filter($conversations, fn($c) => $c['session_id'] === $session_id))[0] ?? null;

    if (!$conversation) {
        http_response_code(404);
        die(json_encode(['error' => 'Conversation not found']));
    }

    // Translate messages to German for admin dashboard
    $conversation = translateConversations([$conversation])[0];

    echo json_encode([
        'success' => true,
        'data' => $conversation,
    ]);
}

function handleGetAnalytics() {
    $conversations = loadConversations();
    $timeframe = $_GET['timeframe'] ?? '30d';

    // Zeitfilter anwenden
    if ($timeframe !== 'all') {
        $cutoff = match($timeframe) {
            '24h' => time() - (24 * 60 * 60),
            '7d' => time() - (7 * 24 * 60 * 60),
            '30d' => time() - (30 * 24 * 60 * 60),
            default => 0,
        };
        $conversations = array_filter($conversations, fn($c) => strtotime($c['timestamp']) >= $cutoff);
    }

    // Statistiken berechnen
    $stats = [
        'total_conversations' => count($conversations),
        'qualified_leads' => count(array_filter($conversations, fn($c) => ($c['extracted_data']['lead_score'] ?? 0) >= 40)),
        'hot_leads' => count(array_filter($conversations, fn($c) => ($c['extracted_data']['lead_score'] ?? 0) >= 70)),
        'warm_leads' => count(array_filter($conversations, function($c) {
            $score = $c['extracted_data']['lead_score'] ?? 0;
            return $score >= 40 && $score < 70;
        })),
        'cold_leads' => count(array_filter($conversations, fn($c) => ($c['extracted_data']['lead_score'] ?? 0) < 40)),
        'document_uploads' => count(array_filter($conversations, fn($c) => !empty($c['document_context']))),
        'with_email' => count(array_filter($conversations, fn($c) => !empty($c['extracted_data']['email']))),
        'with_phone' => count(array_filter($conversations, fn($c) => !empty($c['extracted_data']['phone']))),
        'employers' => count(array_filter($conversations, fn($c) => ($c['extracted_data']['lead_type'] ?? '') === 'employer')),
        'candidates' => count(array_filter($conversations, fn($c) => ($c['extracted_data']['lead_type'] ?? '') === 'candidate')),
    ];

    // Durchschnittswerte
    $lead_scores = array_map(fn($c) => $c['extracted_data']['lead_score'] ?? 0, $conversations);
    $stats['avg_lead_score'] = count($lead_scores) > 0 ? round(array_sum($lead_scores) / count($lead_scores), 1) : 0;

    // Konversionsrate
    $stats['conversion_rate'] = $stats['total_conversations'] > 0
        ? round(($stats['with_email'] / $stats['total_conversations']) * 100, 1)
        : 0;

    // Charts-Daten
    $charts = [
        'conversations_over_time' => getConversationsOverTime($conversations),
        'lead_score_distribution' => getLeadScoreDistribution($conversations),
        'top_technologies' => getTopTechnologies($conversations, 10),
        'top_locations' => getTopLocations($conversations, 10),
    ];

    echo json_encode([
        'success' => true,
        'data' => [
            'stats' => $stats,
            'charts' => $charts,
        ],
    ]);
}

function handleExport() {
    $conversations = loadConversations();
    $format = $_POST['format'] ?? 'csv';

    // Filter anwenden (wie bei get_conversations)
    $timeframe = $_POST['timeframe'] ?? 'all';
    $lead_score = $_POST['lead_score'] ?? 'all';

    $filtered = array_filter($conversations, function($conv) use ($timeframe, $lead_score) {
        if ($timeframe !== 'all') {
            $timestamp = strtotime($conv['timestamp']);
            $cutoff = match($timeframe) {
                '24h' => time() - (24 * 60 * 60),
                '7d' => time() - (7 * 24 * 60 * 60),
                '30d' => time() - (30 * 24 * 60 * 60),
                default => 0,
            };
            if ($timestamp < $cutoff) return false;
        }

        if ($lead_score !== 'all') {
            $score = $conv['extracted_data']['lead_score'] ?? 0;
            $match = match($lead_score) {
                'hot' => $score >= 70,
                'warm' => $score >= 40 && $score < 70,
                'cold' => $score < 40,
                default => true,
            };
            if (!$match) return false;
        }

        return true;
    });

    if ($format === 'csv') {
        exportToCSV($filtered);
    } elseif ($format === 'json') {
        exportToJSON($filtered);
    }
}

function handleDeleteConversation() {
    // DELETE-Requests senden JSON im Body
    $input = json_decode(file_get_contents('php://input'), true);
    $session_id = $_POST['session_id'] ?? $input['session_id'] ?? $_GET['session_id'] ?? '';

    if (!$session_id) {
        http_response_code(400);
        die(json_encode(['error' => 'Missing session_id']));
    }

    $conversations = loadConversations();
    $filtered = array_filter($conversations, fn($c) => $c['session_id'] !== $session_id);

    if (count($filtered) === count($conversations)) {
        http_response_code(404);
        die(json_encode(['error' => 'Conversation not found']));
    }

    saveConversations(array_values($filtered));

    error_log("DSGVO: Konversation gelöscht - Session: $session_id - von Admin");

    echo json_encode(['success' => true, 'message' => 'Konversation gelöscht']);
}

function handleFavorite() {
    // Unterstütze JSON-Body und POST
    $input = json_decode(file_get_contents('php://input'), true);
    $session_id = $_POST['session_id'] ?? $input['session_id'] ?? '';
    $is_favorite = filter_var($_POST['is_favorite'] ?? $input['is_favorite'] ?? false, FILTER_VALIDATE_BOOLEAN);

    $admin_data = loadAdminData();

    if ($is_favorite) {
        if (!in_array($session_id, $admin_data['favorites'])) {
            $admin_data['favorites'][] = $session_id;
        }
    } else {
        $admin_data['favorites'] = array_values(array_filter($admin_data['favorites'], fn($id) => $id !== $session_id));
    }

    saveAdminData($admin_data);

    echo json_encode(['success' => true]);
}

function handleAIAnalyze() {
    // JSON body support für POST-Requests
    $input = json_decode(file_get_contents('php://input'), true);
    $session_id = $_POST['session_id'] ?? $input['session_id'] ?? $_GET['session_id'] ?? '';

    if (!$session_id) {
        http_response_code(400);
        die(json_encode(['error' => 'Missing session_id']));
    }

    $conversations = loadConversations();
    $conversation = array_values(array_filter($conversations, fn($c) => $c['session_id'] === $session_id))[0] ?? null;

    if (!$conversation) {
        http_response_code(404);
        die(json_encode(['error' => 'Conversation not found']));
    }

    // KI-Analyse mit Google Gemini
    $analysis = analyzeLeadWithAI($conversation);

    // Automatisch zu HubSpot synchronisieren
    $hubspotResult = null;
    if (HUBSPOT_ACCESS_TOKEN) {
        $hubspotResult = syncAnalysisToHubSpot($conversation, $analysis);
    }

    echo json_encode([
        'success' => true,
        'data' => $analysis,
        'hubspot_sync' => $hubspotResult ?? ['success' => false, 'message' => 'HubSpot nicht konfiguriert'],
    ]);
}

function handleSyncToHubSpot() {
    // JSON body support für POST-Requests
    $input = json_decode(file_get_contents('php://input'), true);
    $session_id = $_POST['session_id'] ?? $input['session_id'] ?? $_GET['session_id'] ?? '';

    if (!$session_id) {
        http_response_code(400);
        die(json_encode(['error' => 'Missing session_id']));
    }

    if (!HUBSPOT_ACCESS_TOKEN) {
        http_response_code(500);
        die(json_encode(['error' => 'HubSpot API nicht konfiguriert. Bitte ACCESS_TOKEN setzen.']));
    }

    $conversations = loadConversations();
    $conversation = array_values(array_filter($conversations, fn($c) => $c['session_id'] === $session_id))[0] ?? null;

    if (!$conversation) {
        http_response_code(404);
        die(json_encode(['error' => 'Conversation not found']));
    }

    $result = syncToHubSpot($conversation);

    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Erfolgreich zu HubSpot synchronisiert',
            'hubspot_contact_id' => $result['contact_id'],
            'hubspot_url' => "https://app.hubspot.com/contacts/" . HUBSPOT_PORTAL_ID . "/contact/" . $result['contact_id']
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'error' => 'HubSpot Sync fehlgeschlagen: ' . $result['message']
        ]);
    }
}

// ===== HELPER FUNCTIONS =====

function loadConversations() {
    // FIX: Logger speichert in backend/, nicht parent directory!
    $file = __DIR__ . '/chatbot-conversations.json';
    if (!file_exists($file)) return [];
    $data = json_decode(file_get_contents($file), true);
    if (!$data) return [];

    // Duplikate entfernen - nur neueste Version jeder Session behalten
    $unique = [];
    foreach ($data as $conv) {
        $session_id = $conv['session_id'] ?? '';
        if (!$session_id) continue;

        // Wenn Session noch nicht existiert ODER die neue Version mehr Messages hat
        if (!isset($unique[$session_id]) ||
            count($conv['messages'] ?? []) > count($unique[$session_id]['messages'] ?? [])) {
            $unique[$session_id] = $conv;
        }
    }

    return array_values($unique);
}

function saveConversations($conversations) {
    // FIX: Logger speichert in backend/, nicht parent directory!
    $file = __DIR__ . '/chatbot-conversations.json';
    file_put_contents($file, json_encode($conversations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function loadAdminData() {
    $file = dirname(__DIR__) . '/admin-data.json';
    if (!file_exists($file)) {
        return [
            'favorites' => [],
            'settings' => [],
        ];
    }
    return json_decode(file_get_contents($file), true);
}

function saveAdminData($data) {
    $file = dirname(__DIR__) . '/admin-data.json';
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Translation Functions
function translateTextToGerman($text) {
    // Skip if already German or empty
    if (empty(trim($text))) {
        return $text;
    }

    // Load config from chatbot-api.php
    $config_file = __DIR__ . '/chatbot-api.php';
    if (!file_exists($config_file)) {
        return $text; // Fallback to original if config not found
    }

    // Extract API key from chatbot-api.php
    $config_content = file_get_contents($config_file);
    preg_match("/'GOOGLE_AI_API_KEY'\s*=>\s*'([^']+)'/", $config_content, $matches);
    $api_key = $matches[1] ?? null;

    if (!$api_key) {
        return $text;
    }

    $model = 'gemini-2.5-flash-lite';
    $url = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key=$api_key";

    $prompt = "Übersetze den folgenden Text ins Deutsche. Wenn der Text bereits auf Deutsch ist, gib ihn unverändert zurück. Gib NUR die Übersetzung zurück, ohne zusätzliche Erklärungen:\n\n$text";

    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.3,
            'maxOutputTokens' => 1000,
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200 && $response) {
        $result = json_decode($response, true);
        $translated = $result['candidates'][0]['content']['parts'][0]['text'] ?? $text;
        return trim($translated);
    }

    return $text; // Fallback to original if translation fails
}

function translateConversations($conversations) {
    // DISABLED: Translation was causing API rate limit issues and slowing down dashboard
    // Most messages are already in German, no need for translation
    foreach ($conversations as &$conv) {
        if (isset($conv['messages']) && is_array($conv['messages'])) {
            foreach ($conv['messages'] as &$msg) {
                if (isset($msg['text'])) {
                    // Simply copy text to text_de (already German)
                    $msg['text_de'] = $msg['text'];
                }
            }
        }
    }
    return $conversations;
}

// JWT Functions
function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64UrlDecode($data) {
    return base64_decode(strtr($data, '-_', '+/'));
}

function generateJWT($payload) {
    $header = base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload_encoded = base64UrlEncode(json_encode($payload));
    $signature = hash_hmac('sha256', "$header.$payload_encoded", JWT_SECRET, true);
    $signature_encoded = base64UrlEncode($signature);
    return "$header.$payload_encoded.$signature_encoded";
}

function validateJWT($token) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return false;

    [$header, $payload, $signature] = $parts;
    $expected_signature = base64UrlEncode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));

    if ($signature !== $expected_signature) return false;

    $payload_data = json_decode(base64UrlDecode($payload), true);
    if (!$payload_data || ($payload_data['exp'] ?? 0) < time()) return false;

    return true;
}

function decodeJWT($token) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;

    [$header, $payload, $signature] = $parts;
    return json_decode(base64UrlDecode($payload), true);
}

function getBearerToken() {
    $headers = getallheaders();
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (preg_match('/Bearer\s+(.*)$/i', $auth, $matches)) {
        return $matches[1];
    }
    return null;
}

// Analytics Helper
function getConversationsOverTime($conversations) {
    $grouped = [];
    foreach ($conversations as $conv) {
        $date = date('Y-m-d', strtotime($conv['timestamp']));
        $grouped[$date] = ($grouped[$date] ?? 0) + 1;
    }

    ksort($grouped);

    return array_map(fn($date, $count) => ['date' => $date, 'count' => $count], array_keys($grouped), array_values($grouped));
}

function getLeadScoreDistribution($conversations) {
    $ranges = ['Kalt (0-39)' => 0, 'Warm (40-69)' => 0, 'Heiß (70-100)' => 0];

    foreach ($conversations as $conv) {
        $score = $conv['extracted_data']['lead_score'] ?? 0;
        if ($score < 40) $ranges['Kalt (0-39)']++;
        elseif ($score < 70) $ranges['Warm (40-69)']++;
        else $ranges['Heiß (70-100)']++;
    }

    return array_map(fn($range, $count) => ['range' => $range, 'count' => $count], array_keys($ranges), array_values($ranges));
}

function getTopTechnologies($conversations, $limit = 10) {
    $tech_count = [];

    foreach ($conversations as $conv) {
        $tech_stack = $conv['extracted_data']['tech_stack'] ?? [];
        foreach ($tech_stack as $tech) {
            $tech_count[$tech] = ($tech_count[$tech] ?? 0) + 1;
        }
    }

    arsort($tech_count);
    $top = array_slice($tech_count, 0, $limit, true);

    return array_map(fn($name, $count) => ['name' => $name, 'count' => $count], array_keys($top), array_values($top));
}

function getTopLocations($conversations, $limit = 10) {
    $location_count = [];

    foreach ($conversations as $conv) {
        $location = $conv['extracted_data']['location'] ?? null;
        if ($location) {
            $location_count[$location] = ($location_count[$location] ?? 0) + 1;
        }
    }

    arsort($location_count);
    $top = array_slice($location_count, 0, $limit, true);

    return array_map(fn($name, $count) => ['name' => $name, 'count' => $count], array_keys($top), array_values($top));
}

// Export Functions
function exportToCSV($conversations) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="noba_leads_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    // BOM für Excel UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Header
    fputcsv($output, [
        'Session ID', 'Datum', 'Lead-Score', 'Typ', 'Name', 'E-Mail', 'Telefon',
        'Firma', 'Position', 'Tech-Stack', 'Standort', 'Dringlichkeit',
        'Dokument', 'Nachrichten'
    ], ';');

    // Daten
    foreach ($conversations as $conv) {
        $data = $conv['extracted_data'];
        fputcsv($output, [
            $conv['session_id'],
            date('d.m.Y H:i', strtotime($conv['timestamp'])),
            $data['lead_score'] ?? 0,
            $data['lead_type'] ?? '',
            $data['name'] ?? '',
            $data['email'] ?? '',
            $data['phone'] ?? '',
            $data['company'] ?? '',
            $data['position'] ?? '',
            implode(', ', $data['tech_stack'] ?? []),
            $data['location'] ?? '',
            $data['urgency'] ?? '',
            !empty($conv['document_context']) ? 'Ja' : 'Nein',
            count($conv['messages']),
        ], ';');
    }

    fclose($output);
    exit;
}

function exportToJSON($conversations) {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="noba_leads_' . date('Y-m-d') . '.json"');

    echo json_encode($conversations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// KI-Analyse mit Google Gemini
function analyzeLeadWithAI($conversation) {
    $api_key = 'AIzaSyBtwnfTYAJgtJDSU7Lp5C8s5Dnw6PUYP2A'; // Aus chatbot-api.php
    $model = 'gemini-2.0-flash-exp';

    $lead_data = $conversation['extracted_data'] ?? [];
    $messages = $conversation['messages'] ?? [];
    $document = $conversation['document_context'] ?? null;

    // Chat-Verlauf als Text
    $chat_text = '';
    foreach ($messages as $msg) {
        $role = $msg['role'] === 'user' ? 'User' : 'Bot';
        $chat_text .= "$role: " . $msg['text'] . "\n";
    }

    // Analyse-Prompt für Gemini
    $prompt = "Analysiere diesen Lead für ein IT & Engineering Recruiting-Unternehmen (NOBA Experts).

**LEAD-DATEN:**
- Lead-Score: " . ($lead_data['lead_score'] ?? 0) . "/100
- Lead-Typ: " . ($lead_data['lead_type'] ?? 'unbekannt') . "
- Name: " . ($lead_data['name'] ?? 'nicht angegeben') . "
- E-Mail: " . ($lead_data['email'] ?? 'nicht angegeben') . "
- Telefon: " . ($lead_data['phone'] ?? 'nicht angegeben') . "
- Firma: " . ($lead_data['company'] ?? 'nicht angegeben') . "
- Position: " . ($lead_data['position'] ?? 'nicht angegeben') . "
- Tech-Stack: " . (implode(', ', $lead_data['tech_stack'] ?? []) ?: 'nicht angegeben') . "
- Erfahrung: " . ($lead_data['experience_level'] ?? 'nicht angegeben') . "
- Standort: " . ($lead_data['location'] ?? 'nicht angegeben') . "
- Dringlichkeit: " . ($lead_data['urgency'] ?? 'nicht angegeben') . "
" . ($document ? "- Dokument: " . $document['type'] . " (" . $document['filename'] . ")\n" : "") . "

**CHAT-VERLAUF:**
$chat_text

**AUFGABE:**
Erstelle eine professionelle Analyse dieses Leads in folgendem JSON-Format:

{
  \"lead_quality\": \"Bewertung in einem Satz (15-25 Wörter)\",
  \"key_insights\": [
    \"3-5 wichtige Erkenntnisse über den Lead\"
  ],
  \"strengths\": [
    \"3-5 Stärken/Positive Aspekte\"
  ],
  \"concerns\": [
    \"2-3 mögliche Bedenken oder fehlende Informationen\"
  ],
  \"next_actions\": [
    \"3-5 konkrete nächste Schritte mit Priorität\"
  ],
  \"recommended_approach\": \"Empfohlene Kontaktaufnahme-Strategie (50-100 Wörter)\",
  \"urgency_level\": \"Niedrig/Mittel/Hoch/Sehr hoch\",
  \"match_potential\": \"Einschätzung des Match-Potenzials (30-50 Wörter)\"
}

**WICHTIG:**
- Sei konkret und handlungsorientiert
- Fokus auf praktische nächste Schritte
- Berücksichtige die Dringlichkeit
- Bewerte realistisch
- Nutze IT-Recruiting-Expertise

Antworte NUR mit dem JSON-Objekt, nichts anderes!";

    // Gemini API Call
    $url = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key=$api_key";

    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.3,
            'maxOutputTokens' => 1500,
        ],
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        error_log("Gemini API Error: HTTP $http_code - $response");
        return [
            'error' => 'KI-Analyse fehlgeschlagen',
            'lead_quality' => 'Automatische Analyse nicht verfügbar',
            'key_insights' => ['Manuelle Analyse erforderlich'],
        ];
    }

    $result = json_decode($response, true);
    $ai_text = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';

    // JSON aus Antwort extrahieren
    $ai_text = trim($ai_text);
    $ai_text = preg_replace('/^```json\s*/s', '', $ai_text);
    $ai_text = preg_replace('/\s*```$/s', '', $ai_text);

    $analysis = json_decode($ai_text, true);

    if (!$analysis) {
        error_log("Failed to parse AI response: $ai_text");
        return [
            'error' => 'KI-Antwort konnte nicht verarbeitet werden',
            'raw_response' => $ai_text,
        ];
    }

    // Zeitstempel hinzufügen
    $analysis['generated_at'] = date('c');
    $analysis['session_id'] = $conversation['session_id'];

    return $analysis;
}

// HubSpot Integration
function syncToHubSpot($conversation) {
    $data = $conversation['extracted_data'];
    $email = $data['email'] ?? '';
    $name = $data['name'] ?? '';
    $phone = $data['phone'] ?? '';
    $company = $data['company'] ?? '';

    $usePlaceholderEmail = false;

    // E-Mail-Validierung: Entweder echte E-Mail oder mindestens Name
    if (!$email) {
        // Wenn keine E-Mail, aber Name vorhanden → Placeholder-E-Mail generieren
        if ($name || $phone || $company) {
            // Generiere eindeutige Placeholder-E-Mail
            $sessionShort = substr($conversation['session_id'], 0, 8);
            $email = "noba.lead.{$sessionShort}@noba-placeholder.local";
            $usePlaceholderEmail = true;
        } else {
            return [
                'success' => false,
                'message' => 'Keine E-Mail-Adresse und keine alternativen Kontaktdaten vorhanden (Name, Telefon oder Firma erforderlich)'
            ];
        }
    }

    // HubSpot Contact Properties vorbereiten
    $properties = [
        'email' => $email,
    ];

    // Optionale Felder
    if (!empty($data['name'])) {
        $nameParts = explode(' ', $data['name'], 2);
        $properties['firstname'] = $nameParts[0];
        if (isset($nameParts[1])) {
            $properties['lastname'] = $nameParts[1];
        }
    }

    if (!empty($data['phone'])) {
        $properties['phone'] = $data['phone'];
    }

    if (!empty($data['company'])) {
        $properties['company'] = $data['company'];
    }

    if (!empty($data['position'])) {
        $properties['jobtitle'] = $data['position'];
    }

    if (!empty($data['location'])) {
        $properties['city'] = $data['location'];
    }

    // KEINE Custom Properties - diese existieren in HubSpot nicht!
    // Stattdessen werden lead_type, lead_score, tech_stack in der Notiz erwähnt

    // Notiz mit Chat-Verlauf und Lead-Daten erstellen
    $chatHistory = '';

    // Lead-Metadaten
    $chatHistory .= "📊 LEAD-INFORMATIONEN\n";
    $chatHistory .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

    if (!empty($data['lead_type'])) {
        $leadTypeLabel = $data['lead_type'] === 'employer' ? '💼 Kunde (sucht Mitarbeiter)' :
                        ($data['lead_type'] === 'candidate' ? '👔 Kandidat (sucht Job)' : '❓ Unbekannt');
        $chatHistory .= "Typ: {$leadTypeLabel}\n";
    }

    if (isset($data['lead_score'])) {
        $chatHistory .= "Lead-Score: {$data['lead_score']}/100\n";
    }

    if (!empty($data['tech_stack'])) {
        $chatHistory .= "Tech-Stack: " . implode(', ', $data['tech_stack']) . "\n";
    }

    if (!empty($data['experience_level'])) {
        $chatHistory .= "Erfahrung: {$data['experience_level']}\n";
    }

    $chatHistory .= "\n";

    // Warnung bei Placeholder-E-Mail
    if ($usePlaceholderEmail) {
        $chatHistory .= "⚠️ WICHTIG: Placeholder-E-Mail verwendet - Keine echte E-Mail-Adresse erfasst!\n";
        $chatHistory .= "Bitte echte E-Mail-Adresse nachträglich erfassen.\n\n";
    }

    $chatHistory .= "💬 CHAT-VERLAUF\n";
    $chatHistory .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

    foreach ($conversation['messages'] as $msg) {
        $role = $msg['role'] === 'user' ? 'User' : 'Bot';
        $chatHistory .= "[$role]: " . $msg['text'] . "\n";
    }

    // Kontakt erstellen oder aktualisieren (Upsert)
    $url = 'https://api.hubapi.com/crm/v3/objects/contacts';

    $postData = [
        'properties' => $properties
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . HUBSPOT_ACCESS_TOKEN
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 201 || $http_code === 200) {
        $responseData = json_decode($response, true);
        $contactId = $responseData['id'];

        // Note hinzufügen mit Chat-Verlauf
        addNoteToContact($contactId, $chatHistory, $conversation['session_id'], $usePlaceholderEmail);

        $message = $usePlaceholderEmail
            ? 'Kontakt erfolgreich erstellt (⚠️ Placeholder-E-Mail - bitte echte E-Mail nachtragen)'
            : 'Kontakt erfolgreich erstellt';

        return [
            'success' => true,
            'contact_id' => $contactId,
            'message' => $message,
            'placeholder_email' => $usePlaceholderEmail,
            'contact_created' => ($http_code === 201) // 201 = neu erstellt, 200 = aktualisiert
        ];
    } elseif ($http_code === 409) {
        // Kontakt existiert bereits - Update via E-Mail
        return updateExistingContact($email, $properties, $chatHistory, $conversation['session_id'], $usePlaceholderEmail);
    } else {
        $error = json_decode($response, true);
        return [
            'success' => false,
            'message' => $error['message'] ?? 'Unknown error',
            'http_code' => $http_code,
            'contact_created' => false
        ];
    }
}

function updateExistingContact($email, $properties, $chatHistory, $sessionId, $usePlaceholderEmail = false) {
    // Kontakt via E-Mail suchen
    $searchUrl = 'https://api.hubapi.com/crm/v3/objects/contacts/search';
    $searchData = [
        'filterGroups' => [
            [
                'filters' => [
                    [
                        'propertyName' => 'email',
                        'operator' => 'EQ',
                        'value' => $email
                    ]
                ]
            ]
        ]
    ];

    $ch = curl_init($searchUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($searchData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . HUBSPOT_ACCESS_TOKEN
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        return [
            'success' => false,
            'message' => 'Kontakt-Suche fehlgeschlagen',
            'contact_created' => false
        ];
    }

    $searchResult = json_decode($response, true);
    if (empty($searchResult['results'])) {
        return [
            'success' => false,
            'message' => 'Kontakt nicht gefunden',
            'contact_created' => false
        ];
    }

    $contactId = $searchResult['results'][0]['id'];

    // Kontakt aktualisieren
    $updateUrl = "https://api.hubapi.com/crm/v3/objects/contacts/$contactId";
    $updateData = ['properties' => $properties];

    $ch = curl_init($updateUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($updateData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . HUBSPOT_ACCESS_TOKEN
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        // Note hinzufügen
        addNoteToContact($contactId, $chatHistory, $sessionId, $usePlaceholderEmail);

        $message = $usePlaceholderEmail
            ? 'Kontakt erfolgreich aktualisiert (⚠️ Placeholder-E-Mail - bitte echte E-Mail nachtragen)'
            : 'Kontakt erfolgreich aktualisiert';

        return [
            'success' => true,
            'contact_id' => $contactId,
            'message' => $message,
            'placeholder_email' => $usePlaceholderEmail,
            'contact_created' => false // Update = nicht neu erstellt
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Kontakt-Update fehlgeschlagen',
            'http_code' => $http_code,
            'contact_created' => false
        ];
    }
}

function addNoteToContact($contactId, $chatHistory, $sessionId, $usePlaceholderEmail = false) {
    $noteUrl = 'https://api.hubapi.com/crm/v3/objects/notes';

    $noteData = [
        'properties' => [
            'hs_timestamp' => date('c'),
            'hs_note_body' => "**NOBA Chatbot Konversation**\n\nSession: $sessionId\nDatum: " . date('d.m.Y H:i') . "\n\n---\n\n$chatHistory"
        ],
        'associations' => [
            [
                'to' => ['id' => $contactId],
                'types' => [
                    [
                        'associationCategory' => 'HUBSPOT_DEFINED',
                        'associationTypeId' => 202 // Note to Contact
                    ]
                ]
            ]
        ]
    ];

    $ch = curl_init($noteUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($noteData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . HUBSPOT_ACCESS_TOKEN
    ]);

    curl_exec($ch);
    curl_close($ch);
}

/**
 * KI-Analyse zu HubSpot synchronisieren
 * Erstellt/aktualisiert Kontakt und fügt Analyse als Notiz hinzu
 */
function syncAnalysisToHubSpot($conversation, $analysis) {
    // Zuerst: Kontakt zu HubSpot syncen
    $syncResult = syncToHubSpot($conversation);

    if (!$syncResult['success']) {
        return [
            'success' => false,
            'message' => 'Kontakt-Sync fehlgeschlagen: ' . $syncResult['message']
        ];
    }

    $contactId = $syncResult['contact_id'];
    $isNewContact = isset($syncResult['contact_created']) && $syncResult['contact_created'];

    // KI-Analyse als formatierte Notiz erstellen
    $analysisNote = formatAnalysisAsNote($analysis, $conversation['session_id']);

    // Notiz zu Kontakt hinzufügen
    $noteResult = addAnalysisNoteToContact($contactId, $analysisNote);

    // Task/Reminder IMMER erstellen (auch bei vorhandenen Kontakten = neue Lead-Aktivität)
    $taskResult = createFollowUpTask($contactId, $conversation, $analysis, $isNewContact);

    // E-Mail-Benachrichtigung an Admin senden
    $emailResult = sendAdminNotification($conversation, $analysis, $contactId, $isNewContact);

    return [
        'success' => true,
        'contact_id' => $contactId,
        'is_new_contact' => $isNewContact,
        'note_created' => $noteResult,
        'task_created' => $taskResult,
        'hubspot_url' => "https://app.hubspot.com/contacts/" . HUBSPOT_PORTAL_ID . "/contact/" . $contactId
    ];
}

/**
 * Formatiere KI-Analyse als HTML-Notiz
 */
function formatAnalysisAsNote($analysis, $sessionId) {
    $timestamp = date('d.m.Y H:i');

    $note = "🤖 **KI-ANALYSE - NOBA Lead Qualifizierung**\n\n";
    $note .= "Session: {$sessionId}\n";
    $note .= "Analysiert: {$timestamp}\n";
    $note .= "---\n\n";

    // Lead-Qualität
    if (isset($analysis['lead_quality'])) {
        $note .= "### 📊 Lead-Qualität\n";
        $note .= $analysis['lead_quality'] . "\n\n";
    }

    // Urgency Level
    if (isset($analysis['urgency_level'])) {
        $urgencyEmoji = match($analysis['urgency_level']) {
            'Sehr hoch' => '🔴',
            'Hoch' => '🟠',
            'Mittel' => '🟡',
            default => '🟢'
        };
        $note .= "### ⚡ Dringlichkeit\n";
        $note .= "{$urgencyEmoji} **{$analysis['urgency_level']}**\n\n";
    }

    // Key Insights
    if (!empty($analysis['key_insights'])) {
        $note .= "### 💡 Wichtigste Erkenntnisse\n";
        foreach ($analysis['key_insights'] as $insight) {
            $note .= "- {$insight}\n";
        }
        $note .= "\n";
    }

    // Stärken
    if (!empty($analysis['strengths'])) {
        $note .= "### ✅ Stärken\n";
        foreach ($analysis['strengths'] as $strength) {
            $note .= "- {$strength}\n";
        }
        $note .= "\n";
    }

    // Bedenken
    if (!empty($analysis['concerns'])) {
        $note .= "### ⚠️ Bedenken / Fehlende Infos\n";
        foreach ($analysis['concerns'] as $concern) {
            $note .= "- {$concern}\n";
        }
        $note .= "\n";
    }

    // Nächste Schritte
    if (!empty($analysis['next_actions'])) {
        $note .= "### 🎯 Nächste Schritte\n";
        foreach ($analysis['next_actions'] as $idx => $action) {
            $note .= ($idx + 1) . ". {$action}\n";
        }
        $note .= "\n";
    }

    // Empfohlene Vorgehensweise
    if (isset($analysis['recommended_approach'])) {
        $note .= "### 📞 Empfohlene Kontaktaufnahme\n";
        $note .= $analysis['recommended_approach'] . "\n\n";
    }

    // Match-Potenzial
    if (isset($analysis['match_potential'])) {
        $note .= "### 🎯 Match-Potenzial\n";
        $note .= $analysis['match_potential'] . "\n\n";
    }

    $note .= "---\n";
    $note .= "*Automatisch generiert durch NOBA KI-Assistenten*";

    return $note;
}

/**
 * Füge Analyse-Notiz zu HubSpot-Kontakt hinzu
 */
function addAnalysisNoteToContact($contactId, $analysisNote) {
    $noteUrl = 'https://api.hubapi.com/crm/v3/objects/notes';

    $noteData = [
        'properties' => [
            'hs_timestamp' => date('c'),
            'hs_note_body' => $analysisNote
        ],
        'associations' => [
            [
                'to' => ['id' => $contactId],
                'types' => [
                    [
                        'associationCategory' => 'HUBSPOT_DEFINED',
                        'associationTypeId' => 202 // Note to Contact
                    ]
                ]
            ]
        ]
    ];

    $ch = curl_init($noteUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($noteData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . HUBSPOT_ACCESS_TOKEN
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $http_code === 201 || $http_code === 200;
}

/**
 * Erstelle Follow-up Task in HubSpot
 */
function createFollowUpTask($contactId, $conversation, $analysis, $isNewContact = true) {
    $taskUrl = 'https://api.hubapi.com/crm/v3/objects/tasks';

    $leadData = $conversation['extracted_data'];
    $leadType = $leadData['lead_type'] ?? 'unknown';
    $leadTypeName = $leadType === 'employer' ? 'Kunde' : 'Kandidat';

    // Task-Titel basierend auf Lead-Typ und Urgency
    $urgency = $analysis['urgency_level'] ?? 'Mittel';
    $urgencyPrefix = match($urgency) {
        'Sehr hoch' => '🔴 DRINGEND',
        'Hoch' => '🟠 WICHTIG',
        'Mittel' => '🟡',
        default => '🟢'
    };

    $name = $leadData['name'] ?? 'Unbekannt';
    $statusText = $isNewContact ? 'Neuer' : 'Neue Aktivität';
    $taskTitle = "{$urgencyPrefix} {$statusText} {$leadTypeName}: {$name} - Follow-up erforderlich";

    // Task-Beschreibung mit wichtigsten Infos
    $taskBody = "**Neuer Lead aus NOBA Chatbot**\n\n";

    if (!empty($analysis['key_insights'])) {
        $taskBody .= "**Wichtigste Erkenntnisse:**\n";
        foreach (array_slice($analysis['key_insights'], 0, 3) as $insight) {
            $taskBody .= "• {$insight}\n";
        }
        $taskBody .= "\n";
    }

    if (!empty($analysis['next_actions'])) {
        $taskBody .= "**Empfohlene nächste Schritte:**\n";
        foreach (array_slice($analysis['next_actions'], 0, 3) as $action) {
            $taskBody .= "• {$action}\n";
        }
        $taskBody .= "\n";
    }

    $taskBody .= "Session: {$conversation['session_id']}\n";

    // Fälligkeitsdatum basierend auf Urgency
    $dueDate = match($urgency) {
        'Sehr hoch' => date('Y-m-d', strtotime('+1 day')),
        'Hoch' => date('Y-m-d', strtotime('+2 days')),
        'Mittel' => date('Y-m-d', strtotime('+3 days')),
        default => date('Y-m-d', strtotime('+1 week'))
    };

    // Priorität
    $priority = match($urgency) {
        'Sehr hoch' => 'HIGH',
        'Hoch' => 'HIGH',
        'Mittel' => 'MEDIUM',
        default => 'LOW'
    };

    $taskData = [
        'properties' => [
            'hs_task_subject' => $taskTitle,
            'hs_task_body' => $taskBody,
            'hs_task_status' => 'NOT_STARTED',
            'hs_task_priority' => $priority,
            'hs_timestamp' => date('c'),
            'hs_task_type' => 'TODO',
            'hubspot_owner_id' => null // Wird automatisch zugewiesen
        ],
        'associations' => [
            [
                'to' => ['id' => $contactId],
                'types' => [
                    [
                        'associationCategory' => 'HUBSPOT_DEFINED',
                        'associationTypeId' => 204 // Task to Contact
                    ]
                ]
            ]
        ]
    ];

    // Fälligkeitsdatum nur wenn verfügbar
    if ($dueDate) {
        $taskData['properties']['hs_task_due_date'] = $dueDate;
    }

    $ch = curl_init($taskUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($taskData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . HUBSPOT_ACCESS_TOKEN
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 201 || $http_code === 200) {
        $taskResult = json_decode($response, true);
        return [
            'success' => true,
            'task_id' => $taskResult['id'] ?? null,
            'due_date' => $dueDate
        ];
    } else {
        error_log("HubSpot Task Creation Failed: HTTP {$http_code} - {$response}");
        return [
            'success' => false,
            'error' => $response
        ];
    }
}

/**
 * Sende E-Mail-Benachrichtigung an Admin bei neuer Lead-Aktivität
 */
function sendAdminNotification($conversation, $analysis, $contactId, $isNewContact) {
    $adminEmail = 'Jurak.Bahrambaek@noba-experts.de';
    $leadData = $conversation['extracted_data'];

    $leadType = $leadData['lead_type'] ?? 'unknown';
    $leadTypeName = $leadType === 'employer' ? 'Kunde' : 'Kandidat';
    $name = $leadData['name'] ?? 'Unbekannt';
    $urgency = $analysis['urgency_level'] ?? 'Mittel';

    // Betreff
    $urgencyPrefix = match($urgency) {
        'Sehr hoch' => '🔴 DRINGEND',
        'Hoch' => '🟠 WICHTIG',
        'Mittel' => '🟡',
        default => '🟢'
    };

    $statusText = $isNewContact ? 'Neuer Lead' : 'Neue Aktivität bei bestehendem Kontakt';
    $subject = "{$urgencyPrefix} {$statusText}: {$leadTypeName} - {$name}";

    // HTML-Body
    $hubspotUrl = "https://app.hubspot.com/contacts/" . HUBSPOT_PORTAL_ID . "/contact/" . $contactId;
    $timestamp = date('d.m.Y H:i');

    $html = <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f9fafb;">
    <div style="max-width: 600px; margin: 0 auto; background: white;">

        <!-- Header -->
        <div style="background: linear-gradient(135deg, #FF7B29, #e66b24); padding: 30px; text-align: center;">
            <h1 style="margin: 0; color: white; font-size: 24px;">{$urgencyPrefix} {$statusText}</h1>
            <p style="margin: 10px 0 0 0; color: rgba(255,255,255,0.9); font-size: 14px;">NOBA Experts KI-Chatbot</p>
        </div>

        <!-- Content -->
        <div style="padding: 30px;">

            <!-- Info Box -->
            <div style="background: #f9fafb; border-left: 4px solid #FF7B29; padding: 20px; margin-bottom: 30px;">
                <p style="margin: 0 0 10px 0; color: #666; font-size: 14px;">
                    <strong style="color: #333;">Lead-Typ:</strong> {$leadTypeName}<br>
                    <strong style="color: #333;">Name:</strong> {$name}<br>
                    <strong style="color: #333;">Dringlichkeit:</strong> <span style="color: #FF7B29; font-weight: 600;">{$urgency}</span><br>
                    <strong style="color: #333;">Zeit:</strong> {$timestamp}
                </p>
            </div>

HTML;

    // Kontaktdaten
    if (!empty($leadData['email']) || !empty($leadData['phone'])) {
        $html .= <<<HTML
            <h2 style="color: #333; font-size: 18px; margin: 0 0 15px 0; border-bottom: 2px solid #FF7B29; padding-bottom: 10px;">
                📞 Kontaktdaten
            </h2>
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
HTML;

        if (!empty($leadData['email'])) {
            $email = htmlspecialchars($leadData['email']);
            $html .= <<<HTML
                <tr>
                    <td style="padding: 10px 0; border-bottom: 1px solid #f3f4f6; color: #666; font-size: 13px;">📧 E-Mail</td>
                    <td style="padding: 10px 0; border-bottom: 1px solid #f3f4f6; color: #333; font-size: 14px; font-weight: 500;">
                        <a href="mailto:{$email}" style="color: #FF7B29; text-decoration: none;">{$email}</a>
                    </td>
                </tr>
HTML;
        }

        if (!empty($leadData['phone'])) {
            $phone = htmlspecialchars($leadData['phone']);
            $phoneClean = preg_replace('/[^0-9+]/', '', $phone);
            $html .= <<<HTML
                <tr>
                    <td style="padding: 10px 0; border-bottom: 1px solid #f3f4f6; color: #666; font-size: 13px;">📞 Telefon</td>
                    <td style="padding: 10px 0; border-bottom: 1px solid #f3f4f6; color: #333; font-size: 14px; font-weight: 500;">
                        <a href="tel:{$phoneClean}" style="color: #FF7B29; text-decoration: none;">{$phone}</a>
                    </td>
                </tr>
HTML;
        }

        if (!empty($leadData['company'])) {
            $company = htmlspecialchars($leadData['company']);
            $html .= <<<HTML
                <tr>
                    <td style="padding: 10px 0; border-bottom: 1px solid #f3f4f6; color: #666; font-size: 13px;">🏢 Firma</td>
                    <td style="padding: 10px 0; border-bottom: 1px solid #f3f4f6; color: #333; font-size: 14px; font-weight: 500;">{$company}</td>
                </tr>
HTML;
        }

        $html .= "</table>";
    }

    // KI-Analyse Highlights
    $html .= <<<HTML
            <h2 style="color: #333; font-size: 18px; margin: 0 0 15px 0; border-bottom: 2px solid #FF7B29; padding-bottom: 10px;">
                🤖 KI-Analyse
            </h2>
HTML;

    // Lead-Qualität
    if (isset($analysis['lead_quality'])) {
        $html .= <<<HTML
            <div style="background: #f9fafb; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                <strong style="color: #333;">Lead-Qualität:</strong><br>
                <p style="margin: 5px 0 0 0; color: #666;">{$analysis['lead_quality']}</p>
            </div>
HTML;
    }

    // Key Insights
    if (!empty($analysis['key_insights'])) {
        $html .= <<<HTML
            <div style="margin-bottom: 15px;">
                <strong style="color: #333;">💡 Wichtigste Erkenntnisse:</strong>
                <ul style="margin: 5px 0; padding-left: 20px; color: #666;">
HTML;
        foreach (array_slice($analysis['key_insights'], 0, 3) as $insight) {
            $html .= "<li>" . htmlspecialchars($insight) . "</li>";
        }
        $html .= "</ul></div>";
    }

    // Nächste Schritte
    if (!empty($analysis['next_actions'])) {
        $html .= <<<HTML
            <div style="margin-bottom: 20px;">
                <strong style="color: #333;">🎯 Empfohlene nächste Schritte:</strong>
                <ol style="margin: 5px 0; padding-left: 20px; color: #666;">
HTML;
        foreach (array_slice($analysis['next_actions'], 0, 3) as $action) {
            $html .= "<li>" . htmlspecialchars($action) . "</li>";
        }
        $html .= "</ol></div>";
    }

    // Call-to-Action Button
    $html .= <<<HTML
            <div style="text-align: center; margin: 30px 0;">
                <a href="{$hubspotUrl}"
                   style="display: inline-block; background: linear-gradient(135deg, #FF7B29, #e66b24);
                          color: white; padding: 15px 40px; text-decoration: none; border-radius: 8px;
                          font-weight: 600; font-size: 16px;">
                    📊 In HubSpot öffnen
                </a>
            </div>

            <p style="text-align: center; color: #999; font-size: 13px; margin-top: 20px;">
                Eine Erinnerungsaufgabe wurde in HubSpot erstellt.
            </p>

        </div>

        <!-- Footer -->
        <div style="background: #f9fafb; padding: 30px; text-align: center; border-top: 1px solid #e5e7eb;">
            <p style="margin: 0; color: #999; font-size: 12px;">
                Diese E-Mail wurde automatisch generiert von Ihrem KI-Assistenten.<br>
                Session: {$conversation['session_id']}
            </p>
        </div>

    </div>
</body>
</html>
HTML;

    // E-Mail Headers
    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
    $headers[] = 'From: NOBA Experts KI-Chatbot <nobaexpertchatbot@gmail.com>';
    $headers[] = 'Reply-To: Jurak.Bahrambaek@noba-experts.de';
    $headers[] = 'X-Priority: ' . ($urgency === 'Sehr hoch' ? '1' : '2'); // Hohe Priorität bei dringenden Leads

    // E-Mail senden
    $success = mail($adminEmail, $subject, $html, implode("\r\n", $headers), '-f nobaexpertchatbot@gmail.com');

    error_log("📧 Admin-Benachrichtigung gesendet: " . ($success ? "Erfolg" : "Fehler") . " | Lead: {$name} | Urgency: {$urgency}");

    return $success;
}

/**
 * BATCH DELETE - Mehrere Konversationen auf einmal löschen
 */
function handleBatchDelete() {
    $input = json_decode(file_get_contents('php://input'), true);
    $session_ids = $input['session_ids'] ?? [];

    if (empty($session_ids) || !is_array($session_ids)) {
        http_response_code(400);
        die(json_encode(['error' => 'session_ids array required']));
    }

    $conversations = loadConversations();
    $before_count = count($conversations);

    // Filtere alle Session-IDs raus
    $conversations = array_filter($conversations, function($conv) use ($session_ids) {
        return !in_array($conv['session_id'], $session_ids);
    });

    $deleted_count = $before_count - count($conversations);

    saveConversations($conversations);

    error_log("🗑️ Batch-Delete: {$deleted_count} Konversationen gelöscht");

    echo json_encode([
        'success' => true,
        'deleted' => $deleted_count,
        'remaining' => count($conversations)
    ]);
}

/**
 * BATCH EMAIL - KI-Analysen mehrerer Leads per E-Mail versenden
 */
function handleBatchEmail() {
    $input = json_decode(file_get_contents('php://input'), true);
    $session_ids = $input['session_ids'] ?? [];

    if (empty($session_ids) || !is_array($session_ids)) {
        http_response_code(400);
        die(json_encode(['error' => 'session_ids array required']));
    }

    $conversations = loadConversations();
    $leads_data = [];

    // Sammle alle Leads und erstelle KI-Analysen
    foreach ($session_ids as $session_id) {
        $conv = array_values(array_filter($conversations, fn($c) => $c['session_id'] === $session_id))[0] ?? null;

        if (!$conv) continue;

        // KI-Analyse erstellen
        $analysis = analyzeLeadWithAI($conv);

        $leads_data[] = [
            'conversation' => $conv,
            'analysis' => $analysis
        ];
    }

    if (empty($leads_data)) {
        http_response_code(404);
        die(json_encode(['error' => 'Keine Leads gefunden']));
    }

    // E-Mail erstellen
    $adminEmail = 'Jurak.Bahrambaek@noba-experts.de';
    $subject = "📊 NOBA Experts - {count($leads_data)} Lead-Analysen";

    $html = buildBatchEmailHTML($leads_data);

    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
    $headers[] = 'From: NOBA Experts KI-Chatbot <nobaexpertchatbot@gmail.com>';
    $headers[] = 'Reply-To: Jurak.Bahrambaek@noba-experts.de';

    $success = mail($adminEmail, $subject, $html, implode("\r\n", $headers), '-f nobaexpertchatbot@gmail.com');

    error_log("📧 Batch-Email: " . count($leads_data) . " Analysen versendet - " . ($success ? "Erfolg" : "Fehler"));

    echo json_encode([
        'success' => $success,
        'count' => count($leads_data),
        'message' => $success ? "E-Mail erfolgreich versendet" : "E-Mail-Versand fehlgeschlagen"
    ]);
}

/**
 * Erstelle HTML für Batch-Email mit mehreren Lead-Analysen
 */
function buildBatchEmailHTML($leads_data) {
    $timestamp = date('d.m.Y H:i');

    $html = <<<HTML
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 800px; margin: 0 auto; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
            .lead-section { background: #f9f9f9; margin: 20px 0; padding: 20px; border-left: 4px solid #667eea; }
            .lead-header { font-size: 20px; font-weight: bold; margin-bottom: 10px; }
            .analysis-item { margin: 15px 0; }
            .analysis-title { font-weight: bold; color: #667eea; margin-bottom: 5px; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>📊 Lead-Analysen</h1>
            <p>{$timestamp}</p>
        </div>
    HTML;

    foreach ($leads_data as $idx => $lead) {
        $conv = $lead['conversation'];
        $analysis = $lead['analysis'];
        $data = $conv['extracted_data'];

        $name = $data['name'] ?? 'Unbekannt';
        $email = $data['email'] ?? 'Keine E-Mail';
        $phone = $data['phone'] ?? 'Keine Telefonnummer';
        $leadType = $data['lead_type'] === 'employer' ? '💼 Kunde' : '👔 Kandidat';
        $leadScore = $data['lead_score'] ?? 0;

        $html .= <<<HTML
        <div class="lead-section">
            <div class="lead-header">Lead #{($idx + 1)}: {$name} {$leadType}</div>
            <p><strong>E-Mail:</strong> {$email} | <strong>Telefon:</strong> {$phone} | <strong>Score:</strong> {$leadScore}/100</p>

            <div class="analysis-item">
                <div class="analysis-title">📊 Lead-Qualität</div>
                <p>{$analysis['lead_quality']}</p>
            </div>

            <div class="analysis-item">
                <div class="analysis-title">⚡ Dringlichkeit</div>
                <p>{$analysis['urgency_level']}</p>
            </div>

            <div class="analysis-item">
                <div class="analysis-title">💡 Top 3 Erkenntnisse</div>
                <ul>
        HTML;

        foreach (array_slice($analysis['key_insights'] ?? [], 0, 3) as $insight) {
            $html .= "<li>{$insight}</li>";
        }

        $html .= <<<HTML
                </ul>
            </div>

            <div class="analysis-item">
                <div class="analysis-title">🎯 Nächste Schritte</div>
                <ol>
        HTML;

        foreach (array_slice($analysis['next_actions'] ?? [], 0, 3) as $action) {
            $html .= "<li>{$action}</li>";
        }

        $html .= <<<HTML
                </ol>
            </div>
        </div>
        HTML;
    }

    $html .= <<<HTML
        <div class="footer">
            <p>🤖 Automatisch generiert vom NOBA Experts KI-Chatbot</p>
        </div>
    </body>
    </html>
    HTML;

    return $html;
}

/**
 * BATCH SYNC TO HUBSPOT - Mehrere Leads zu HubSpot synchronisieren (mit KI-Analyse)
 */
function handleBatchSyncHubSpot() {
    $input = json_decode(file_get_contents('php://input'), true);
    $session_ids = $input['session_ids'] ?? [];

    if (empty($session_ids) || !is_array($session_ids)) {
        http_response_code(400);
        die(json_encode(['error' => 'session_ids array required']));
    }

    if (!HUBSPOT_ACCESS_TOKEN) {
        http_response_code(500);
        die(json_encode(['error' => 'HubSpot nicht konfiguriert']));
    }

    $conversations = loadConversations();
    $results = [];
    $success_count = 0;
    $error_count = 0;

    foreach ($session_ids as $session_id) {
        $conv = array_values(array_filter($conversations, fn($c) => $c['session_id'] === $session_id))[0] ?? null;

        if (!$conv) {
            $results[] = [
                'session_id' => $session_id,
                'success' => false,
                'error' => 'Konversation nicht gefunden'
            ];
            $error_count++;
            continue;
        }

        // KI-Analyse erstellen
        $analysis = analyzeLeadWithAI($conv);

        // Zu HubSpot syncen (mit Analyse)
        $syncResult = syncAnalysisToHubSpot($conv, $analysis);

        if ($syncResult['success']) {
            $success_count++;
        } else {
            $error_count++;
        }

        $results[] = [
            'session_id' => $session_id,
            'success' => $syncResult['success'],
            'contact_id' => $syncResult['contact_id'] ?? null,
            'error' => $syncResult['message'] ?? null
        ];
    }

    error_log("🔄 Batch-HubSpot-Sync: {$success_count} erfolgreich, {$error_count} Fehler");

    echo json_encode([
        'success' => true,
        'total' => count($session_ids),
        'success_count' => $success_count,
        'error_count' => $error_count,
        'results' => $results
    ]);
}

function handleSendSummaryEmail() {
    $input = json_decode(file_get_contents('php://input'), true);
    $session_id = $input['session_id'] ?? '';
    $recipient_email = filter_var($input['recipient_email'] ?? '', FILTER_VALIDATE_EMAIL);

    if (!$session_id) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'session_id required']));
    }

    if (!$recipient_email) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Ungültige E-Mail-Adresse']));
    }

    // Load conversation
    $conversations = loadConversations();
    $conv = array_values(array_filter($conversations, fn($c) => $c['session_id'] === $session_id))[0] ?? null;

    if (!$conv) {
        http_response_code(404);
        die(json_encode(['success' => false, 'message' => 'Konversation nicht gefunden']));
    }

    // Generate email HTML
    $messageCount = count($conv['messages'] ?? []);
    $timestamp = date('d.m.Y H:i', strtotime($conv['timestamp']));
    $leadScore = $conv['extracted_data']['lead_score'] ?? 0;
    $leadScoreColor = $leadScore >= 70 ? '#10b981' : ($leadScore >= 40 ? '#f59e0b' : '#ef4444');

    $emailHTML = generateSummaryEmailHTML($conv, $session_id, $timestamp, $messageCount, $leadScore, $leadScoreColor);

    // Send email using PHP mail()
    $subject = "Chat-Zusammenfassung - NOBA Experts (Session: " . substr($session_id, 0, 8) . "...)";
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: NOBA Experts <noreply@noba-experts.de>',
        'Reply-To: info@noba-experts.de',
        'X-Mailer: PHP/' . phpversion()
    ];

    $success = mail($recipient_email, $subject, $emailHTML, implode("\r\n", $headers));

    if ($success) {
        error_log("✉️ E-Mail gesendet an {$recipient_email} für Session {$session_id}");
        echo json_encode([
            'success' => true,
            'message' => 'E-Mail erfolgreich gesendet'
        ]);
    } else {
        error_log("❌ E-Mail-Versand fehlgeschlagen an {$recipient_email}");
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'E-Mail konnte nicht gesendet werden'
        ]);
    }
}

function generateSummaryEmailHTML($conv, $sessionId, $timestamp, $messageCount, $leadScore, $leadScoreColor) {
    $html = <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Chat-Zusammenfassung - NOBA Experts</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f9fafb;">
    <div style="max-width: 600px; margin: 0 auto; background: white;">
        <!-- Header -->
        <div style="background: linear-gradient(135deg, #FF7B29, #e66b24); padding: 30px; text-align: center;">
            <h1 style="margin: 0; color: white; font-size: 24px;">💬 Chat-Zusammenfassung</h1>
            <p style="margin: 10px 0 0 0; color: rgba(255,255,255,0.9); font-size: 14px;">NOBA Experts KI-Berater</p>
        </div>

        <!-- Content -->
        <div style="padding: 30px;">
            <!-- Info Box -->
            <div style="background: #f9fafb; border-left: 4px solid #FF7B29; padding: 20px; margin-bottom: 30px;">
                <p style="margin: 0; color: #666; font-size: 14px;">
                    <strong style="color: #333;">Session-ID:</strong> {$sessionId}<br>
                    <strong style="color: #333;">Datum:</strong> {$timestamp}<br>
                    <strong style="color: #333;">Nachrichten:</strong> {$messageCount}
HTML;

    if ($leadScore > 0) {
        $html .= <<<HTML
                    <br><strong style="color: #333;">Lead-Qualität:</strong>
                    <span style="background: {$leadScoreColor}; color: white; padding: 4px 12px; border-radius: 12px; font-weight: 600; font-size: 13px;">{$leadScore} Punkte</span>
HTML;
    }

    $html .= <<<HTML
                </p>
            </div>

            <!-- Extracted Data -->
HTML;

    $extractedData = $conv['extracted_data'] ?? [];
    if (!empty($extractedData['name']) || !empty($extractedData['email']) || !empty($extractedData['phone'])) {
        $html .= '<div style="margin-bottom: 30px;"><h2 style="color: #333; font-size: 18px; margin-bottom: 15px;">📋 Kontaktdaten</h2><ul style="list-style: none; padding: 0;">';

        if (!empty($extractedData['name'])) {
            $html .= '<li style="padding: 8px 0; color: #666;"><strong style="color: #333;">Name:</strong> ' . htmlspecialchars($extractedData['name']) . '</li>';
        }
        if (!empty($extractedData['email'])) {
            $html .= '<li style="padding: 8px 0; color: #666;"><strong style="color: #333;">E-Mail:</strong> ' . htmlspecialchars($extractedData['email']) . '</li>';
        }
        if (!empty($extractedData['phone'])) {
            $html .= '<li style="padding: 8px 0; color: #666;"><strong style="color: #333;">Telefon:</strong> ' . htmlspecialchars($extractedData['phone']) . '</li>';
        }

        $html .= '</ul></div>';
    }

    // Chat messages
    $html .= '<div style="margin-bottom: 30px;"><h2 style="color: #333; font-size: 18px; margin-bottom: 15px;">💬 Chat-Verlauf</h2>';

    foreach (($conv['messages'] ?? []) as $msg) {
        $isBot = ($msg['role'] ?? '') === 'bot';
        $bgColor = $isBot ? '#f3f4f6' : '#FF7B29';
        $textColor = $isBot ? '#333' : 'white';
        $align = $isBot ? 'left' : 'right';

        $html .= <<<HTML
            <div style="margin-bottom: 15px; text-align: {$align};">
                <div style="display: inline-block; max-width: 80%; background: {$bgColor}; color: {$textColor}; padding: 12px 16px; border-radius: 12px; text-align: left;">
                    {$msg['text']}
                </div>
            </div>
HTML;
    }

    $html .= <<<HTML
            </div>

            <!-- Footer -->
            <div style="text-align: center; padding-top: 30px; border-top: 1px solid #e5e7eb;">
                <p style="margin: 0; color: #999; font-size: 12px;">
                    Diese E-Mail wurde automatisch vom NOBA Experts Admin Dashboard generiert.
                </p>
            </div>
        </div>
    </div>
</body>
</html>
HTML;

    return $html;
}

// ========================================
// VAKANZEN & KANDIDATENPROFILE MANAGEMENT
// ========================================

/**
 * Lade Vakanzen aus JSON-Datei
 */
function loadVacancies() {
    $file = dirname(__DIR__) . '/vacancies.json';
    if (!file_exists($file)) return [];
    $data = json_decode(file_get_contents($file), true);
    return $data ?? [];
}

/**
 * Speichere Vakanzen in JSON-Datei
 */
function saveVacancies($vacancies) {
    $file = dirname(__DIR__) . '/vacancies.json';
    file_put_contents($file, json_encode($vacancies, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Lade Kandidatenprofile aus JSON-Datei
 */
function loadCandidates() {
    $file = dirname(__DIR__) . '/candidate-profiles.json';
    if (!file_exists($file)) return [];
    $data = json_decode(file_get_contents($file), true);
    return $data ?? [];
}

/**
 * Speichere Kandidatenprofile in JSON-Datei
 */
function saveCandidates($candidates) {
    $file = dirname(__DIR__) . '/candidate-profiles.json';
    file_put_contents($file, json_encode($candidates, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Lade Projekte aus JSON-Datei
 */
function loadProjects() {
    $file = dirname(__DIR__) . '/projects.json';
    if (!file_exists($file)) return [];
    $data = json_decode(file_get_contents($file), true);
    return $data ?? [];
}

/**
 * Speichere Projekte in JSON-Datei
 */
function saveProjects($projects) {
    $file = dirname(__DIR__) . '/projects.json';
    file_put_contents($file, json_encode($projects, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Extrahiere Text aus PDF-Datei
 */
function extractTextFromPDF($filepath) {
    // Methode 1: pdftotext (wenn auf Server verfügbar)
    $output = shell_exec("pdftotext " . escapeshellarg($filepath) . " -");
    if ($output) return $output;

    // Methode 2: Fallback - Einfache PDF-Textextraktion
    $content = file_get_contents($filepath);
    $text = '';

    // Sehr einfache PDF-Textextraktion (funktioniert nicht bei allen PDFs)
    if (preg_match_all('/\(([^)]+)\)/i', $content, $matches)) {
        $text = implode(' ', $matches[1]);
    }

    return $text ?: 'Text konnte nicht extrahiert werden. Bitte manuelle Eingabe nutzen.';
}

/**
 * Extrahiere Text aus DOCX-Datei
 */
function extractTextFromDOCX($filepath) {
    $zip = new ZipArchive;
    $text = '';

    if ($zip->open($filepath) === TRUE) {
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml) {
            $xml = str_replace('</w:p>', "\n", $xml);
            $text = strip_tags($xml);
        }
    }

    return $text ?: 'Text konnte nicht extrahiert werden. Bitte manuelle Eingabe nutzen.';
}

/**
 * KI-basierte Anonymisierung und Datenextraktion für Stellenbeschreibungen
 * DSGVO-konform: Entfernt Firmennamen, Kontaktdaten, spezifische Standorte
 */
function anonymizeAndExtractVacancy($text) {
    $api_key = 'AIzaSyBtwnfTYAJgtJDSU7Lp5C8s5Dnw6PUYP2A';
    $model = 'gemini-2.0-flash-exp';

    $prompt = "Du bist ein DSGVO-Experte für IT-Recruiting. Analysiere diese Stellenbeschreibung und erstelle eine ANONYMISIERTE Version.

**ORIGINAL-TEXT:**
$text

**AUFGABE:**
1. **ANONYMISIERUNG (DSGVO-konform):**
   - Entferne ALLE Firmennamen, Markennamen, Produktnamen
   - Ersetze spezifische Standorte durch allgemeine Regionen (z.B. \"München\" → \"Raum München\", \"Berlin-Mitte\" → \"Berlin\")
   - Entferne Kontaktdaten (E-Mail, Telefon, URLs)
   - Entferne firmenspezifische Details (Gründungsjahr, Mitarbeiterzahl, etc.)
   - Behalte nur: Position, Skills, Anforderungen, Aufgaben, Benefits (allgemein formuliert)

2. **STRUKTURIERTE DATEN EXTRAHIEREN:**
   - Position/Titel
   - Required Skills (als Array)
   - Nice-to-have Skills (als Array)
   - Erfahrungslevel (Junior/Mid/Senior/Lead)
   - Standort (nur Region, z.B. \"Remote\", \"Berlin\", \"Raum München\")
   - Gehaltsrange (falls erwähnt)
   - Vertragsart (Festanstellung/Freelance/Hybrid)
   - Remote-Möglichkeit (Ja/Nein/Hybrid)

**ANTWORT-FORMAT (nur JSON):**
```json
{
  \"anonymized_description\": \"Vollständig anonymisierte Stellenbeschreibung als Fließtext\",
  \"title\": \"Positionstitel\",
  \"required_skills\": [\"Skill1\", \"Skill2\", ...],
  \"nice_to_have_skills\": [\"Skill1\", \"Skill2\", ...],
  \"experience_level\": \"Junior/Mid/Senior/Lead\",
  \"location\": \"Region ohne Details\",
  \"salary_range\": \"z.B. 60.000-80.000 EUR oder null\",
  \"employment_type\": \"Festanstellung/Freelance/Hybrid\",
  \"remote_option\": \"Ja/Nein/Hybrid\",
  \"key_responsibilities\": [\"Aufgabe 1\", \"Aufgabe 2\", ...]
}
```

WICHTIG: Antworte NUR mit dem JSON-Objekt!";

    $url = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key=$api_key";

    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.2,
            'maxOutputTokens' => 2000,
        ],
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        error_log("Gemini API Error (Vacancy): HTTP $http_code - $response");
        return null;
    }

    $result = json_decode($response, true);
    $ai_text = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';

    // JSON extrahieren
    $ai_text = trim($ai_text);
    $ai_text = preg_replace('/^```json\s*/s', '', $ai_text);
    $ai_text = preg_replace('/\s*```$/s', '', $ai_text);

    $extracted = json_decode($ai_text, true);

    if (!$extracted) {
        error_log("Failed to parse AI response (Vacancy): $ai_text");
        return null;
    }

    return $extracted;
}

/**
 * KI-basierte Anonymisierung und Datenextraktion für CVs
 * DSGVO-konform: Entfernt Namen, Adressen, Kontaktdaten, spezifische Firmennamen
 */
function anonymizeAndExtractCandidate($text) {
    $api_key = 'AIzaSyBtwnfTYAJgtJDSU7Lp5C8s5Dnw6PUYP2A';
    $model = 'gemini-2.0-flash-exp';

    $prompt = "Du bist ein DSGVO-Experte für IT-Recruiting. Analysiere diesen Lebenslauf und erstelle eine ANONYMISIERTE Version.

**ORIGINAL-TEXT:**
$text

**AUFGABE:**
1. **ANONYMISIERUNG (DSGVO-konform):**
   - Entferne ALLE persönlichen Daten: Name, Geburtsdatum, Adresse, E-Mail, Telefon
   - Entferne spezifische Firmennamen (ersetze durch \"Großes Tech-Unternehmen\", \"Mittelständischer Automobilzulieferer\", etc.)
   - Entferne Namen von Universitäten/Schulen (ersetze durch \"Technische Universität\", \"Fachhochschule\", etc.)
   - Behalte nur: Skills, Technologien, Erfahrungsjahre, Branchen, grobe Standort-Region
   - Zeiträume können bleiben (z.B. \"2020-2023\"), aber ohne spezifische Arbeitgeber

2. **STRUKTURIERTE DATEN EXTRAHIEREN:**
   - Skills/Technologien (als Array)
   - Erfahrungsjahre (Gesamt)
   - Seniority-Level (Junior/Mid/Senior/Lead)
   - Branchen-Erfahrung
   - Standort (nur Region)
   - Verfügbarkeit (Vollzeit/Teilzeit/Freelance)
   - Sprachkenntnisse

**ANTWORT-FORMAT (nur JSON):**
```json
{
  \"anonymized_profile\": \"Vollständig anonymisierter Lebenslauf als Fließtext (max. 500 Wörter)\",
  \"skills\": [\"Skill1\", \"Skill2\", ...],
  \"experience_years\": 5,
  \"seniority_level\": \"Junior/Mid/Senior/Lead\",
  \"industries\": [\"Branche1\", \"Branche2\", ...],
  \"location\": \"Region ohne Details (z.B. 'Berlin', 'Raum München', 'Remote')\",
  \"availability\": \"Vollzeit/Teilzeit/Freelance\",
  \"languages\": [\"Deutsch (Muttersprache)\", \"Englisch (Fließend)\", ...],
  \"key_qualifications\": [\"Qualifikation 1\", \"Qualifikation 2\", ...]
}
```

WICHTIG: Antworte NUR mit dem JSON-Objekt!";

    $url = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key=$api_key";

    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.2,
            'maxOutputTokens' => 2000,
        ],
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        error_log("Gemini API Error (Candidate): HTTP $http_code - $response");
        return null;
    }

    $result = json_decode($response, true);
    $ai_text = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';

    // JSON extrahieren
    $ai_text = trim($ai_text);
    $ai_text = preg_replace('/^```json\s*/s', '', $ai_text);
    $ai_text = preg_replace('/\s*```$/s', '', $ai_text);

    $extracted = json_decode($ai_text, true);

    if (!$extracted) {
        error_log("Failed to parse AI response (Candidate): $ai_text");
        return null;
    }

    return $extracted;
}

// ===== VACANCY HANDLERS =====

function handleUploadVacancy() {
    // Unterstütze sowohl File-Upload als auch direkten Text-Input
    $rawText = $_POST['raw_text'] ?? '';
    $file = $_FILES['file'] ?? null;

    $extractedText = '';

    // Option 1: File Upload
    if ($file && $file['error'] === UPLOAD_ERR_OK) {
        $filename = $file['name'];
        $tmpPath = $file['tmp_name'];
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if ($extension === 'pdf') {
            $extractedText = extractTextFromPDF($tmpPath);
        } elseif ($extension === 'docx') {
            $extractedText = extractTextFromDOCX($tmpPath);
        } elseif (in_array($extension, ['txt', 'doc'])) {
            $extractedText = file_get_contents($tmpPath);
        } else {
            http_response_code(400);
            die(json_encode(['error' => 'Unsupported file type. Please upload PDF, DOCX, or TXT.']));
        }

        // Original-Dateiname speichern
        $originalFilename = $filename;
    }
    // Option 2: Direkter Text-Input
    elseif ($rawText) {
        $extractedText = $rawText;
        $originalFilename = 'Manual Input';
    }
    else {
        http_response_code(400);
        die(json_encode(['error' => 'No file or text provided']));
    }

    // KI-Anonymisierung und Datenextraktion
    $vacancyData = anonymizeAndExtractVacancy($extractedText);

    if (!$vacancyData) {
        http_response_code(500);
        die(json_encode(['error' => 'AI anonymization failed']));
    }

    // Vakanz-Objekt erstellen
    $vacancy = [
        'id' => uniqid('vac_', true),
        'title' => $vacancyData['title'] ?? 'Unbekannte Position',
        'anonymized_description' => $vacancyData['anonymized_description'] ?? '',
        'required_skills' => $vacancyData['required_skills'] ?? [],
        'nice_to_have_skills' => $vacancyData['nice_to_have_skills'] ?? [],
        'experience_level' => $vacancyData['experience_level'] ?? 'Mid',
        'location' => $vacancyData['location'] ?? 'Remote',
        'salary_range' => $vacancyData['salary_range'] ?? null,
        'employment_type' => $vacancyData['employment_type'] ?? 'Festanstellung',
        'remote_option' => $vacancyData['remote_option'] ?? 'Hybrid',
        'key_responsibilities' => $vacancyData['key_responsibilities'] ?? [],
        'created_at' => date('c'),
        'updated_at' => date('c'),
        'original_filename' => $originalFilename ?? 'Manual Input',
        'status' => 'active', // active, inactive, filled
    ];

    // Zu Liste hinzufügen und speichern
    $vacancies = loadVacancies();
    $vacancies[] = $vacancy;
    saveVacancies($vacancies);

    error_log("✅ Neue Vakanz erstellt: {$vacancy['title']} (ID: {$vacancy['id']})");

    echo json_encode([
        'success' => true,
        'vacancy' => $vacancy,
        'message' => 'Vakanz erfolgreich anonymisiert und gespeichert'
    ]);
}

function handleGetVacancies() {
    $vacancies = loadVacancies();

    // Filter-Parameter
    $status = $_GET['status'] ?? 'all';
    $search = $_GET['search'] ?? '';

    // Filtern
    $filtered = array_filter($vacancies, function($vac) use ($status, $search) {
        if ($status !== 'all' && ($vac['status'] ?? 'active') !== $status) {
            return false;
        }

        if ($search) {
            $searchable = json_encode($vac, JSON_UNESCAPED_UNICODE);
            if (stripos($searchable, $search) === false) {
                return false;
            }
        }

        return true;
    });

    // Sortieren (neueste zuerst)
    usort($filtered, fn($a, $b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));

    echo json_encode([
        'success' => true,
        'data' => array_values($filtered),
        'total' => count($filtered)
    ]);
}

function handleUpdateVacancy() {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? '';
    $updates = $input['updates'] ?? [];

    if (!$id) {
        http_response_code(400);
        die(json_encode(['error' => 'Missing vacancy ID']));
    }

    $vacancies = loadVacancies();
    $found = false;

    foreach ($vacancies as &$vac) {
        if ($vac['id'] === $id) {
            // Merge updates
            foreach ($updates as $key => $value) {
                $vac[$key] = $value;
            }
            $vac['updated_at'] = date('c');
            $found = true;
            break;
        }
    }

    if (!$found) {
        http_response_code(404);
        die(json_encode(['error' => 'Vacancy not found']));
    }

    saveVacancies($vacancies);

    error_log("✏️ Vakanz aktualisiert: ID {$id}");

    echo json_encode([
        'success' => true,
        'message' => 'Vakanz erfolgreich aktualisiert'
    ]);
}

function handleDeleteVacancy() {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? $_GET['id'] ?? '';

    if (!$id) {
        http_response_code(400);
        die(json_encode(['error' => 'Missing vacancy ID']));
    }

    $vacancies = loadVacancies();
    $filtered = array_filter($vacancies, fn($vac) => $vac['id'] !== $id);

    if (count($filtered) === count($vacancies)) {
        http_response_code(404);
        die(json_encode(['error' => 'Vacancy not found']));
    }

    saveVacancies(array_values($filtered));

    error_log("🗑️ Vakanz gelöscht: ID {$id}");

    echo json_encode([
        'success' => true,
        'message' => 'Vakanz erfolgreich gelöscht'
    ]);
}

// ===== CANDIDATE HANDLERS =====

function handleUploadCandidate() {
    // Unterstütze sowohl File-Upload als auch direkten Text-Input
    $rawText = $_POST['raw_text'] ?? '';
    $file = $_FILES['file'] ?? null;

    $extractedText = '';

    // Option 1: File Upload
    if ($file && $file['error'] === UPLOAD_ERR_OK) {
        $filename = $file['name'];
        $tmpPath = $file['tmp_name'];
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if ($extension === 'pdf') {
            $extractedText = extractTextFromPDF($tmpPath);
        } elseif ($extension === 'docx') {
            $extractedText = extractTextFromDOCX($tmpPath);
        } elseif (in_array($extension, ['txt', 'doc'])) {
            $extractedText = file_get_contents($tmpPath);
        } else {
            http_response_code(400);
            die(json_encode(['error' => 'Unsupported file type. Please upload PDF, DOCX, or TXT.']));
        }

        $originalFilename = $filename;
    }
    // Option 2: Direkter Text-Input
    elseif ($rawText) {
        $extractedText = $rawText;
        $originalFilename = 'Manual Input';
    }
    else {
        http_response_code(400);
        die(json_encode(['error' => 'No file or text provided']));
    }

    // KI-Anonymisierung und Datenextraktion
    $candidateData = anonymizeAndExtractCandidate($extractedText);

    if (!$candidateData) {
        http_response_code(500);
        die(json_encode(['error' => 'AI anonymization failed']));
    }

    // Kandidaten-Objekt erstellen
    $candidate = [
        'id' => uniqid('cand_', true),
        'anonymized_profile' => $candidateData['anonymized_profile'] ?? '',
        'skills' => $candidateData['skills'] ?? [],
        'experience_years' => $candidateData['experience_years'] ?? 0,
        'seniority_level' => $candidateData['seniority_level'] ?? 'Mid',
        'industries' => $candidateData['industries'] ?? [],
        'location' => $candidateData['location'] ?? 'Remote',
        'availability' => $candidateData['availability'] ?? 'Vollzeit',
        'languages' => $candidateData['languages'] ?? [],
        'key_qualifications' => $candidateData['key_qualifications'] ?? [],
        'created_at' => date('c'),
        'updated_at' => date('c'),
        'original_filename' => $originalFilename ?? 'Manual Input',
        'status' => 'available', // available, placed, inactive
    ];

    // Zu Liste hinzufügen und speichern
    $candidates = loadCandidates();
    $candidates[] = $candidate;
    saveCandidates($candidates);

    error_log("✅ Neues Kandidatenprofil erstellt: {$candidate['seniority_level']} (ID: {$candidate['id']})");

    echo json_encode([
        'success' => true,
        'candidate' => $candidate,
        'message' => 'Kandidatenprofil erfolgreich anonymisiert und gespeichert'
    ]);
}

function handleGetCandidates() {
    $candidates = loadCandidates();

    // Filter-Parameter
    $status = $_GET['status'] ?? 'all';
    $search = $_GET['search'] ?? '';

    // Filtern
    $filtered = array_filter($candidates, function($cand) use ($status, $search) {
        if ($status !== 'all' && ($cand['status'] ?? 'available') !== $status) {
            return false;
        }

        if ($search) {
            $searchable = json_encode($cand, JSON_UNESCAPED_UNICODE);
            if (stripos($searchable, $search) === false) {
                return false;
            }
        }

        return true;
    });

    // Sortieren (neueste zuerst)
    usort($filtered, fn($a, $b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));

    echo json_encode([
        'success' => true,
        'data' => array_values($filtered),
        'total' => count($filtered)
    ]);
}

function handleUpdateCandidate() {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? '';
    $updates = $input['updates'] ?? [];

    if (!$id) {
        http_response_code(400);
        die(json_encode(['error' => 'Missing candidate ID']));
    }

    $candidates = loadCandidates();
    $found = false;

    foreach ($candidates as &$cand) {
        if ($cand['id'] === $id) {
            // Merge updates
            foreach ($updates as $key => $value) {
                $cand[$key] = $value;
            }
            $cand['updated_at'] = date('c');
            $found = true;
            break;
        }
    }

    if (!$found) {
        http_response_code(404);
        die(json_encode(['error' => 'Candidate not found']));
    }

    saveCandidates($candidates);

    error_log("✏️ Kandidatenprofil aktualisiert: ID {$id}");

    echo json_encode([
        'success' => true,
        'message' => 'Kandidatenprofil erfolgreich aktualisiert'
    ]);
}

function handleDeleteCandidate() {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? $_GET['id'] ?? '';

    if (!$id) {
        http_response_code(400);
        die(json_encode(['error' => 'Missing candidate ID']));
    }

    $candidates = loadCandidates();
    $filtered = array_filter($candidates, fn($cand) => $cand['id'] !== $id);

    if (count($filtered) === count($candidates)) {
        http_response_code(404);
        die(json_encode(['error' => 'Candidate not found']));
    }

    saveCandidates(array_values($filtered));

    error_log("🗑️ Kandidatenprofil gelöscht: ID {$id}");

    echo json_encode([
        'success' => true,
        'message' => 'Kandidatenprofil erfolgreich gelöscht'
    ]);
}

// ========================================
// MATCHING & INTEREST TRACKING
// ========================================

/**
 * Lade Matches aus JSON-Datei
 */
function loadMatches() {
    $file = dirname(__DIR__) . '/matches.json';
    if (!file_exists($file)) return [];
    $data = json_decode(file_get_contents($file), true);
    return $data ?? [];
}

/**
 * Speichere Matches in JSON-Datei
 */
function saveMatches($matches) {
    $file = dirname(__DIR__) . '/matches.json';
    file_put_contents($file, json_encode($matches, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Speichere Interesse (von Kandidat oder Kunde)
 * 
 * Typen:
 * - candidate_to_vacancy: Kandidat interessiert sich für Stelle
 * - customer_to_candidate: Kunde interessiert sich für Kandidaten
 */
function handleSaveInterest() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $type = $input['type'] ?? ''; // 'candidate_to_vacancy' oder 'customer_to_candidate'
    $user_email = $input['user_email'] ?? '';
    $user_name = $input['user_name'] ?? 'Unbekannt';
    $session_id = $input['session_id'] ?? '';
    $target_id = $input['target_id'] ?? ''; // vacancy_id oder candidate_id
    $target_title = $input['target_title'] ?? '';
    $message = $input['message'] ?? ''; // Optional: User-Message die Interesse zeigt
    
    if (!$type || !$target_id) {
        http_response_code(400);
        die(json_encode(['error' => 'Missing required fields']));
    }
    
    // Match-Objekt erstellen
    $match = [
        'id' => uniqid('match_', true),
        'type' => $type,
        'user_email' => $user_email,
        'user_name' => $user_name,
        'session_id' => $session_id,
        'target_id' => $target_id,
        'target_title' => $target_title,
        'message' => $message,
        'created_at' => date('c'),
        'status' => 'new', // new, contacted, rejected, hired
    ];
    
    // Zu Liste hinzufügen
    $matches = loadMatches();
    $matches[] = $match;
    saveMatches($matches);
    
    // E-Mail an Admin senden
    $admin_email = 'jurak.bahrambaek@noba-experts.de';
    
    if ($type === 'candidate_to_vacancy') {
        $subject = "🎯 Neues Interesse: Kandidat → Stelle";
        $body = "Ein Kandidat hat Interesse an einer Stelle gezeigt!\n\n";
        $body .= "Kandidat: $user_name ($user_email)\n";
        $body .= "Stelle: $target_title\n";
        $body .= "Zeit: " . date('d.m.Y H:i') . "\n";
        if ($message) $body .= "Nachricht: $message\n";
        $body .= "\nZum Dashboard: https://chatbot.noba-experts.de/admin/";
    } else {
        $subject = "🎯 Neues Interesse: Kunde → Kandidat";
        $body = "Ein Kunde hat Interesse an einem Kandidaten gezeigt!\n\n";
        $body .= "Kunde: $user_name ($user_email)\n";
        $body .= "Kandidat: $target_title\n";
        $body .= "Zeit: " . date('d.m.Y H:i') . "\n";
        if ($message) $body .= "Nachricht: $message\n";
        $body .= "\nZum Dashboard: https://chatbot.noba-experts.de/admin/";
    }
    
    // E-Mail senden (async)
    @mail($admin_email, $subject, $body, "From: noreply@noba-experts.de\r\n");
    
    error_log("✅ Neuer Match gespeichert: $type - {$match['id']}");
    
    echo json_encode([
        'success' => true,
        'match_id' => $match['id'],
        'message' => 'Interesse gespeichert! Wir melden uns zeitnah.'
    ]);
}

/**
 * Alle Matches abrufen (für Admin Dashboard)
 */
function handleGetMatches() {
    $matches = loadMatches();
    
    // Filter-Parameter
    $type = $_GET['type'] ?? 'all'; // all, candidate_to_vacancy, customer_to_candidate
    $status = $_GET['status'] ?? 'all'; // all, new, contacted, rejected, hired
    
    // Filtern
    $filtered = array_filter($matches, function($match) use ($type, $status) {
        if ($type !== 'all' && ($match['type'] ?? '') !== $type) {
            return false;
        }
        if ($status !== 'all' && ($match['status'] ?? 'new') !== $status) {
            return false;
        }
        return true;
    });
    
    // Sortieren (neueste zuerst)
    usort($filtered, fn($a, $b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));
    
    echo json_encode([
        'success' => true,
        'data' => array_values($filtered),
        'total' => count($filtered)
    ]);
}

/**
 * Match löschen
 */
function handleDeleteMatch() {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? $_GET['id'] ?? '';
    
    if (!$id) {
        http_response_code(400);
        die(json_encode(['error' => 'Missing match ID']));
    }
    
    $matches = loadMatches();
    $filtered = array_filter($matches, fn($m) => $m['id'] !== $id);
    
    if (count($filtered) === count($matches)) {
        http_response_code(404);
        die(json_encode(['error' => 'Match not found']));
    }
    
    saveMatches(array_values($filtered));
    
    error_log("🗑️ Match gelöscht: ID {$id}");
    
    echo json_encode([
        'success' => true,
        'message' => 'Match erfolgreich gelöscht'
    ]);
}

// ========================================
// PROJEKT-ANALYSE HANDLERS
// ========================================

/**
 * KI-basierte Projektanalyse
 * Analysiert Lastenheft/Projektbeschreibung und extrahiert:
 * - Benötigte Rollen
 * - Skills pro Rolle
 * - Zeitaufwand & Kosten
 * - Passende Kandidaten aus DB
 */
function analyzeProjectWithAI($text, $project_name = '') {
    $api_key = 'AIzaSyBtwnfTYAJgtJDSU7Lp5C8s5Dnw6PUYP2A';
    $model = 'gemini-2.0-flash-exp';
    
    $prompt = "Du bist ein Experte für IT-Projektmanagement und Ressourcenplanung. Analysiere folgendes Projekt/Lastenheft und erstelle eine detaillierte Personalbedarfsanalyse.

PROJEKT-BESCHREIBUNG:
$text

AUFGABE:
1. PROJEKT-ÜBERBLICK:
   - Projektziel & Beschreibung (kurz zusammengefasst)
   - Projektdauer (geschätzt in Monaten)
   - Technologie-Stack
   - Komplexität (niedrig/mittel/hoch/sehr hoch)

2. BENÖTIGTE ROLLEN/POSITIONEN:
   Für jede benötigte Rolle extrahiere:
   - Rollenbezeichnung (z.B. \"Senior Backend Developer\", \"DevOps Engineer\")
   - Anzahl benötigter Personen
   - Erforderliche Skills (als Array)
   - Seniority-Level (Junior/Mid/Senior/Lead)
   - Zeitaufwand in Personentagen (PT) oder Personenmonaten (PM)
   - Geschätzte Kosten pro Rolle (basierend auf üblichen Marktpreisen in EUR)

3. GESAMTKALKULATION:
   - Gesamte Personentage/Personenmonate
   - Gesamtkosten (Range: Min-Max in EUR)
   - Kritische Skills (die schwer zu finden sind)

ANTWORT-FORMAT (nur JSON):
```json
{
  \"project_summary\": {
    \"name\": \"$project_name\",
    \"description\": \"Kurze Zusammenfassung des Projekts\",
    \"duration_months\": 6,
    \"tech_stack\": [\"React\", \"Node.js\", \"AWS\", ...],
    \"complexity\": \"hoch\"
  },
  \"required_roles\": [
    {
      \"role\": \"Senior Backend Developer\",
      \"count\": 2,
      \"skills\": [\"Node.js\", \"PostgreSQL\", \"Docker\", ...],
      \"seniority_level\": \"Senior\",
      \"effort_days\": 120,
      \"estimated_cost_eur\": 72000,
      \"description\": \"Kurze Beschreibung der Aufgaben\"
    },
    ...
  ],
  \"total_cost\": {
    \"min_eur\": 200000,
    \"max_eur\": 300000,
    \"total_person_months\": 15
  },
  \"critical_skills\": [\"Kubernetes\", \"Machine Learning\", ...],
  \"recommendations\": \"Weitere Empfehlungen für das Projekt\"
}
```

WICHTIG: Antworte NUR mit dem JSON-Objekt! Keine Markdown-Formatierung.";

    $url = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key=$api_key";
    
    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.3,
            'maxOutputTokens' => 4000,
        ],
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        error_log("Gemini API Error (Project): HTTP $http_code - $response");
        return null;
    }
    
    $result = json_decode($response, true);
    $ai_text = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
    
    // JSON extrahieren
    $ai_text = trim($ai_text);
    $ai_text = preg_replace('/^```json\s*/s', '', $ai_text);
    $ai_text = preg_replace('/\s*```$/s', '', $ai_text);
    
    $extracted = json_decode($ai_text, true);
    
    if (!$extracted) {
        error_log("Failed to parse AI response (Project): $ai_text");
        return null;
    }
    
    return $extracted;
}

/**
 * Finde passende Kandidaten für Projekt-Rollen
 */
function findCandidatesForProject($required_roles) {
    $candidates = loadCandidates();
    $matched_candidates = [];
    
    foreach ($required_roles as $role) {
        $role_skills = array_map('strtolower', $role['skills'] ?? []);
        $role_matches = [];
        
        foreach ($candidates as $candidate) {
            if (($candidate['status'] ?? 'available') !== 'available') {
                continue;
            }
            
            $candidate_skills = array_map('strtolower', $candidate['skills'] ?? []);
            $score = 0;
            
            // Skill-Matching
            foreach ($role_skills as $req_skill) {
                foreach ($candidate_skills as $cand_skill) {
                    if (stripos($cand_skill, $req_skill) !== false || stripos($req_skill, $cand_skill) !== false) {
                        $score += 10;
                    }
                }
            }
            
            // Seniority-Matching
            $role_seniority = strtolower($role['seniority_level'] ?? '');
            $cand_seniority = strtolower($candidate['seniority_level'] ?? '');
            if ($role_seniority === $cand_seniority) {
                $score += 15;
            }
            
            if ($score > 0) {
                $role_matches[] = [
                    'candidate' => $candidate,
                    'score' => $score,
                    'matching_skills' => array_intersect($role_skills, $candidate_skills)
                ];
            }
        }
        
        // Sortiere nach Score
        usort($role_matches, fn($a, $b) => $b['score'] <=> $a['score']);
        
        $matched_candidates[$role['role']] = array_slice($role_matches, 0, 5);
    }
    
    return $matched_candidates;
}

function handleUploadProject() {
    $rawText = $_POST['raw_text'] ?? '';
    $projectName = $_POST['project_name'] ?? '';
    $file = $_FILES['file'] ?? null;
    
    $extractedText = '';
    
    if ($file && $file['error'] === UPLOAD_ERR_OK) {
        $filename = $file['name'];
        $tmpPath = $file['tmp_name'];
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if ($extension === 'pdf') {
            $extractedText = extractTextFromPDF($tmpPath);
        } elseif ($extension === 'docx') {
            $extractedText = extractTextFromDOCX($tmpPath);
        } elseif (in_array($extension, ['txt', 'doc'])) {
            $extractedText = file_get_contents($tmpPath);
        } else {
            http_response_code(400);
            die(json_encode(['error' => 'Unsupported file type. Please upload PDF, DOCX, or TXT.']));
        }
        
        $originalFilename = $filename;
    } elseif ($rawText) {
        $extractedText = $rawText;
        $originalFilename = 'Manual Input';
    } else {
        http_response_code(400);
        die(json_encode(['error' => 'No file or text provided']));
    }
    
    // KI-Analyse
    $analysisData = analyzeProjectWithAI($extractedText, $projectName);
    
    if (!$analysisData) {
        http_response_code(500);
        die(json_encode(['error' => 'AI analysis failed']));
    }
    
    // Finde passende Kandidaten für jede Rolle
    $matched_candidates = findCandidatesForProject($analysisData['required_roles'] ?? []);
    
    // Projekt-Objekt erstellen
    $project = [
        'id' => uniqid('proj_', true),
        'name' => $projectName ?: ($analysisData['project_summary']['name'] ?? 'Unbenanntes Projekt'),
        'summary' => $analysisData['project_summary'] ?? [],
        'required_roles' => $analysisData['required_roles'] ?? [],
        'total_cost' => $analysisData['total_cost'] ?? [],
        'critical_skills' => $analysisData['critical_skills'] ?? [],
        'recommendations' => $analysisData['recommendations'] ?? '',
        'matched_candidates' => $matched_candidates,
        'created_at' => date('c'),
        'updated_at' => date('c'),
        'original_filename' => $originalFilename ?? 'Manual Input',
        'status' => 'open', // open, in_progress, completed, cancelled
        'original_text' => $extractedText
    ];
    
    $projects = loadProjects();
    $projects[] = $project;
    saveProjects($projects);
    
    error_log("✅ Neues Projekt analysiert: {$project['name']} (ID: {$project['id']})");
    
    echo json_encode([
        'success' => true,
        'project' => $project,
        'message' => 'Projekt erfolgreich analysiert'
    ]);
}

function handleGetProjects() {
    $projects = loadProjects();
    
    $status = $_GET['status'] ?? 'all';
    $search = $_GET['search'] ?? '';
    
    $filtered = array_filter($projects, function($proj) use ($status, $search) {
        if ($status !== 'all' && ($proj['status'] ?? 'open') !== $status) {
            return false;
        }
        
        if ($search) {
            $searchable = json_encode($proj, JSON_UNESCAPED_UNICODE);
            if (stripos($searchable, $search) === false) {
                return false;
            }
        }
        
        return true;
    });
    
    usort($filtered, fn($a, $b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));
    
    echo json_encode([
        'success' => true,
        'data' => array_values($filtered),
        'total' => count($filtered)
    ]);
}

function handleUpdateProject() {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? '';
    $updates = $input['updates'] ?? [];
    
    if (!$id) {
        http_response_code(400);
        die(json_encode(['error' => 'Missing project ID']));
    }
    
    $projects = loadProjects();
    $found = false;
    
    foreach ($projects as &$proj) {
        if ($proj['id'] === $id) {
            foreach ($updates as $key => $value) {
                $proj[$key] = $value;
            }
            $proj['updated_at'] = date('c');
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        http_response_code(404);
        die(json_encode(['error' => 'Project not found']));
    }
    
    saveProjects($projects);
    
    error_log("✏️ Projekt aktualisiert: ID {$id}");
    
    echo json_encode([
        'success' => true,
        'message' => 'Projekt erfolgreich aktualisiert'
    ]);
}

function handleDeleteProject() {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? $_GET['id'] ?? '';
    
    if (!$id) {
        http_response_code(400);
        die(json_encode(['error' => 'Missing project ID']));
    }
    
    $projects = loadProjects();
    $filtered = array_filter($projects, fn($proj) => $proj['id'] !== $id);
    
    if (count($filtered) === count($projects)) {
        http_response_code(404);
        die(json_encode(['error' => 'Project not found']));
    }
    
    saveProjects(array_values($filtered));
    
    error_log("🗑️ Projekt gelöscht: ID {$id}");
    
    echo json_encode([
        'success' => true,
        'message' => 'Projekt erfolgreich gelöscht'
    ]);
}

function handleAnalyzeProject() {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? '';
    
    if (!$id) {
        http_response_code(400);
        die(json_encode(['error' => 'Missing project ID']));
    }
    
    $projects = loadProjects();
    $project = null;
    
    foreach ($projects as &$proj) {
        if ($proj['id'] === $id) {
            // Re-analyse mit aktuellem Kandidaten-Pool
            $matched_candidates = findCandidatesForProject($proj['required_roles'] ?? []);
            $proj['matched_candidates'] = $matched_candidates;
            $proj['updated_at'] = date('c');
            $project = $proj;
            break;
        }
    }
    
    if (!$project) {
        http_response_code(404);
        die(json_encode(['error' => 'Project not found']));
    }
    
    saveProjects($projects);
    
    echo json_encode([
        'success' => true,
        'project' => $project,
        'message' => 'Projekt neu analysiert'
    ]);
}

?>
