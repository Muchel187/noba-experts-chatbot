<?php
/**
 * E-MAIL ZUSAMMENFASSUNG für PWA Chatbot
 * Sendet Konversation als strukturierte E-Mail
 */

// Zeitzone für Deutschland setzen
date_default_timezone_set('Europe/Berlin');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONS Request (Preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Nur POST erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'Method not allowed']));
}

// Input validieren
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid JSON']));
}

$recipientEmail = filter_var($input['recipient_email'] ?? '', FILTER_VALIDATE_EMAIL);
$conversation = $input['conversation'] ?? [];
$extractedData = $input['extracted_data'] ?? [];
$includeFullChat = $input['include_full_chat'] ?? true;
$sessionId = $input['session_id'] ?? 'unknown';
$autoSent = $input['auto_sent'] ?? false; // Automatisch gesendet?
$documentContext = $input['document_context'] ?? null;

if (!$recipientEmail) {
    http_response_code(400);
    die(json_encode(['error' => 'Ungültige E-Mail-Adresse']));
}

if (empty($conversation)) {
    http_response_code(400);
    die(json_encode(['error' => 'Keine Konversation vorhanden']));
}

// ===== E-MAIL ZUSAMMENFASSUNG ERSTELLEN =====

/**
 * Generiere HTML-E-Mail mit Zusammenfassung
 */
function generateEmailHTML($conversation, $extractedData, $includeFullChat, $sessionId) {
    $messageCount = count($conversation);
    $timestamp = date('d.m.Y H:i');

    // Lead-Score
    $leadScore = $extractedData['lead_score'] ?? 0;
    $leadScoreColor = $leadScore >= 70 ? '#10b981' : ($leadScore >= 40 ? '#f59e0b' : '#ef4444');

    $html = <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
                <p style="margin: 0 0 10px 0; color: #666; font-size: 14px;">
                    <strong style="color: #333;">Session-ID:</strong> {$sessionId}<br>
                    <strong style="color: #333;">Datum:</strong> {$timestamp}<br>
                    <strong style="color: #333;">Nachrichten:</strong> {$messageCount}
                </p>
HTML;

    // Lead-Score nur wenn vorhanden
    if ($leadScore > 0) {
        $html .= <<<HTML
                <p style="margin: 10px 0 0 0;">
                    <strong style="color: #333;">Lead-Qualität:</strong>
                    <span style="background: {$leadScoreColor}; color: white; padding: 4px 12px; border-radius: 12px; font-weight: 600; font-size: 13px;">
                        {$leadScore} / 100
                    </span>
                </p>
HTML;
    }

    $html .= <<<HTML
            </div>

            <!-- Extrahierte Daten -->
HTML;

    if (!empty($extractedData) && ($extractedData['email'] || $extractedData['phone'] || $extractedData['name'])) {
        $html .= <<<HTML
            <h2 style="color: #333; font-size: 18px; margin: 0 0 15px 0; border-bottom: 2px solid #FF7B29; padding-bottom: 10px;">
                📊 Extrahierte Lead-Daten
            </h2>

            <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
HTML;

        // Name
        if (!empty($extractedData['name'])) {
            $html .= generateDataRow('👤 Name', htmlspecialchars($extractedData['name']));
        }

        // E-Mail
        if (!empty($extractedData['email'])) {
            $email = htmlspecialchars($extractedData['email']);
            $html .= generateDataRow('📧 E-Mail', "<a href='mailto:{$email}' style='color: #FF7B29; text-decoration: none;'>{$email}</a>");
        }

        // Telefon
        if (!empty($extractedData['phone'])) {
            $phone = htmlspecialchars($extractedData['phone']);
            $phoneClean = preg_replace('/[^0-9+]/', '', $phone);
            $html .= generateDataRow('📞 Telefon', "<a href='tel:{$phoneClean}' style='color: #FF7B29; text-decoration: none;'>{$phone}</a>");
        }

        // Firma
        if (!empty($extractedData['company'])) {
            $html .= generateDataRow('🏢 Firma', htmlspecialchars($extractedData['company']));
        }

        // Lead-Typ
        if (!empty($extractedData['lead_type'])) {
            $type = $extractedData['lead_type'] === 'employer' ? '💼 Kunde (sucht Mitarbeiter)' : '👔 Kandidat (sucht Job)';
            $html .= generateDataRow('Typ', $type);
        }

        // Position
        if (!empty($extractedData['position'])) {
            $html .= generateDataRow('💻 Position', htmlspecialchars($extractedData['position']));
        }

        // Tech-Stack
        if (!empty($extractedData['tech_stack']) && is_array($extractedData['tech_stack'])) {
            $techStack = implode(', ', array_map('htmlspecialchars', $extractedData['tech_stack']));
            $html .= generateDataRow('🛠️ Tech-Stack', $techStack);
        }

        // Erfahrung
        if (!empty($extractedData['experience_level'])) {
            $html .= generateDataRow('📈 Erfahrung', htmlspecialchars($extractedData['experience_level']));
        }

        // Standort
        if (!empty($extractedData['location'])) {
            $html .= generateDataRow('📍 Standort', htmlspecialchars($extractedData['location']));
        }

        // Dringlichkeit
        if (!empty($extractedData['urgency'])) {
            $urgency = htmlspecialchars($extractedData['urgency']);
            $urgencyColor = $urgency === 'Sehr hoch' ? '#ef4444' : ($urgency === 'Hoch' ? '#f59e0b' : '#6b7280');
            $html .= generateDataRow('⚡ Dringlichkeit', "<span style='color: {$urgencyColor}; font-weight: 600;'>{$urgency}</span>");
        }

        $html .= "</table>";
    }

    // Vollständiger Chat-Verlauf (falls aktiviert)
    if ($includeFullChat) {
        $html .= <<<HTML
            <h2 style="color: #333; font-size: 18px; margin: 30px 0 15px 0; border-bottom: 2px solid #FF7B29; padding-bottom: 10px;">
                💬 Chat-Verlauf
            </h2>
HTML;

        foreach ($conversation as $msg) {
            $role = $msg['role'] ?? 'unknown';
            $text = htmlspecialchars($msg['text'] ?? '');
            $time = isset($msg['timestamp']) ? date('H:i', strtotime($msg['timestamp'])) : '';

            if ($role === 'user') {
                $html .= <<<HTML
                <div style="margin-bottom: 15px; text-align: right;">
                    <div style="display: inline-block; max-width: 70%; background: linear-gradient(135deg, #FF7B29, #e66b24); color: white; padding: 12px 16px; border-radius: 16px 16px 4px 16px; text-align: left;">
                        <div style="font-size: 14px; line-height: 1.5; word-wrap: break-word;">{$text}</div>
                        <div style="font-size: 11px; margin-top: 4px; opacity: 0.8;">{$time}</div>
                    </div>
                </div>
HTML;
            } else {
                $html .= <<<HTML
                <div style="margin-bottom: 15px;">
                    <div style="display: inline-block; max-width: 70%; background: #f3f4f6; color: #333; padding: 12px 16px; border-radius: 16px 16px 16px 4px;">
                        <div style="font-size: 14px; line-height: 1.5; word-wrap: break-word;">{$text}</div>
                        <div style="font-size: 11px; margin-top: 4px; color: #6b7280;">{$time}</div>
                    </div>
                </div>
HTML;
            }
        }
    }

    $html .= <<<HTML
        </div>

        <!-- Footer -->
        <div style="background: #f9fafb; padding: 30px; text-align: center; border-top: 1px solid #e5e7eb;">
            <p style="margin: 0 0 10px 0; color: #666; font-size: 14px;">
                <strong>NOBA Experts GmbH</strong><br>
                IT & Engineering Personalberatung
            </p>
            <p style="margin: 0; color: #999; font-size: 12px;">
                📞 +49 211 975 324 74 | 📧 Jurak.Bahrambaek@noba-experts.de<br>
                🌐 <a href="https://www.noba-experts.de" style="color: #FF7B29; text-decoration: none;">www.noba-experts.de</a>
            </p>
            <p style="margin: 15px 0 0 0; color: #999; font-size: 11px;">
                Diese E-Mail wurde automatisch generiert von unserem KI-Assistenten.
            </p>
        </div>

    </div>
</body>
</html>
HTML;

    return $html;
}

/**
 * Hilfsfunktion: Tabellenzeile für Daten
 */
function generateDataRow($label, $value) {
    return <<<HTML
                <tr>
                    <td style="padding: 10px 0; border-bottom: 1px solid #f3f4f6; color: #666; font-size: 13px; width: 35%;">
                        {$label}
                    </td>
                    <td style="padding: 10px 0; border-bottom: 1px solid #f3f4f6; color: #333; font-size: 14px; font-weight: 500;">
                        {$value}
                    </td>
                </tr>
HTML;
}

/**
 * Plain-Text Version (für E-Mail-Clients ohne HTML-Support)
 */
function generatePlainText($conversation, $extractedData, $includeFullChat, $sessionId) {
    $messageCount = count($conversation);
    $timestamp = date('d.m.Y H:i');

    $text = "=============================================\n";
    $text .= "CHAT-ZUSAMMENFASSUNG - NOBA Experts\n";
    $text .= "=============================================\n\n";
    $text .= "Session-ID: {$sessionId}\n";
    $text .= "Datum: {$timestamp}\n";
    $text .= "Nachrichten: {$messageCount}\n";

    if (isset($extractedData['lead_score']) && $extractedData['lead_score'] > 0) {
        $text .= "Lead-Qualität: {$extractedData['lead_score']} / 100\n";
    }

    $text .= "\n";

    // Extrahierte Daten
    if (!empty($extractedData) && ($extractedData['email'] || $extractedData['phone'] || $extractedData['name'])) {
        $text .= "---------------------------------------------\n";
        $text .= "EXTRAHIERTE LEAD-DATEN\n";
        $text .= "---------------------------------------------\n";

        if (!empty($extractedData['name'])) $text .= "Name: {$extractedData['name']}\n";
        if (!empty($extractedData['email'])) $text .= "E-Mail: {$extractedData['email']}\n";
        if (!empty($extractedData['phone'])) $text .= "Telefon: {$extractedData['phone']}\n";
        if (!empty($extractedData['company'])) $text .= "Firma: {$extractedData['company']}\n";
        if (!empty($extractedData['lead_type'])) {
            $type = $extractedData['lead_type'] === 'employer' ? 'Kunde (sucht Mitarbeiter)' : 'Kandidat (sucht Job)';
            $text .= "Typ: {$type}\n";
        }
        if (!empty($extractedData['position'])) $text .= "Position: {$extractedData['position']}\n";
        if (!empty($extractedData['tech_stack']) && is_array($extractedData['tech_stack'])) {
            $text .= "Tech-Stack: " . implode(', ', $extractedData['tech_stack']) . "\n";
        }
        if (!empty($extractedData['experience_level'])) $text .= "Erfahrung: {$extractedData['experience_level']}\n";
        if (!empty($extractedData['location'])) $text .= "Standort: {$extractedData['location']}\n";
        if (!empty($extractedData['urgency'])) $text .= "Dringlichkeit: {$extractedData['urgency']}\n";

        $text .= "\n";
    }

    // Chat-Verlauf
    if ($includeFullChat) {
        $text .= "---------------------------------------------\n";
        $text .= "CHAT-VERLAUF\n";
        $text .= "---------------------------------------------\n\n";

        foreach ($conversation as $msg) {
            $role = ($msg['role'] ?? 'unknown') === 'user' ? 'USER' : 'BOT';
            $msgText = $msg['text'] ?? '';
            $time = isset($msg['timestamp']) ? date('H:i', strtotime($msg['timestamp'])) : '';

            $text .= "[{$time}] {$role}:\n{$msgText}\n\n";
        }
    }

    $text .= "=============================================\n";
    $text .= "NOBA Experts GmbH\n";
    $text .= "IT & Engineering Personalberatung\n";
    $text .= "Tel: +49 211 975 324 74\n";
    $text .= "E-Mail: Jurak.Bahrambaek@noba-experts.de\n";
    $text .= "Web: www.noba-experts.de\n";
    $text .= "=============================================\n";

    return $text;
}

// ===== E-MAIL VERSENDEN =====

// Betreff je nach Art der Anfrage
$subject = $autoSent
    ? "🔔 Automatische Chat-Protokoll - NOBA Experts (Session: " . substr($sessionId, 0, 8) . ")"
    : "Chat-Zusammenfassung - NOBA Experts KI-Berater";

$htmlBody = generateEmailHTML($conversation, $extractedData, $includeFullChat, $sessionId);
$plainText = generatePlainText($conversation, $extractedData, $includeFullChat, $sessionId);

// E-Mail Headers
$headers = [];
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'Content-Type: text/html; charset=UTF-8';
$headers[] = 'From: NOBA Experts - www.noba-experts.de <nobaexpertchatbot@gmail.com>';
$headers[] = 'Reply-To: Jurak.Bahrambaek@noba-experts.de';
$headers[] = 'X-Mailer: PHP/' . phpversion();
$headers[] = 'X-Priority: ' . ($autoSent ? '3' : '1'); // Auto-Mails niedrigere Priorität

// E-Mail senden (mit Envelope-Sender für korrekten Absender)
$success = mail($recipientEmail, $subject, $htmlBody, implode("\r\n", $headers), '-f nobaexpertchatbot@gmail.com');

// Log für Debugging
error_log("📧 E-Mail-Versand: " . ($success ? "Erfolg" : "Fehler") . " | An: $recipientEmail | Auto: " . ($autoSent ? "Ja" : "Nein"));

if ($success) {
    // Optional: Auch an Admin-E-Mail senden (für Lead-Nachverfolgung)
    // WICHTIG: Nur wenn nicht bereits an Admin gesendet
    $adminEmail = 'Jurak.Bahrambaek@noba-experts.de';

    if ($recipientEmail !== $adminEmail) {
        $adminSubject = $autoSent
            ? "🔔 AUTO: Chat-Protokoll (User verließ Chat ohne Kontakt)"
            : "📧 Chat-Zusammenfassung (User angefordert) - an {$recipientEmail}";

        mail($adminEmail, $adminSubject, $htmlBody, implode("\r\n", $headers), '-f nobaexpertchatbot@gmail.com');
        error_log("📧 Admin-Kopie gesendet an: $adminEmail");
    }

    // ===== DSGVO: Hochgeladene Dateien löschen =====
    if ($documentContext && isset($documentContext['server_path'])) {
        $filePath = $documentContext['server_path'];

        // Sicherheitsprüfung: Datei muss im uploads-Ordner sein
        $uploadsDir = __DIR__ . '/uploads/';
        $realPath = realpath($filePath);

        if ($realPath && strpos($realPath, realpath($uploadsDir)) === 0 && file_exists($realPath)) {
            if (unlink($realPath)) {
                error_log("🗑️ DSGVO: Datei gelöscht nach E-Mail-Versand: " . basename($realPath));
            } else {
                error_log("⚠️ WARNUNG: Datei konnte nicht gelöscht werden: " . basename($realPath));
            }
        } else {
            error_log("⚠️ WARNUNG: Datei nicht im uploads-Ordner oder existiert nicht: " . $filePath);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'E-Mail erfolgreich gesendet',
        'file_deleted' => isset($documentContext['server_path']) && !file_exists($documentContext['server_path'])
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'E-Mail konnte nicht gesendet werden'
    ]);
}

?>
