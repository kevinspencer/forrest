<?php

session_start();
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

function require_auth(): void {
    if (empty($_SESSION['authenticated'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

function json_error(int $status, string $message): void {
    http_response_code($status);
    echo json_encode(['error' => $message]);
    exit;
}

if ($method === 'GET') {
    $year  = $_GET['year']  ?? null;
    $month = $_GET['month'] ?? null;

    if (!$year || !$month) {
        json_error(400, 'year and month are required');
    }

    $pdo  = get_pdo();
    $stmt = $pdo->prepare(
        'SELECT run_date, miles, notes FROM runs
         WHERE YEAR(run_date) = ? AND MONTH(run_date) = ?
         ORDER BY run_date'
    );
    $stmt->execute([$year, $month]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

} elseif ($method === 'POST') {
    require_auth();
    $body = json_decode(file_get_contents('php://input'), true);

    $run_date = $body['run_date'] ?? null;
    $miles    = $body['miles']    ?? null;
    $notes    = $body['notes']    ?? null;

    if (!$run_date || $miles === null) {
        json_error(400, 'run_date and miles are required');
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $run_date)) {
        json_error(400, 'run_date must be YYYY-MM-DD');
    }

    if (!is_numeric($miles) || $miles < 0) {
        json_error(400, 'miles must be a non-negative number');
    }

    $pdo  = get_pdo();
    $stmt = $pdo->prepare(
        'INSERT INTO runs (run_date, miles, notes)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE miles = VALUES(miles), notes = VALUES(notes)'
    );
    $stmt->execute([$run_date, $miles, $notes]);
    http_response_code(200);
    echo json_encode(['ok' => true]);

} elseif ($method === 'DELETE') {
    require_auth();
    $run_date = $_GET['run_date'] ?? null;

    if (!$run_date) {
        json_error(400, 'run_date is required');
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $run_date)) {
        json_error(400, 'run_date must be YYYY-MM-DD');
    }

    $pdo  = get_pdo();
    $stmt = $pdo->prepare('DELETE FROM runs WHERE run_date = ?');
    $stmt->execute([$run_date]);

    if ($stmt->rowCount() === 0) {
        json_error(404, 'no run found for that date');
    }

    echo json_encode(['ok' => true]);

} else {
    json_error(405, 'method not allowed');
}
