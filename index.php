<?php
require_once __DIR__ . '/auth.php';
startSession();

$currentUser = getCurrentUser();

// Handle API requests (saving and fetching records)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (isset($input['action']) && $input['action'] === 'save') {
        $name = trim($input['name'] ?? '');
        $surname = trim($input['surname'] ?? '');
        $weightKg = floatval($input['weight_kg'] ?? 0);
        $heightCm = floatval($input['height_cm'] ?? 0);

        // Use authenticated user info if available
        if ($currentUser) {
            $name = $currentUser['display_name'];
            $surname = '';
        }

        if ($name !== '' && $weightKg > 0 && $heightCm > 0) {
            $gender = isset($input['gender']) ? trim($input['gender']) : null;
            $neckCm = isset($input['neck_cm']) ? floatval($input['neck_cm']) : null;
            $waistCm = isset($input['waist_cm']) ? floatval($input['waist_cm']) : null;
            $hipCm = isset($input['hip_cm']) ? floatval($input['hip_cm']) : null;
            $bodyFatPct = isset($input['body_fat_pct']) ? floatval($input['body_fat_pct']) : null;
            $whtr = isset($input['whtr']) ? floatval($input['whtr']) : null;
            if ($neckCm === 0.0) $neckCm = null;
            if ($waistCm === 0.0) $waistCm = null;
            if ($hipCm === 0.0) $hipCm = null;
            if ($bodyFatPct === 0.0) $bodyFatPct = null;
            if ($whtr === 0.0) $whtr = null;

            $tdee = isset($input['tdee']) ? floatval($input['tdee']) : null;
            $dietType = isset($input['diet_type']) ? trim($input['diet_type']) : null;
            if ($tdee === 0.0) $tdee = null;

            $result = saveRecord($name, $surname, $weightKg, $heightCm,
                $currentUser['id'] ?? null, $gender, $neckCm, $waistCm, $hipCm, $bodyFatPct, $whtr, $tdee, $dietType);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'record' => $result]);
            exit;
        }
    }

    if (isset($input['action']) && $input['action'] === 'set_goal' && $currentUser) {
        $goalKg = floatval($input['goal_weight_kg'] ?? 0);
        if ($goalKg > 0) {
            setGoalWeight($currentUser['id'], $goalKg);
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'get_history') {
    header('Content-Type: application/json');
    if ($currentUser) {
        echo json_encode(getRecordsByUser($currentUser['id']));
    } else {
        echo json_encode(getRecords());
    }
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'get_goal' && $currentUser) {
    header('Content-Type: application/json');
    echo json_encode(['goal_weight_kg' => getGoalWeight($currentUser['id'])]);
    exit;
}

$visitorCount = incrementVisitorCount();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BMI & Health Calculator</title>
    <link rel="stylesheet" href="style.css">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#0073aa">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="BMI Calc">
    <link rel="apple-touch-icon" href="icons/icon-192.png">
</head>
<body>
    <div class="container">
        <a class="skip-link" href="#calcBtn">Skip to calculator</a>
        <div class="top-bar">
            <div class="top-bar-left">
                <div class="theme-toggle">
                    <button id="themeBtn" aria-label="Toggle dark mode">Dark Mode</button>
                </div>
                <div class="lang-toggle">
                    <select id="langSelect" aria-label="Select language">
                        <option value="en">English</option>
                        <option value="es">Español</option>
                        <option value="fr">Français</option>
                    </select>
                </div>
            </div>
            <div class="auth-bar">
                <?php if ($currentUser): ?>
                    <span class="auth-user"><?= htmlspecialchars($currentUser['display_name']) ?></span>
                    <a href="login.php?action=logout" class="auth-link">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="auth-link">Login</a>
                    <a href="login.php?mode=signup" class="auth-link">Sign Up</a>
                <?php endif; ?>
            </div>
        </div>
        <h1 id="calcTitle">BMI Calculator</h1>

        <?php if (!$currentUser): ?>
        <div class="form-group">
            <label for="name" id="labelName">Name</label>
            <input type="text" id="name" placeholder="Enter your name" aria-label="Your name">
        </div>
        <div class="form-group">
            <label for="surname" id="labelSurname">Surname</label>
            <input type="text" id="surname" placeholder="Enter your surname" aria-label="Your surname">
        </div>
        <?php else: ?>
        <input type="hidden" id="name" value="<?= htmlspecialchars($currentUser['display_name']) ?>">
        <input type="hidden" id="surname" value="">
        <?php endif; ?>

        <div class="form-row">
            <div class="form-group half-width">
                <label for="age" id="labelAge">Age</label>
                <input type="number" id="age" min="1" max="120" placeholder="e.g. 25" aria-label="Age in years" aria-required="true">
            </div>
            <div class="form-group half-width">
                <label for="gender" id="labelGender">Gender</label>
                <select id="gender" aria-label="Gender">
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="activity" id="labelActivity">Activity Level</label>
            <select id="activity" aria-label="Activity level">
                <option value="1.2">Sedentary (little or no exercise)</option>
                <option value="1.375">Lightly active (light exercise/sports 1-3 days/week)</option>
                <option value="1.55">Moderately active (moderate exercise/sports 3-5 days/week)</option>
                <option value="1.725">Very active (hard exercise/sports 6-7 days a week)</option>
                <option value="1.9">Extra active (very hard exercise/sports & physical job)</option>
            </select>
        </div>

        <div class="form-group">
            <label for="dietType" id="labelDietType">Diet Type</label>
            <select id="dietType" aria-label="Diet type">
                <option value="balanced">Balanced</option>
                <option value="keto">Keto</option>
                <option value="paleo">Paleo</option>
                <option value="high-protein">High Protein</option>
            </select>
        </div>
        
        <div class="form-row">
            <div class="form-group half-width">
                <label for="mainWeight">Weight</label>
                <div class="input-group">
                    <input type="number" id="mainWeight" step="0.1" min="1" placeholder="e.g. 70" aria-label="Weight value" aria-required="true">
                    <select id="weightUnit" aria-label="Weight unit">
                        <option value="kg">kg</option>
                        <option value="lbs">lbs</option>
                    </select>
                </div>
            </div>
            <div class="form-group half-width">
                <label for="mainHeight">Height</label>
                <div class="input-group">
                    <input type="number" id="mainHeight" step="0.1" min="1" placeholder="e.g. 175" aria-label="Height value" aria-required="true">
                    <select id="heightUnit" aria-label="Height unit">
                        <option value="cm">cm</option>
                        <option value="in">inches</option>
                    </select>
                </div>
            </div>
        </div>

        <details class="body-measurements">
            <summary>Body Measurements (for Body Fat %)</summary>
            <div class="form-row">
                <div class="form-group half-width">
                    <label for="neckCirc">Neck Circumference (cm)</label>
                    <input type="number" id="neckCirc" step="0.1" min="1" placeholder="e.g. 38">
                </div>
                <div class="form-group half-width">
                    <label for="waistCirc">Waist Circumference (cm)</label>
                    <input type="number" id="waistCirc" step="0.1" min="1" placeholder="e.g. 85">
                </div>
            </div>
            <div class="form-group" id="hipGroup">
                <label for="hipCirc">Hip Circumference (cm) <span id="hipNote">(required for female)</span></label>
                <input type="number" id="hipCirc" step="0.1" min="1" placeholder="e.g. 95" aria-label="Hip circumference in centimeters">
            </div>
        </details>

        <?php if ($currentUser): ?>
        <div class="form-group" id="goalWeightGroup">
            <label for="goalWeight">Goal Weight</label>
            <div class="input-group">
                <input type="number" id="goalWeight" step="0.1" min="1" placeholder="e.g. 65">
                <select id="goalWeightUnit">
                    <option value="kg">kg</option>
                    <option value="lbs">lbs</option>
                </select>
            </div>
        </div>
        <?php endif; ?>

        <div class="button-group">
            <button id="calcBtn" class="btn-primary" aria-label="Calculate BMI">Calculate</button>
            <button id="clearBtn" class="btn-secondary" aria-label="Clear form">Clear</button>
        </div>

        <div id="resultsArea" class="results-grid hidden" role="status" aria-live="polite" tabindex="-1">
            <div class="result-card" id="bmiCard">
                <h4>Body Mass Index</h4>
                <div class="result-value" id="bmiResult">--</div>
                <div class="gauge-container" role="img" aria-label="BMI gauge showing current value">
                    <div class="gauge-bar">
                        <div id="gaugePointer" class="gauge-pointer"></div>
                    </div>
                    <div class="gauge-labels">
                        <span style="flex: 1; text-align: left;">15</span>
                        <span style="flex: 1; text-align: center;">25</span>
                        <span style="flex: 1; text-align: right;">40</span>
                    </div>
                </div>
            </div>
            <div class="result-card" id="commentCard">
                <h4>Health Status</h4>
                <div class="result-value" id="commentResult" style="font-size: 16px;">--</div>
                <div class="ideal-weight" id="idealWeightArea">
                    Ideal: <span id="idealWeightRange">--</span>
                </div>
            </div>
            <div class="result-card">
                <h4>Basal Metabolic Rate (BMR)</h4>
                <div class="result-value" id="calInResult">--</div>
                <div class="result-subtext">kcal / day (Resting)</div>
            </div>
            <div class="result-card">
                <h4>Daily Energy Needs (TDEE)</h4>
                <div class="result-value" id="calOutResult">--</div>
                <div class="result-subtext">kcal / day (Active)</div>
            </div>
            <div class="result-card full-width-card">
                <h4>Daily Macronutrient Guide</h4>
                <div class="macros-grid">
                    <div class="macro-item">
                        <span class="macro-label" id="proteinLabel">Protein (30%)</span>
                        <span class="macro-value" id="proteinResult">--</span>
                    </div>
                    <div class="macro-item">
                        <span class="macro-label" id="carbsLabel">Carbs (40%)</span>
                        <span class="macro-value" id="carbsResult">--</span>
                    </div>
                    <div class="macro-item">
                        <span class="macro-label" id="fatsLabel">Fats (30%)</span>
                        <span class="macro-value" id="fatsResult">--</span>
                    </div>
                </div>
            </div>
            <div class="result-card hidden" id="bodyFatCard">
                <h4>Body Fat % (Navy Method)</h4>
                <div class="result-value" id="bodyFatResult">--</div>
                <div class="result-subtext" id="bodyFatCategory">--</div>
            </div>
            <div class="result-card hidden" id="whtrCard">
                <h4>Waist-to-Height Ratio</h4>
                <div class="result-value" id="whtrResult">--</div>
                <div class="result-subtext" id="whtrCategory">--</div>
            </div>
            <div class="result-card hidden" id="velocityCard">
                <h4>Weight Velocity</h4>
                <div class="result-value" id="velocityResult">--</div>
                <div class="result-subtext">kg/week (last 4 entries)</div>
            </div>
            <div class="result-card hidden" id="projectionCard">
                <h4>Projected Goal Date</h4>
                <div class="result-value" id="projectionResult">--</div>
                <div class="result-subtext" id="projectionSubtext">Based on current trend</div>
            </div>
        </div>

        <div id="reportBtnArea" class="hidden" style="text-align: center; margin-top: 15px;">
            <button id="downloadReportBtn" class="btn-primary" style="max-width: 300px;">Download Health Report (PDF)</button>
        </div>

        <hr class="divider">

        <div id="historyArea" class="hidden">
            <div class="history-header">
                <h2>Previous Records</h2>
                <div class="user-filter">
                    <label for="userSelect">View records for:</label>
                    <select id="userSelect">
                        <option value="all">All Users</option>
                    </select>
                    <button id="exportBtn" class="btn-export">Export CSV</button>
                </div>
            </div>

            <div class="chart-container hidden" id="chartContainer">
                <canvas id="bmiChart"></canvas>
            </div>

            <table id="historyTable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Weight</th>
                        <th>Height</th>
                        <th>BMI</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody id="historyBody">
                    <!-- Records will be injected here -->
                </tbody>
            </table>
        </div>

        <hr class="divider">

        <details class="advanced-converter">
            <summary>Advanced Real-Time Unit Converter</summary>
            <div class="converter-wrapper">
                <div class="converter-section">
                    <h3>Weight Converter</h3>
                    <div class="form-group"><label>Kilograms (kg)</label><input type="number" id="kgInput" step="0.0001"></div>
                    <div class="form-group"><label>Pounds (lbs)</label><input type="number" id="lbsInput" step="0.0001"></div>
                    <div class="form-group"><label>Grams (gm)</label><input type="number" id="gmInput" step="0.0001"></div>
                    <div class="form-group"><label>Ounces (oz)</label><input type="number" id="ozInput" step="0.0001"></div>
                </div>
                <div class="converter-section">
                    <h3>Height Converter</h3>
                    <div class="form-group"><label>Centimeters (cm)</label><input type="number" id="cmInput" step="0.0001"></div>
                    <div class="form-group"><label>Meters (m)</label><input type="number" id="mInput" step="0.0001"></div>
                    <div class="form-group"><label>Inches (in)</label><input type="number" id="inchInput" step="0.0001"></div>
                    <div class="form-group"><label>Feet (ft)</label><input type="number" id="ftInput" step="0.0001"></div>
                </div>
            </div>
        </details>

        <div class="visitor-counter">
            Visitors: <?= $visitorCount ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
    <script>
        window.BMI_USER = <?= json_encode($currentUser ? [
            'id' => $currentUser['id'],
            'name' => $currentUser['display_name'],
            'email' => $currentUser['email'],
        ] : null) ?>;
    </script>
    <script src="lang.js"></script>
    <script src="functionality.js"></script>
    <script>
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('sw.js').catch(err => console.log('SW registration failed:', err));
    }
    </script>
</body>
</html>