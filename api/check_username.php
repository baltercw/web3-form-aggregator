<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'available' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$username = isset($_POST['username']) ? trim((string) $_POST['username']) : '';

if ($username === '') {
    echo json_encode(['ok' => true, 'available' => false, 'message' => 'empty'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (strlen($username) > 50) {
    echo json_encode(['ok' => true, 'available' => false, 'message' => 'invalid'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $conn->prepare('SELECT 1 FROM users WHERE username = ? LIMIT 1');
if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'available' => false, 'message' => 'server'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt->bind_param('s', $username);
$stmt->execute();
$stmt->store_result();
$taken = $stmt->num_rows > 0;
$stmt->close();

echo json_encode([
    'ok' => true,
    'available' => !$taken,
    'message' => $taken ? 'taken' : 'ok',
], JSON_UNESCAPED_UNICODE);
