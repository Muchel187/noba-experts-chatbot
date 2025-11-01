<?php
// Restore backup and deduplicate

$backup = json_decode(file_get_contents('chatbot-conversations.backup.20251101_210921.json'), true);
echo "Backup has: " . count($backup) . " entries\n";

// Deduplicate: Keep only newest version with most messages
$unique = [];
foreach ($backup as $conv) {
    $session_id = $conv['session_id'] ?? '';
    if (!$session_id) continue;

    if (!isset($unique[$session_id]) ||
        count($conv['messages'] ?? []) > count($unique[$session_id]['messages'] ?? [])) {
        $unique[$session_id] = $conv;
    }
}

$cleaned = array_values($unique);
echo "After deduplication: " . count($cleaned) . " unique sessions\n";

// Sort by timestamp (newest first)
usort($cleaned, function($a, $b) {
    return strtotime($b['timestamp']) - strtotime($a['timestamp']);
});

file_put_contents('chatbot-conversations.json', json_encode($cleaned, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "✅ Saved to chatbot-conversations.json\n";
echo "📁 File size: " . number_format(filesize('chatbot-conversations.json') / 1024, 2) . " KB\n";
