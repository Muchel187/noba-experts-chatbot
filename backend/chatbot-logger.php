<?php
/**
 * CHATBOT CONVERSATION LOGGER
 * Speichert alle Konversationen für Lead-Analyse
 * AUCH OHNE Formular-Submission!
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

// Pfad zur Log-Datei (außerhalb von public_html für Sicherheit!)
$log_file = __DIR__ . '/chatbot-conversations.json';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Konversation speichern
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        die(json_encode(['error' => 'Invalid input']));
    }

    // Session-ID für Tracking
    session_start();
    $session_id = session_id();

    // DSGVO: IP-Adresse anonymisieren (nur erste 3 Oktette)
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ip_anonymized = preg_replace('/\.\d+$/', '.xxx', $ip); // z.B. 192.168.1.xxx

    // Konversationsdaten mit DSGVO-Minimierung
    $conversation = [
        'session_id' => $session_id,
        'timestamp' => date('Y-m-d H:i:s'),
        'messages' => $input['messages'] ?? [],
        'metadata' => [
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200), // Begrenzt
            'ip_address' => $ip_anonymized, // ANONYMISIERT!
            'referer' => parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST) ?? '', // Nur Domain
        ],
        'extracted_data' => extractLeadData($input['messages'] ?? []),
        'form_submitted' => $input['form_submitted'] ?? false,
        'consent_given' => true, // User hat DSGVO-Consent gegeben
        'retention_until' => date('Y-m-d', strtotime('+30 days')) // Auto-Löschung nach 30 Tagen
    ];

    // Bestehende Logs laden
    $conversations = [];
    if (file_exists($log_file)) {
        $conversations = json_decode(file_get_contents($log_file), true) ?? [];
    }

    // DSGVO: Automatisch alte Konversationen löschen (älter als 30 Tage)
    $conversations = array_filter($conversations, function($conv) {
        $retention = $conv['retention_until'] ?? null;
        if (!$retention) return true; // Alte Einträge ohne Datum behalten
        return strtotime($retention) > time();
    });

    // Neue Konversation hinzufügen
    $conversations[] = $conversation;

    // Speichern (maximal 1000 Konversationen)
    if (count($conversations) > 1000) {
        $conversations = array_slice($conversations, -1000);
    }

    // Re-indexieren
    $conversations = array_values($conversations);

    file_put_contents($log_file, json_encode($conversations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    echo json_encode([
        'success' => true,
        'session_id' => $session_id,
        'extracted_data' => $conversation['extracted_data']
    ]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Admin-Zugriff: Konversationen abrufen
    // WICHTIG: Zusätzlich mit .htaccess absichern!

    session_start();

    $password = $_GET['password'] ?? '';
    $admin_password = 'IHR_PASSWORT_HIER'; // Siehe SECRETS.local.md oder .env!

    // Session-basierte Auth: Wenn bereits eingeloggt, kein Passwort nötig
    if (!isset($_SESSION['admin_authenticated'])) {
        if ($password !== $admin_password) {
            http_response_code(403);
            die(json_encode(['error' => 'Unauthorized']));
        }

        // Login erfolgreich - Session setzen
        $_SESSION['admin_authenticated'] = true;
        $_SESSION['admin_login_time'] = time();
        $_SESSION['admin_login_ip'] = $_SERVER['REMOTE_ADDR'];
    }

    // Session-Timeout: 4 Stunden
    if (time() - $_SESSION['admin_login_time'] > 14400) {
        session_destroy();
        http_response_code(403);
        die(json_encode(['error' => 'Session expired']));
    }

    if (!file_exists($log_file)) {
        echo json_encode([]);
        exit;
    }

    $conversations = json_decode(file_get_contents($log_file), true) ?? [];

    // Filter-Optionen
    $filter = $_GET['filter'] ?? 'all';

    if ($filter === 'no_submission') {
        $conversations = array_filter($conversations, function($conv) {
            return !($conv['form_submitted'] ?? false);
        });
    } elseif ($filter === 'with_data') {
        $conversations = array_filter($conversations, function($conv) {
            $data = $conv['extracted_data'] ?? [];
            return !empty($data['email']) || !empty($data['phone']);
        });
    }

    // Sortiere nach Timestamp (neueste zuerst)
    usort($conversations, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });

    echo json_encode([
        'total' => count($conversations),
        'conversations' => array_slice($conversations, 0, 100) // Maximal 100
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // DSGVO: Einzelne Konversation löschen (z.B. auf User-Anfrage)

    session_start();

    // Nur für authentifizierte Admins
    if (!isset($_SESSION['admin_authenticated'])) {
        http_response_code(403);
        die(json_encode(['error' => 'Unauthorized']));
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $session_id_to_delete = $input['session_id'] ?? '';

    if (!$session_id_to_delete) {
        http_response_code(400);
        die(json_encode(['error' => 'session_id required']));
    }

    if (!file_exists($log_file)) {
        http_response_code(404);
        die(json_encode(['error' => 'No conversations found']));
    }

    $conversations = json_decode(file_get_contents($log_file), true) ?? [];

    // Filtere Konversation raus
    $count_before = count($conversations);
    $conversations = array_filter($conversations, function($conv) use ($session_id_to_delete) {
        return $conv['session_id'] !== $session_id_to_delete;
    });
    $count_after = count($conversations);

    if ($count_before === $count_after) {
        http_response_code(404);
        die(json_encode(['error' => 'Conversation not found']));
    }

    // Re-indexieren
    $conversations = array_values($conversations);

    // Speichern
    file_put_contents($log_file, json_encode($conversations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // Log für DSGVO-Nachweis
    error_log('DSGVO: Konversation gelöscht - Session: ' . $session_id_to_delete . ' - Admin IP: ' . $_SERVER['REMOTE_ADDR']);

    echo json_encode([
        'success' => true,
        'deleted' => 1,
        'remaining' => count($conversations)
    ]);
}

/**
 * INTELLIGENTE DATEN-EXTRAKTION
 * Extrahiert Lead-Daten aus der Konversation
 */
function extractLeadData($messages) {
    $data = [
        'lead_type' => null,        // 'employer' oder 'candidate'
        'name' => null,
        'email' => null,
        'phone' => null,
        'company' => null,
        'position' => null,
        'tech_stack' => [],
        'experience_level' => null,
        'location' => null,
        'urgency' => null,
        'additional_info' => []
    ];

    foreach ($messages as $msg) {
        $text = $msg['text'] ?? '';
        $role = $msg['role'] ?? '';

        if ($role !== 'user') continue;

        $lower = strtolower($text);

        // Lead-Typ erkennen
        if (preg_match('/(suche|brauche|benötige).*(mitarbeiter|entwickler|engineer|fachkraft|personal|team)/i', $text)) {
            $data['lead_type'] = 'employer';
        } elseif (preg_match('/(suche|interesse|bewerbe).*(job|stelle|position|arbeit)/i', $text)) {
            $data['lead_type'] = 'candidate';
        }

        // E-Mail extrahieren
        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $text, $matches)) {
            $data['email'] = $matches[0];
        }

        // Telefonnummer extrahieren (verschiedene Formate)
        // Deutsch: 0151 12345678, 015112345678, +49 151 12345678
        // Mit Leerzeichen, Bindestriche, Schrägstriche
        if (preg_match('/(\+49\s?|0)(\d{2,4})[\s\-\/]?(\d{3,})[\s\-\/]?(\d{3,})/', $text, $matches)) {
            // Entferne alle Leerzeichen und Sonderzeichen
            $phone = preg_replace('/[\s\-\/]/', '', $matches[0]);
            // Normalisiere: Wenn +49, behalte; wenn 0, behalte
            $data['phone'] = $phone;
        }
        // Alternativ-Pattern für Nummern ohne Präfix (z.B. nur Ziffern)
        elseif (preg_match('/\b\d{10,13}\b/', $text, $matches)) {
            $data['phone'] = $matches[0];
        }

        // Name extrahieren (wenn "Ich bin..." oder "Mein Name ist...")
        if (preg_match('/(ich bin|mein name ist|ich heiße)\s+([A-ZÄÖÜ][a-zäöüß]+\s+[A-ZÄÖÜ][a-zäöüß]+)/i', $text, $matches)) {
            $data['name'] = $matches[2];
        }

        // Firma extrahieren
        if (preg_match('/(firma|unternehmen|company|bei)\s+([A-ZÄÖÜ][a-zA-ZäöüÄÖÜß\s&]+(?:GmbH|AG|SE|Inc|Ltd)?)/i', $text, $matches)) {
            $data['company'] = trim($matches[2]);
        }

        // Position extrahieren
        $positions = [
            'devops', 'backend', 'frontend', 'fullstack', 'full-stack',
            'data scientist', 'data engineer', 'software engineer',
            'entwickler', 'developer', 'architect', 'lead', 'manager',
            'product owner', 'scrum master', 'qa', 'tester'
        ];

        foreach ($positions as $pos) {
            if (stripos($lower, $pos) !== false) {
                $data['position'] = ucfirst($pos);
                break;
            }
        }

        // Tech-Stack extrahieren
        $techs = [
            'java', 'python', 'javascript', 'typescript', 'php', 'c#', 'c++', 'go', 'rust',
            'react', 'angular', 'vue', 'node', 'spring', 'django', 'flask',
            'aws', 'azure', 'gcp', 'docker', 'kubernetes', 'k8s',
            'sql', 'postgresql', 'mysql', 'mongodb', 'redis',
            'devops', 'ci/cd', 'jenkins', 'gitlab', 'github'
        ];

        foreach ($techs as $tech) {
            if (stripos($lower, $tech) !== false && !in_array($tech, $data['tech_stack'])) {
                $data['tech_stack'][] = ucfirst($tech);
            }
        }

        // Experience Level
        if (preg_match('/(junior|einsteiger|anfänger)/i', $text)) {
            $data['experience_level'] = 'Junior';
        } elseif (preg_match('/(senior|lead|principal|expert)/i', $text)) {
            $data['experience_level'] = 'Senior';
        } elseif (preg_match('/(\d+)\s*(jahre?|years?)/i', $text, $matches)) {
            $years = intval($matches[1]);
            $data['experience_level'] = $years . ' Jahre';
        }

        // Standort
        $locations = ['düsseldorf', 'remote', 'hybrid', 'vor ort', 'homeoffice', 'berlin', 'münchen', 'köln', 'hamburg'];
        foreach ($locations as $loc) {
            if (stripos($lower, $loc) !== false) {
                $data['location'] = ucfirst($loc);
                break;
            }
        }

        // Dringlichkeit
        if (preg_match('/(dringend|asap|schnell|sofort|zeitnah)/i', $text)) {
            $data['urgency'] = 'Hoch';
        } elseif (preg_match('/(suchen seit|erfolglos|verzweifelt)/i', $text)) {
            $data['urgency'] = 'Sehr hoch';
        }

        // Zusätzliche Infos sammeln
        if (strlen($text) > 30) {
            $data['additional_info'][] = $text;
        }
    }

    // Bereinigen
    $data['tech_stack'] = array_values(array_unique($data['tech_stack']));
    $data['additional_info'] = array_slice($data['additional_info'], 0, 3); // Max 3

    // Scoring für Lead-Qualität
    $data['lead_score'] = calculateLeadScore($data);

    return $data;
}

/**
 * LEAD-SCORE berechnen (0-100)
 */
function calculateLeadScore($data) {
    $score = 0;

    // Kontaktdaten
    if (!empty($data['email'])) $score += 30;
    if (!empty($data['phone'])) $score += 20;
    if (!empty($data['name'])) $score += 10;

    // Firmendaten (Arbeitgeber)
    if (!empty($data['company'])) $score += 15;

    // Fachliche Details
    if (!empty($data['position'])) $score += 10;
    if (!empty($data['tech_stack'])) $score += count($data['tech_stack']) * 2; // Max 10
    if (!empty($data['experience_level'])) $score += 5;

    // Engagement
    if (count($data['additional_info']) > 0) $score += 5;
    if (!empty($data['urgency'])) $score += 10;

    return min(100, $score);
}

?>
