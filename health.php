<?php
header("Content-Type: application/json");

$checks = [];
$healthy = true;

// PostgreSQL (shared RDS) is the only runtime dependency in the containerized
// deployment. The old Apache-process / disk-usage / local-MariaDB checks were
// removed — they are meaningless (and would fail) inside a Fargate container.
try {
    require_once __DIR__ . '/db.php';
    $db = getDB();
    $db->query('SELECT count FROM visitor_counter WHERE id = 1')->fetch();
    $checks['postgresql'] = 'ok';
} catch (Exception $e) {
    $checks['postgresql'] = 'fail';
    $healthy = false;
}

http_response_code($healthy ? 200 : 503);
echo json_encode(['healthy' => $healthy, 'checks' => $checks, 'version' => '3.0.0']);
