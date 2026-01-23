<?php
// Basic HTTP authentication - change these credentials
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'bmi2026!');

if (!isset($_SERVER['PHP_AUTH_USER']) ||
    $_SERVER['PHP_AUTH_USER'] !== ADMIN_USER ||
    $_SERVER['PHP_AUTH_PW'] !== ADMIN_PASS) {
    header('WWW-Authenticate: Basic realm="BMI Admin"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Unauthorized';
    exit;
}

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    if (isset($_POST['action']) && $id > 0) {
        if ($_POST['action'] === 'hide') {
            hideRecord($id);
        } elseif ($_POST['action'] === 'unhide') {
            unhideRecord($id);
        }
    }
}

$records = getAllRecords();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BMI Admin Panel</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>BMI Admin Panel</h1>
        <p>All records (<?= count($records) ?> total). Hidden records are greyed out.</p>

        <?php if (!empty($records)): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Surname</th>
                        <th>Weight (kg)</th>
                        <th>Height (cm)</th>
                        <th>BMI</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $rec): ?>
                        <tr class="<?= $rec['hidden'] ? 'hidden-row' : '' ?>">
                            <td><?= $rec['id'] ?></td>
                            <td><?= htmlspecialchars($rec['name']) ?></td>
                            <td><?= htmlspecialchars($rec['surname']) ?></td>
                            <td><?= $rec['weight_kg'] ?></td>
                            <td><?= $rec['height_cm'] ?></td>
                            <td><?= $rec['bmi'] ?></td>
                            <td><?= $rec['created_at'] ?></td>
                            <td><?= $rec['hidden'] ? 'Hidden' : 'Visible' ?></td>
                            <td>
                                <form method="POST" style="margin:0">
                                    <input type="hidden" name="id" value="<?= $rec['id'] ?>">
                                    <?php if ($rec['hidden']): ?>
                                        <input type="hidden" name="action" value="unhide">
                                        <button type="submit" class="btn-unhide">Unhide</button>
                                    <?php else: ?>
                                        <input type="hidden" name="action" value="hide">
                                        <button type="submit" class="btn-hide">Hide</button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No records yet.</p>
        <?php endif; ?>

        <p style="margin-top: 20px;"><a href="index.php">Back to BMI Calculator</a></p>
    </div>
</body>
</html>
