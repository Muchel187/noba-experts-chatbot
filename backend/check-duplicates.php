<?php
/**
 * Duplikate-Analyse Tool
 */

header('Content-Type: text/plain; charset=utf-8');

echo "🔍 Duplikate-Analyse\n";
echo "====================\n\n";

$conversationsFile = dirname(__DIR__) . '/chatbot-conversations.json';

if (!file_exists($conversationsFile)) {
    die("❌ chatbot-conversations.json nicht gefunden!\n");
}

$data = json_decode(file_get_contents($conversationsFile), true);

echo "📊 Gesamt-Einträge: " . count($data) . "\n\n";

// Gruppiere nach session_id
$grouped = [];
foreach ($data as $conv) {
    $session_id = $conv['session_id'] ?? '';
    if (!$session_id) continue;

    if (!isset($grouped[$session_id])) {
        $grouped[$session_id] = [];
    }
    $grouped[$session_id][] = $conv;
}

// Finde Duplikate
$duplicates = array_filter($grouped, fn($sessions) => count($sessions) > 1);

echo "🔢 Unique Sessions: " . count($grouped) . "\n";
echo "⚠️  Duplikate: " . count($duplicates) . "\n\n";

if (!empty($duplicates)) {
    echo "Top 10 Duplikate:\n";
    echo "==================\n";

    // Sortiere nach Anzahl
    uasort($duplicates, fn($a, $b) => count($b) - count($a));

    $count = 0;
    foreach ($duplicates as $session_id => $sessions) {
        if ($count++ >= 10) break;

        $sessionShort = substr($session_id, 0, 8);
        $numDuplicates = count($sessions);

        echo "{$sessionShort}: {$numDuplicates}x Duplikate\n";

        // Zeige Details
        foreach ($sessions as $idx => $session) {
            $msgCount = count($session['messages'] ?? []);
            $timestamp = $session['timestamp'] ?? 'N/A';
            echo "  [{$idx}] Messages: {$msgCount}, Time: {$timestamp}\n";
        }
        echo "\n";
    }
}

echo "\n💡 Hinweis: Die loadConversations() Funktion sollte Duplikate entfernen.\n";
echo "   Wenn Duplikate angezeigt werden, ist die Deduplizierung fehlerhaft.\n";
