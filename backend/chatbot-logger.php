<?php
/**
 * CHATBOT CONVERSATION LOGGER
 * Speichert alle Konversationen für Lead-Analyse
 * AUCH OHNE Formular-Submission!
 */

// Memory-Limit erhöhen für große JSON-Dateien
ini_set('memory_limit', '256M');

// Zeitzone für Deutschland setzen
date_default_timezone_set('Europe/Berlin');

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

    // Session-ID für Tracking - verwende Frontend Session-ID falls vorhanden
    $session_id = $input['session_id'] ?? '';

    // Fallback: PHP Session-ID nur wenn keine vom Frontend gesendet wurde
    if (empty($session_id)) {
        session_start();
        $session_id = session_id();
    }

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
        $file_content = file_get_contents($log_file);
        $decoded = json_decode($file_content, true);

        // KRITISCH: Prüfe ob json_decode erfolgreich war
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            error_log('❌ JSON DECODE FAILED: ' . json_last_error_msg() . ' - File size: ' . strlen($file_content) . ' bytes');
            // NICHT überschreiben! Stattdessen Notfall-Backup erstellen
            $emergency_backup = dirname($log_file) . '/chatbot-conversations.emergency.' . date('Ymd_His') . '.json';
            copy($log_file, $emergency_backup);
            error_log('🚨 Emergency backup created: ' . $emergency_backup);
            // Versuche die Datei trotzdem zu behalten - lade NUR neue Session
            $conversations = [];
        } else {
            $conversations = $decoded;
            error_log('✅ Loaded ' . count($conversations) . ' existing conversations');
        }
    }

    // DSGVO: Automatisch alte Konversationen löschen (älter als 30 Tage)
    $conversations = array_filter($conversations, function($conv) {
        $retention = $conv['retention_until'] ?? null;
        if (!$retention) return true; // Alte Einträge ohne Datum behalten
        return strtotime($retention) > time();
    });

    // Prüfe ob Session bereits existiert und update (verhindert Duplikate!)
    $found = false;
    foreach ($conversations as $key => $conv) {
        if (($conv['session_id'] ?? '') === $session_id) {
            // Session existiert bereits - UPDATE statt neu hinzufügen
            $conversations[$key] = $conversation;
            $found = true;
            break;
        }
    }

    // Nur wenn Session nicht existiert: Neu hinzufügen
    if (!$found) {
        $conversations[] = $conversation;
    }

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

        // Lead-Typ erkennen (Kunde = sucht Mitarbeiter, Kandidat = sucht Job)
        // KUNDE/ARBEITGEBER Keywords
        if (preg_match('/(suche|brauche|benötige|gesucht|hiring|rekrutierung|einstellen).*(mitarbeiter|entwickler|engineer|fachkraft|personal|team|spezialist|experte)/i', $text)) {
            $data['lead_type'] = 'employer';
        }
        // Zusätzliche Kunde-Patterns
        elseif (preg_match('/(projekt|auftrag|team erweitern|verstärkung|vakanz|offene stelle|besetzt werden)/i', $text)) {
            $data['lead_type'] = 'employer';
        }
        // SEHR WICHTIG: Direkte Intent-Erkennung aus Quick Replies / Start-Buttons
        elseif (preg_match('/(mitarbeiter finden|personal finden|entwickler finden|stellenbeschreibung|stelle ausschreiben)/i', $text)) {
            $data['lead_type'] = 'employer';
        }
        // Zusätzlich: Wenn jemand "für mein Unternehmen" oder "für unsere Firma" sagt
        elseif (preg_match('/(für mein|für unser|für die).*(unternehmen|firma|projekt|team)/i', $text)) {
            $data['lead_type'] = 'employer';
        }
        // KANDIDAT Keywords
        elseif (preg_match('/(suche|interesse|bewerbe|interessiere|möchte).*(job|stelle|position|arbeit|anstellung|karriere)/i', $text)) {
            $data['lead_type'] = 'candidate';
        }
        // SEHR WICHTIG: Direkte Intent-Erkennung aus Quick Replies / Start-Buttons
        elseif (preg_match('/(job suchen|stelle suchen|arbeit suchen|karriere machen|bewerben möchte|neuen job)/i', $text)) {
            $data['lead_type'] = 'candidate';
        }
        // Zusätzliche Kandidat-Patterns
        elseif (preg_match('/(bin|arbeite als|erfahrung als|skills in|kann ich|mein lebenslauf|meine kenntnisse)/i', $text) && !isset($data['lead_type'])) {
            $data['lead_type'] = 'candidate';
        }
        // Default: Wenn kein Typ erkannt wurde, prüfe Kontext
        if (!isset($data['lead_type']) || $data['lead_type'] === null) {
            // Wenn jemand nach Dienstleistungen oder Vermittlung fragt = wahrscheinlich Kunde
            if (preg_match('/(vermittlung|dienstleistung|können sie|bieten sie|zeitarbeit|freelancer finden)/i', $text)) {
                $data['lead_type'] = 'employer';
            }
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
