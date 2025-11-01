<?php
/**
 * Duplikate-Cleaning Tool
 * Entfernt Duplikate, behält nur die neueste Version mit den meisten Messages
 */

header('Content-Type: text/plain; charset=utf-8');

echo "🧹 Duplikate-Cleaning\n";
echo "=====================\n\n";

$conversationsFile = dirname(__DIR__) . '/chatbot-conversations.json';

if (!file_exists($conversationsFile)) {
    die("❌ chatbot-conversations.json nicht gefunden!\n");
}

// Backup erstellen
$backupFile = dirname(__DIR__) . '/chatbot-conversations.backup.' . date('Ymd_His') . '.json';
copy($conversationsFile, $backupFile);
echo "💾 Backup erstellt: " . basename($backupFile) . "\n\n";

$data = json_decode(file_get_contents($conversationsFile), true);
$originalCount = count($data);

echo "📊 Original-Einträge: {$originalCount}\n";

// Deduplizierung: Nur neueste Version mit meisten Messages behalten
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

$cleaned = array_values($unique);
$newCount = count($cleaned);
$removed = $originalCount - $newCount;

echo "✅ Nach Cleaning: {$newCount}\n";
echo "🗑️  Entfernt: {$removed} Duplikate\n\n";

// Speichern
file_put_contents($conversationsFile, json_encode($cleaned, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "💾 Gespeichert: {$conversationsFile}\n";
echo "📝 Neue Dateigröße: " . number_format(filesize($conversationsFile) / 1024, 2) . " KB\n\n";

echo "✅ Cleaning abgeschlossen!\n";
echo "💡 Backup liegt in: " . basename($backupFile) . "\n";
