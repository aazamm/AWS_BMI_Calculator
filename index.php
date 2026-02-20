<?php
require_once __DIR__ . '/db.php';

// Handle API requests (saving and fetching records)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['action']) && $input['action'] === 'save') {
        $name = trim($input['name'] ?? '');
        $surname = trim($input['surname'] ?? '');
        $weightKg = floatval($input['weight_kg'] ?? 0);
        $heightCm = floatval($input['height_cm'] ?? 0);

        if ($name !== '' && $weightKg > 0 && $heightCm > 0) {
            $result = saveRecord($name, $surname, $weightKg, $heightCm);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'record' => $result]);
            exit;
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'get_history') {
    header('Content-Type: application/json');
    echo json_encode(getRecords());
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
</head>
<body>
    <div class="container">
        <div class="theme-toggle">
            <button id="themeBtn">🌙 Dark Mode</button>
        </div>
        <h1>BMI Calculator</h1>

        <div class="form-group">
            <label for="name">Name</label>
            <input type="text" id="name" placeholder="Enter your name">
        </div>
        <div class="form-group">
            <label for="surname">Surname</label>
            <input type="text" id="surname" placeholder="Enter your surname">
        </div>

        <div class="form-row">
            <div class="form-group half-width">
                <label for="age">Age</label>
                <input type="number" id="age" min="1" max="120" placeholder="e.g. 25">
            </div>
            <div class="form-group half-width">
                <label for="gender">Gender</label>
                <select id="gender">
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="activity">Activity Level</label>
            <select id="activity">
                <option value="1.2">Sedentary (little or no exercise)</option>
                <option value="1.375">Lightly active (light exercise/sports 1-3 days/week)</option>
                <option value="1.55">Moderately active (moderate exercise/sports 3-5 days/week)</option>
                <option value="1.725">Very active (hard exercise/sports 6-7 days a week)</option>
                <option value="1.9">Extra active (very hard exercise/sports & physical job)</option>
            </select>
        </div>
        
        <div class="form-row">
            <div class="form-group half-width">
                <label for="mainWeight">Weight</label>
                <div class="input-group">
                    <input type="number" id="mainWeight" step="0.1" min="1" placeholder="e.g. 70">
                    <select id="weightUnit">
                        <option value="kg">kg</option>
                        <option value="lbs">lbs</option>
                    </select>
                </div>
            </div>
            <div class="form-group half-width">
                <label for="mainHeight">Height</label>
                <div class="input-group">
                    <input type="number" id="mainHeight" step="0.1" min="1" placeholder="e.g. 175">
                    <select id="heightUnit">
                        <option value="cm">cm</option>
                        <option value="in">inches</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="button-group">
            <button id="calcBtn" class="btn-primary">Calculate</button>
            <button id="clearBtn" class="btn-secondary">Clear</button>
        </div>

        <div id="resultsArea" class="results-grid hidden">
            <div class="result-card" id="bmiCard">
                <h4>Body Mass Index</h4>
                <div class="result-value" id="bmiResult">--</div>
                <div class="gauge-container">
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
                        <span class="macro-label">Protein (30%)</span>
                        <span class="macro-value" id="proteinResult">--</span>
                    </div>
                    <div class="macro-item">
                        <span class="macro-label">Carbs (40%)</span>
                        <span class="macro-value" id="carbsResult">--</span>
                    </div>
                    <div class="macro-item">
                        <span class="macro-label">Fats (30%)</span>
                        <span class="macro-value" id="fatsResult">--</span>
                    </div>
                </div>
            </div>
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
    <script src="functionality.js"></script>
</body>
</html>