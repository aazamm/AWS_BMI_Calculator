<?php

function loadEnv(): void {
    static $loaded = false;
    if ($loaded) return;
    $loaded = true;

    $envFile = __DIR__ . '/.env';
    if (file_exists($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (str_starts_with($line, '#')) continue;
            if (str_contains($line, '=')) {
                [$key, $val] = explode('=', $line, 2);
                putenv(trim($key) . '=' . trim($val));
            }
        }
    }
}

function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    loadEnv();
    $dbUrl = getenv('DATABASE_URL');

    if (!$dbUrl) {
        throw new RuntimeException('DATABASE_URL not set');
    }

    $parts = parse_url($dbUrl);
    $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s',
        $parts['host'],
        $parts['port'] ?? 5432,
        ltrim($parts['path'], '/')
    );

    $pdo = new PDO($dsn, $parts['user'], $parts['pass'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function incrementVisitorCount(): int {
    $db = getDB();
    $db->exec('UPDATE visitor_counter SET count = count + 1 WHERE id = 1');
    $stmt = $db->query('SELECT count FROM visitor_counter WHERE id = 1');
    return (int) $stmt->fetchColumn();
}

function saveRecord(
    string $name,
    string $surname,
    float $weightKg,
    float $heightCm,
    ?int $userId = null,
    ?string $gender = null,
    ?float $neckCm = null,
    ?float $waistCm = null,
    ?float $hipCm = null,
    ?float $bodyFatPct = null,
    ?float $whtr = null,
    ?float $tdee = null,
    ?string $dietType = null
): array {
    $heightM = $heightCm / 100;
    $bmi = round($weightKg / ($heightM * $heightM), 2);

    $db = getDB();
    $stmt = $db->prepare('INSERT INTO bmi_records (name, surname, weight_kg, height_cm, bmi, user_id, gender, neck_cm, waist_cm, hip_cm, body_fat_pct, whtr, tdee, diet_type) VALUES (:name, :surname, :weight, :height, :bmi, :user_id, :gender, :neck_cm, :waist_cm, :hip_cm, :body_fat_pct, :whtr, :tdee, :diet_type)');
    $stmt->execute([
        ':name' => $name,
        ':surname' => $surname,
        ':weight' => $weightKg,
        ':height' => $heightCm,
        ':bmi' => $bmi,
        ':user_id' => $userId,
        ':gender' => $gender,
        ':neck_cm' => $neckCm,
        ':waist_cm' => $waistCm,
        ':hip_cm' => $hipCm,
        ':body_fat_pct' => $bodyFatPct,
        ':whtr' => $whtr,
        ':tdee' => $tdee,
        ':diet_type' => $dietType,
    ]);

    return ['bmi' => $bmi, 'category' => getBMICategory($bmi)];
}

function getRecords(): array {
    $db = getDB();
    $stmt = $db->query('SELECT * FROM bmi_records WHERE hidden = 0 ORDER BY created_at DESC');
    return $stmt->fetchAll();
}

function getRecordsByUser(int $userId): array {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM bmi_records WHERE user_id = :uid AND hidden = 0 ORDER BY created_at DESC');
    $stmt->execute([':uid' => $userId]);
    return $stmt->fetchAll();
}

function getAllRecords(): array {
    $db = getDB();
    $stmt = $db->query('SELECT * FROM bmi_records ORDER BY created_at DESC');
    return $stmt->fetchAll();
}

function hideRecord(int $id): void {
    $db = getDB();
    $stmt = $db->prepare('UPDATE bmi_records SET hidden = 1 WHERE id = :id');
    $stmt->execute([':id' => $id]);
}

function unhideRecord(int $id): void {
    $db = getDB();
    $stmt = $db->prepare('UPDATE bmi_records SET hidden = 0 WHERE id = :id');
    $stmt->execute([':id' => $id]);
}

function getBMICategory(float $bmi): string {
    if ($bmi < 18.5) return 'Underweight';
    if ($bmi < 25) return 'Normal weight';
    if ($bmi < 30) return 'Overweight';
    return 'Obese';
}

// --- User functions ---

function createUser(string $email, string $passwordHash, string $displayName): int {
    $db = getDB();
    $stmt = $db->prepare('INSERT INTO users (email, password_hash, display_name) VALUES (:email, :hash, :name)');
    $stmt->execute([':email' => $email, ':hash' => $passwordHash, ':name' => $displayName]);
    return (int) $db->lastInsertId();
}

function getUserByEmail(string $email): ?array {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE email = :email');
    $stmt->execute([':email' => $email]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function getUserById(int $id): ?array {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

// --- Goal functions ---

function setGoalWeight(int $userId, float $goalKg): void {
    $db = getDB();
    $stmt = $db->prepare('INSERT INTO user_goals (user_id, goal_weight_kg) VALUES (:uid, :goal)');
    $stmt->execute([':uid' => $userId, ':goal' => $goalKg]);
    // Also update users table for quick access
    $stmt = $db->prepare('UPDATE users SET goal_weight_kg = :goal WHERE id = :uid');
    $stmt->execute([':goal' => $goalKg, ':uid' => $userId]);
}

function getGoalWeight(int $userId): ?float {
    $db = getDB();
    $stmt = $db->prepare('SELECT goal_weight_kg FROM users WHERE id = :uid');
    $stmt->execute([':uid' => $userId]);
    $val = $stmt->fetchColumn();
    return $val !== false && $val !== null ? (float) $val : null;
}

// --- Unit preference functions ---

function getUserPreferences(int $userId): array {
    $db = getDB();
    $stmt = $db->prepare('SELECT pref_weight_unit, pref_height_unit FROM users WHERE id = :uid');
    $stmt->execute([':uid' => $userId]);
    $row = $stmt->fetch();
    return [
        'weight_unit' => $row['pref_weight_unit'] ?? 'kg',
        'height_unit' => $row['pref_height_unit'] ?? 'cm',
    ];
}

function saveUserPreferences(int $userId, string $weightUnit, string $heightUnit): void {
    $db = getDB();
    $stmt = $db->prepare('UPDATE users SET pref_weight_unit = :wu, pref_height_unit = :hu WHERE id = :uid');
    $stmt->execute([':wu' => $weightUnit, ':hu' => $heightUnit, ':uid' => $userId]);
}

// --- Google OAuth functions ---

function getUserByGoogleId(string $googleId): ?array {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE google_id = :gid');
    $stmt->execute([':gid' => $googleId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function createGoogleUser(string $email, string $displayName, string $googleId): int {
    $db = getDB();
    $stmt = $db->prepare('INSERT INTO users (email, password_hash, display_name, google_id) VALUES (:email, NULL, :name, :gid)');
    $stmt->execute([':email' => $email, ':name' => $displayName, ':gid' => $googleId]);
    return (int) $db->lastInsertId();
}

function linkGoogleId(int $userId, string $googleId): void {
    $db = getDB();
    $stmt = $db->prepare('UPDATE users SET google_id = :gid WHERE id = :uid');
    $stmt->execute([':gid' => $googleId, ':uid' => $userId]);
}
