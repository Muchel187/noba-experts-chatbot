<?php
/**
 * NOBA EXPERTS - ADMIN DASHBOARD API
 * Version: 1.0
 * Backend für Admin Dashboard PWA
 */

header('Content-Type: application/json; charset=utf-8');

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
            'email' => $email,
            'name' => 'Admin',
            'role' => 'admin',
        ],
        'expires_at' => date('c', time() + (8 * 60 * 60)),
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
    $session_id = $_POST['session_id'] ?? '';

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
    $session_id = $_POST['session_id'] ?? '';
    $is_favorite = filter_var($_POST['is_favorite'] ?? false, FILTER_VALIDATE_BOOLEAN);

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
    $session_id = $_POST['session_id'] ?? $_GET['session_id'] ?? '';

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
    $session_id = $_POST['session_id'] ?? $_GET['session_id'] ?? '';

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
    $file = dirname(__DIR__) . '/chatbot-conversations.json';
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
    $file = dirname(__DIR__) . '/chatbot-conversations.json';
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

    // Custom Properties (müssen in HubSpot existieren!)
    if (!empty($data['lead_type'])) {
        $properties['lead_type'] = $data['lead_type']; // Custom Property
    }

    if (isset($data['lead_score'])) {
        $properties['lead_score'] = $data['lead_score']; // Custom Property
    }

    if (!empty($data['tech_stack'])) {
        $properties['tech_stack'] = implode(', ', $data['tech_stack']); // Custom Property
    }

    // Notiz mit Chat-Verlauf erstellen
    $chatHistory = '';

    // Warnung bei Placeholder-E-Mail
    if ($usePlaceholderEmail) {
        $chatHistory .= "⚠️ WICHTIG: Placeholder-E-Mail verwendet - Keine echte E-Mail-Adresse erfasst!\n";
        $chatHistory .= "Bitte echte E-Mail-Adresse nachträglich erfassen.\n\n";
    }

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
?>
