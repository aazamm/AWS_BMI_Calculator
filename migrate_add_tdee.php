<?php
/**
 * One-time migration: Add tdee and diet_type columns to bmi_records.
 * Run via: curl http://localhost/bmi/migrate_add_tdee.php
 * Delete this file after running.
 */
require_once __DIR__ . '/db.php';

try {
    $db = getDB();
    $db->exec('ALTER TABLE bmi_records ADD COLUMN IF NOT EXISTS tdee FLOAT');
    $db->exec('ALTER TABLE bmi_records ADD COLUMN IF NOT EXISTS diet_type VARCHAR(20)');
    echo json_encode(['success' => true, 'message' => 'Migration complete: tdee and diet_type columns added']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
