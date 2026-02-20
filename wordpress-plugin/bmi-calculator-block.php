<?php
/**
 * Plugin Name: BMI Calculator Block
 * Description: BMI, BMR, TDEE and Macros calculator with history tracking as a Gutenberg block.
 * Version: 1.0.0
 * Author: Aaron Zammit
 * License: GPL-2.0-or-later
 * Text Domain: bmi-calculator-block
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'BMI_BLOCK_DIR', plugin_dir_path( __FILE__ ) );
define( 'BMI_BLOCK_URL', plugin_dir_url( __FILE__ ) );
define( 'BMI_DB_PATH', '/var/www/html/bmi/bmi_data.db' );

require_once BMI_BLOCK_DIR . 'includes/db.php';

/**
 * Register the block and its assets.
 */
function bmi_calculator_block_init() {
    register_block_type( BMI_BLOCK_DIR . 'block/block.json', array(
        'render_callback' => 'bmi_calculator_block_render',
    ) );
}
add_action( 'init', 'bmi_calculator_block_init' );

/**
 * Enqueue front-end assets when the block is present.
 */
function bmi_calculator_block_enqueue_assets() {
    if ( ! has_block( 'bmi-calculator/bmi-block' ) ) {
        return;
    }

    wp_enqueue_script(
        'chart-js',
        'https://cdn.jsdelivr.net/npm/chart.js',
        array(),
        '4.4.0',
        true
    );

    wp_enqueue_script(
        'bmi-functionality',
        BMI_BLOCK_URL . 'assets/js/bmi-functionality.js',
        array( 'chart-js' ),
        '1.0.0',
        true
    );

    wp_localize_script( 'bmi-functionality', 'bmiCalcData', array(
        'restUrl' => rest_url( 'bmi-calculator/v1/' ),
        'nonce'   => wp_create_nonce( 'wp_rest' ),
    ) );

    wp_enqueue_style(
        'bmi-style',
        BMI_BLOCK_URL . 'assets/css/bmi-style.css',
        array(),
        '1.0.0'
    );
}
add_action( 'wp_enqueue_scripts', 'bmi_calculator_block_enqueue_assets' );

/**
 * Register editor assets.
 */
function bmi_calculator_block_editor_assets() {
    wp_enqueue_script(
        'bmi-calculator-editor',
        BMI_BLOCK_URL . 'block/editor.js',
        array( 'wp-blocks', 'wp-element', 'wp-block-editor' ),
        '1.0.0',
        true
    );
}
add_action( 'enqueue_block_editor_assets', 'bmi_calculator_block_editor_assets' );

/**
 * Register REST API routes.
 */
function bmi_calculator_rest_api_init() {
    require_once BMI_BLOCK_DIR . 'includes/rest-api.php';
    bmi_calculator_register_routes();
}
add_action( 'rest_api_init', 'bmi_calculator_rest_api_init' );

/**
 * Server-side render callback for the BMI Calculator block.
 */
function bmi_calculator_block_render( $attributes, $content ) {
    $visitorCount = bmi_incrementVisitorCount();

    ob_start();
    ?>
    <div class="bmi-calculator-wrap">
        <div class="container">
            <div class="theme-toggle">
                <button id="bmi-themeBtn">🌙 Dark Mode</button>
            </div>
            <h1>BMI Calculator</h1>

            <div class="form-group">
                <label for="bmi-name">Name</label>
                <input type="text" id="bmi-name" placeholder="Enter your name">
            </div>
            <div class="form-group">
                <label for="bmi-surname">Surname</label>
                <input type="text" id="bmi-surname" placeholder="Enter your surname">
            </div>

            <div class="form-row">
                <div class="form-group half-width">
                    <label for="bmi-age">Age</label>
                    <input type="number" id="bmi-age" min="1" max="120" placeholder="e.g. 25">
                </div>
                <div class="form-group half-width">
                    <label for="bmi-gender">Gender</label>
                    <select id="bmi-gender">
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="bmi-activity">Activity Level</label>
                <select id="bmi-activity">
                    <option value="1.2">Sedentary (little or no exercise)</option>
                    <option value="1.375">Lightly active (light exercise/sports 1-3 days/week)</option>
                    <option value="1.55">Moderately active (moderate exercise/sports 3-5 days/week)</option>
                    <option value="1.725">Very active (hard exercise/sports 6-7 days a week)</option>
                    <option value="1.9">Extra active (very hard exercise/sports & physical job)</option>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group half-width">
                    <label for="bmi-mainWeight">Weight</label>
                    <div class="input-group">
                        <input type="number" id="bmi-mainWeight" step="0.1" min="1" placeholder="e.g. 70">
                        <select id="bmi-weightUnit">
                            <option value="kg">kg</option>
                            <option value="lbs">lbs</option>
                        </select>
                    </div>
                </div>
                <div class="form-group half-width">
                    <label for="bmi-mainHeight">Height</label>
                    <div class="input-group">
                        <input type="number" id="bmi-mainHeight" step="0.1" min="1" placeholder="e.g. 175">
                        <select id="bmi-heightUnit">
                            <option value="cm">cm</option>
                            <option value="in">inches</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="button-group">
                <button id="bmi-calcBtn" class="btn-primary">Calculate</button>
                <button id="bmi-clearBtn" class="btn-secondary">Clear</button>
            </div>

            <div id="bmi-resultsArea" class="results-grid hidden">
                <div class="result-card" id="bmi-bmiCard">
                    <h4>Body Mass Index</h4>
                    <div class="result-value" id="bmi-bmiResult">--</div>
                    <div class="gauge-container">
                        <div class="gauge-bar">
                            <div id="bmi-gaugePointer" class="gauge-pointer"></div>
                        </div>
                        <div class="gauge-labels">
                            <span style="flex: 1; text-align: left;">15</span>
                            <span style="flex: 1; text-align: center;">25</span>
                            <span style="flex: 1; text-align: right;">40</span>
                        </div>
                    </div>
                </div>
                <div class="result-card" id="bmi-commentCard">
                    <h4>Health Status</h4>
                    <div class="result-value" id="bmi-commentResult" style="font-size: 16px;">--</div>
                    <div class="ideal-weight" id="bmi-idealWeightArea">
                        Ideal: <span id="bmi-idealWeightRange">--</span>
                    </div>
                </div>
                <div class="result-card">
                    <h4>Basal Metabolic Rate (BMR)</h4>
                    <div class="result-value" id="bmi-calInResult">--</div>
                    <div class="result-subtext">kcal / day (Resting)</div>
                </div>
                <div class="result-card">
                    <h4>Daily Energy Needs (TDEE)</h4>
                    <div class="result-value" id="bmi-calOutResult">--</div>
                    <div class="result-subtext">kcal / day (Active)</div>
                </div>
                <div class="result-card full-width-card">
                    <h4>Daily Macronutrient Guide</h4>
                    <div class="macros-grid">
                        <div class="macro-item">
                            <span class="macro-label">Protein (30%)</span>
                            <span class="macro-value" id="bmi-proteinResult">--</span>
                        </div>
                        <div class="macro-item">
                            <span class="macro-label">Carbs (40%)</span>
                            <span class="macro-value" id="bmi-carbsResult">--</span>
                        </div>
                        <div class="macro-item">
                            <span class="macro-label">Fats (30%)</span>
                            <span class="macro-value" id="bmi-fatsResult">--</span>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="divider">

            <div id="bmi-historyArea" class="hidden">
                <div class="history-header">
                    <h2>Previous Records</h2>
                    <div class="user-filter">
                        <label for="bmi-userSelect">View records for:</label>
                        <select id="bmi-userSelect">
                            <option value="all">All Users</option>
                        </select>
                        <button id="bmi-exportBtn" class="btn-export">Export CSV</button>
                    </div>
                </div>

                <div class="chart-container hidden" id="bmi-chartContainer">
                    <canvas id="bmi-bmiChart"></canvas>
                </div>

                <table id="bmi-historyTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Weight</th>
                            <th>Height</th>
                            <th>BMI</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody id="bmi-historyBody">
                    </tbody>
                </table>
            </div>

            <hr class="divider">

            <details class="advanced-converter">
                <summary>Advanced Real-Time Unit Converter</summary>
                <div class="converter-wrapper">
                    <div class="converter-section">
                        <h3>Weight Converter</h3>
                        <div class="form-group"><label>Kilograms (kg)</label><input type="number" id="bmi-kgInput" step="0.0001"></div>
                        <div class="form-group"><label>Pounds (lbs)</label><input type="number" id="bmi-lbsInput" step="0.0001"></div>
                        <div class="form-group"><label>Grams (gm)</label><input type="number" id="bmi-gmInput" step="0.0001"></div>
                        <div class="form-group"><label>Ounces (oz)</label><input type="number" id="bmi-ozInput" step="0.0001"></div>
                    </div>
                    <div class="converter-section">
                        <h3>Height Converter</h3>
                        <div class="form-group"><label>Centimeters (cm)</label><input type="number" id="bmi-cmInput" step="0.0001"></div>
                        <div class="form-group"><label>Meters (m)</label><input type="number" id="bmi-mInput" step="0.0001"></div>
                        <div class="form-group"><label>Inches (in)</label><input type="number" id="bmi-inchInput" step="0.0001"></div>
                        <div class="form-group"><label>Feet (ft)</label><input type="number" id="bmi-ftInput" step="0.0001"></div>
                    </div>
                </div>
            </details>

            <div class="visitor-counter">
                Visitors: <?php echo esc_html( $visitorCount ); ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
