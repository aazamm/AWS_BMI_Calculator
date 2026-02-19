<?php
header("Content-Type: application/json");

$checks = [];
$healthy = true;

// 1. SQLite - BMI Calculator database
try {
    $dbPath = __DIR__ . '/bmi_data.db';
    $db = new SQLite3($dbPath);
    $db->querySingle('SELECT count FROM visitor_counter WHERE id = 1');
    $db->close();
    $checks['sqlite'] = 'ok';
} catch (Exception $e) {
    $checks['sqlite'] = 'fail';
    $healthy = false;
}

// 2. MariaDB - WordPress database (read creds from wp-config.php)
try {
    $wpConfig = file_get_contents('/var/www/html/wp-config.php');
    preg_match("/define\(\s*'DB_USER',\s*'(.+?)'\s*\)/", $wpConfig, $userMatch);
    preg_match("/define\(\s*'DB_PASSWORD',\s*'(.+?)'\s*\)/", $wpConfig, $passMatch);
    preg_match("/define\(\s*'DB_NAME',\s*'(.+?)'\s*\)/", $wpConfig, $nameMatch);
    $mysqli = new mysqli('localhost', $userMatch[1], $passMatch[1], $nameMatch[1]);
    if ($mysqli->connect_error) {
        throw new Exception('connection failed');
    }
    $mysqli->query('SELECT 1');
    $mysqli->close();
    $checks['mariadb'] = 'ok';
} catch (Exception $e) {
    $checks['mariadb'] = 'fail';
    $healthy = false;
}

// 3. Disk space - fail if root filesystem > 90% used
$diskFree = disk_free_space('/');
$diskTotal = disk_total_space('/');
$diskUsedPct = round((1 - $diskFree / $diskTotal) * 100, 1);
if ($diskUsedPct > 90) {
    $checks['disk'] = 'fail';
    $healthy = false;
} else {
    $checks['disk'] = 'ok';
}

// 4. Apache - verify httpd process is running
$httpdRunning = !empty(shell_exec('pgrep httpd 2>/dev/null'));
if ($httpdRunning) {
    $checks['apache'] = 'ok';
} else {
    $checks['apache'] = 'fail';
    $healthy = false;
}

http_response_code($healthy ? 200 : 503);
echo json_encode(['healthy' => $healthy, 'checks' => $checks]);
