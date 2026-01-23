<?php
require_once __DIR__ . '/db.php';

$result = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hide_id'])) {
    hideRecord((int) $_POST['hide_id']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $surname = trim($_POST['surname'] ?? '');
    $weightKg = floatval($_POST['weight_kg'] ?? 0);
    $heightCm = floatval($_POST['height_cm'] ?? 0);

    if ($name === '' || $surname === '' || $weightKg <= 0 || $heightCm <= 0) {
        $error = 'All fields are required and must be valid positive numbers.';
    } else {
        $result = saveRecord($name, $surname, $weightKg, $heightCm);
    }
}

$visitorCount = incrementVisitorCount();
$records = getRecords();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BMI Calculator</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>BMI Calculator</h1>

        <?php if ($error): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($result): ?>
            <div class="alert success">
                Your BMI is <strong><?= $result['bmi'] ?></strong> (<?= $result['category'] ?>)
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" required
                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="surname">Surname</label>
                <input type="text" id="surname" name="surname" required
                       value="<?= htmlspecialchars($_POST['surname'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="weight_kg">Weight (kg)</label>
                <input type="number" id="weight_kg" name="weight_kg" step="0.1" min="1" required
                       value="<?= htmlspecialchars($_POST['weight_kg'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="height_cm">Height (cm)</label>
                <input type="number" id="height_cm" name="height_cm" step="0.1" min="1" required
                       value="<?= htmlspecialchars($_POST['height_cm'] ?? '') ?>">
            </div>
            <button type="submit">Calculate BMI</button>
        </form>

        <?php if (!empty($records)): ?>
            <h2>Previous Records</h2>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Surname</th>
                        <th>Weight (kg)</th>
                        <th>Height (cm)</th>
                        <th>BMI</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $rec): ?>
                        <tr>
                            <td><?= htmlspecialchars($rec['name']) ?></td>
                            <td><?= htmlspecialchars($rec['surname']) ?></td>
                            <td><?= $rec['weight_kg'] ?></td>
                            <td><?= $rec['height_cm'] ?></td>
                            <td><?= $rec['bmi'] ?></td>
                            <td><?= $rec['created_at'] ?></td>
                            <td>
                                <form method="POST" style="margin:0">
                                    <input type="hidden" name="hide_id" value="<?= $rec['id'] ?>">
                                    <button type="submit" class="btn-hide">Hide</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <div class="visitor-counter">
            Visitors: <?= $visitorCount ?>
        </div>
    </div>
</body>
</html>
