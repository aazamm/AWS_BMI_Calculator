<?php

function getDB(): SQLite3 {
    $dbPath = __DIR__ . '/bmi_data.db';
    $db = new SQLite3($dbPath);
    $db->exec('CREATE TABLE IF NOT EXISTS bmi_records (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        surname TEXT NOT NULL,
        weight_kg REAL NOT NULL,
        height_cm REAL NOT NULL,
        bmi REAL NOT NULL,
        hidden INTEGER NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');
    // Add hidden column if missing (existing databases)
    $columns = $db->query("PRAGMA table_info(bmi_records)");
    $hasHidden = false;
    while ($col = $columns->fetchArray(SQLITE3_ASSOC)) {
        if ($col['name'] === 'hidden') $hasHidden = true;
    }
    if (!$hasHidden) {
        $db->exec('ALTER TABLE bmi_records ADD COLUMN hidden INTEGER NOT NULL DEFAULT 0');
    }
    $db->exec('CREATE TABLE IF NOT EXISTS visitor_counter (
        id INTEGER PRIMARY KEY CHECK (id = 1),
        count INTEGER NOT NULL DEFAULT 0
    )');
    $db->exec('INSERT OR IGNORE INTO visitor_counter (id, count) VALUES (1, 0)');
    return $db;
}

function incrementVisitorCount(): int {
    $db = getDB();
    $db->exec('UPDATE visitor_counter SET count = count + 1 WHERE id = 1');
    $result = $db->querySingle('SELECT count FROM visitor_counter WHERE id = 1');
    $db->close();
    return (int) $result;
}

function saveRecord(string $name, string $surname, float $weightKg, float $heightCm): array {
    $heightM = $heightCm / 100;
    $bmi = round($weightKg / ($heightM * $heightM), 2);

    $db = getDB();
    $stmt = $db->prepare('INSERT INTO bmi_records (name, surname, weight_kg, height_cm, bmi) VALUES (:name, :surname, :weight, :height, :bmi)');
    $stmt->bindValue(':name', $name, SQLITE3_TEXT);
    $stmt->bindValue(':surname', $surname, SQLITE3_TEXT);
    $stmt->bindValue(':weight', $weightKg, SQLITE3_FLOAT);
    $stmt->bindValue(':height', $heightCm, SQLITE3_FLOAT);
    $stmt->bindValue(':bmi', $bmi, SQLITE3_FLOAT);
    $stmt->execute();
    $db->close();

    return ['bmi' => $bmi, 'category' => getBMICategory($bmi)];
}

function getRecords(): array {
    $db = getDB();
    $results = $db->query('SELECT * FROM bmi_records WHERE hidden = 0 ORDER BY created_at DESC');
    $records = [];
    while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
        $records[] = $row;
    }
    $db->close();
    return $records;
}

function getAllRecords(): array {
    $db = getDB();
    $results = $db->query('SELECT * FROM bmi_records ORDER BY created_at DESC');
    $records = [];
    while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
        $records[] = $row;
    }
    $db->close();
    return $records;
}

function hideRecord(int $id): void {
    $db = getDB();
    $stmt = $db->prepare('UPDATE bmi_records SET hidden = 1 WHERE id = :id');
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->execute();
    $db->close();
}

function unhideRecord(int $id): void {
    $db = getDB();
    $stmt = $db->prepare('UPDATE bmi_records SET hidden = 0 WHERE id = :id');
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->execute();
    $db->close();
}

function getBMICategory(float $bmi): string {
    if ($bmi < 18.5) return 'Underweight';
    if ($bmi < 25) return 'Normal weight';
    if ($bmi < 30) return 'Overweight';
    return 'Obese';
}
