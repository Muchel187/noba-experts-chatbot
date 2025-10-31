<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

echo json_encode([
    'received' => $input,
    'has_message' => isset($input['message']),
    'message_value' => $input['message'] ?? null,
    'history_count' => isset($input['history']) ? count($input['history']) : 0
]);
