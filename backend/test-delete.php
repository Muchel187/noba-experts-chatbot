<?php
/**
 * Test für Delete-Funktion
 */

header('Content-Type: text/plain; charset=utf-8');

echo "🔍 Testing Delete Function\n";
echo "==========================\n\n";

// Pfad-Test
$expectedPath = dirname(__DIR__) . '/chatbot-conversations.json';
echo "Expected file path: $expectedPath\n";
echo "File exists: " . (file_exists($expectedPath) ? "✅ YES" : "❌ NO") . "\n";
echo "File size: " . (file_exists($expectedPath) ? filesize($expectedPath) : 0) . " bytes\n";
echo "Readable: " . (is_readable($expectedPath) ? "✅ YES" : "❌ NO") . "\n";
echo "Writable: " . (is_writable($expectedPath) ? "✅ YES" : "❌ NO") . "\n\n";

// Load Test
if (file_exists($expectedPath)) {
    $data = json_decode(file_get_contents($expectedPath), true);
    echo "Conversations loaded: " . count($data) . "\n";

    if (!empty($data)) {
        echo "First session ID: " . ($data[0]['session_id'] ?? 'N/A') . "\n";
    }
} else {
    echo "❌ Cannot load conversations - file not found!\n";
}

echo "\n✅ If all checks passed, delete should work!\n";
