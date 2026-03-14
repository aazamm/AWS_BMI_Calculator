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
require_once BMI_BLOCK_DIR . 'includes/leads.php';
require_once BMI_BLOCK_DIR . 'includes/admin.php';
require_once BMI_BLOCK_DIR . 'includes/integrations.php';

/**
 * Enhancement 2: Create leads table on plugin activation.
 */
function bmi_calculator_activate() {
    bmi_create_leads_table();
}
register_activation_hook( __FILE__, 'bmi_calculator_activate' );

/**
 * Register the block and its assets.
 */
function bmi_calculator_block_init() {
    load_plugin_textdomain( 'bmi-calculator-block', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

    register_block_type( BMI_BLOCK_DIR . 'block/block.json', array(
        'render_callback' => 'bmi_calculator_block_render',
    ) );

    // Enhancement 3: Register shortcode
    add_shortcode( 'bmi_calculator', 'bmi_calculator_shortcode_handler' );
}
add_action( 'init', 'bmi_calculator_block_init' );

/**
 * Enqueue front-end assets when the block is present.
 */
function bmi_calculator_block_enqueue_assets() {
    if ( ! has_block( 'bmi-calculator/bmi-block' ) ) {
        return;
    }
    bmi_calculator_enqueue_frontend_assets();
}
add_action( 'wp_enqueue_scripts', 'bmi_calculator_block_enqueue_assets' );

/**
 * Register editor assets.
 */
function bmi_calculator_block_editor_assets() {
    wp_enqueue_script(
        'bmi-calculator-editor',
        BMI_BLOCK_URL . 'block/editor.js',
        array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components' ),
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
 * Enhancement 3: Shortcode handler.
 * Accepts same attributes as block (snake_case mapped to camelCase).
 */
function bmi_calculator_shortcode_handler( $atts ) {
    $atts = shortcode_atts( array(
        'primary_color'  => '#0073aa',
        'show_history'   => 'true',
        'show_converter' => 'true',
        'macro_protein'  => 30,
        'macro_carbs'    => 40,
        'macro_fats'     => 30,
        'diet_type'      => 'balanced',
    ), $atts, 'bmi_calculator' );

    // Map snake_case shortcode attributes to camelCase block attributes
    $block_attributes = array(
        'primaryColor'  => $atts['primary_color'],
        'showHistory'   => filter_var( $atts['show_history'], FILTER_VALIDATE_BOOLEAN ),
        'showConverter' => filter_var( $atts['show_converter'], FILTER_VALIDATE_BOOLEAN ),
        'macroProtein'  => intval( $atts['macro_protein'] ),
        'macroCarbs'    => intval( $atts['macro_carbs'] ),
        'macroFats'     => intval( $atts['macro_fats'] ),
        'dietType'      => sanitize_text_field( $atts['diet_type'] ),
    );

    // Enqueue front-end assets (same as block)
    bmi_calculator_enqueue_frontend_assets();

    return bmi_calculator_block_render( $block_attributes, '' );
}

/**
 * Shared function to enqueue front-end assets.
 */
function bmi_calculator_enqueue_frontend_assets() {
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

    wp_localize_script( 'bmi-functionality', 'bmiCalcI18n', array(
        'underweight'     => __( 'Underweight', 'bmi-calculator-block' ),
        'normal'          => __( 'Normal weight', 'bmi-calculator-block' ),
        'overweight'      => __( 'Overweight', 'bmi-calculator-block' ),
        'obese'           => __( 'Obese', 'bmi-calculator-block' ),
        'noRecords'       => __( 'No records to export.', 'bmi-calculator-block' ),
        'validationError' => __( 'Please enter valid positive numbers for weight, height, and age.', 'bmi-calculator-block' ),
        'pleaseAgree'     => __( 'Please agree to receive emails first.', 'bmi-calculator-block' ),
        'validEmail'      => __( 'Please enter a valid email address.', 'bmi-calculator-block' ),
        'networkError'    => __( 'Network error. Please try again.', 'bmi-calculator-block' ),
        'darkMode'        => __( 'Dark Mode', 'bmi-calculator-block' ),
        'lightMode'       => __( 'Light Mode', 'bmi-calculator-block' ),
        'protein'         => __( 'Protein', 'bmi-calculator-block' ),
        'carbs'           => __( 'Carbs', 'bmi-calculator-block' ),
        'fats'            => __( 'Fats', 'bmi-calculator-block' ),
    ) );

    // Enhancement 4: Inject WP user data if logged in
    if ( is_user_logged_in() ) {
        $user    = wp_get_current_user();
        $history = get_user_meta( $user->ID, 'bmi_history', true );
        if ( ! is_array( $history ) ) {
            $history = array();
        }
        wp_add_inline_script( 'bmi-functionality', 'window.BMI_WP_USER = ' . wp_json_encode( array(
            'id'      => $user->ID,
            'name'    => $user->display_name,
            'email'   => $user->user_email,
            'history' => $history,
        ) ) . ';', 'before' );
    }

    wp_enqueue_style(
        'bmi-style',
        BMI_BLOCK_URL . 'assets/css/bmi-style.css',
        array(),
        '1.0.0'
    );
}

/**
 * Server-side render callback for the BMI Calculator block.
 */
function bmi_calculator_block_render( $attributes, $content ) {
    $visitorCount = bmi_incrementVisitorCount();

    // Enhancement 5: Allow plugins to override macro defaults
    $macro_defaults = apply_filters( 'bmi_calculator_macro_defaults', array(
        'protein' => isset( $attributes['macroProtein'] ) ? $attributes['macroProtein'] : 30,
        'carbs'   => isset( $attributes['macroCarbs'] ) ? $attributes['macroCarbs'] : 40,
        'fats'    => isset( $attributes['macroFats'] ) ? $attributes['macroFats'] : 30,
    ) );

    // Enhancement 1: Read block attributes with defaults
    $primary_color = isset( $attributes['primaryColor'] ) ? sanitize_hex_color( $attributes['primaryColor'] ) : '#0073aa';
    $show_history  = isset( $attributes['showHistory'] ) ? (bool) $attributes['showHistory'] : true;
    $show_converter = isset( $attributes['showConverter'] ) ? (bool) $attributes['showConverter'] : true;

    ob_start();

    // Enhancement 5: Before render hook
    do_action( 'bmi_calculator_before_render', $attributes );
    ?>
    <div class="bmi-calculator-wrap"
         data-primary-color="<?php echo esc_attr( $primary_color ); ?>"
         data-show-history="<?php echo $show_history ? 'true' : 'false'; ?>"
         data-show-converter="<?php echo $show_converter ? 'true' : 'false'; ?>"
         data-macro-protein="<?php echo esc_attr( $macro_defaults['protein'] ); ?>"
         data-macro-carbs="<?php echo esc_attr( $macro_defaults['carbs'] ); ?>"
         data-macro-fats="<?php echo esc_attr( $macro_defaults['fats'] ); ?>"
         data-diet-type="<?php echo esc_attr( isset( $attributes['dietType'] ) ? $attributes['dietType'] : 'balanced' ); ?>"
         style="--bmi-accent-color: <?php echo esc_attr( $primary_color ); ?>; --bmi-accent-hover: <?php echo esc_attr( $primary_color ); ?>cc;">
        <div class="container">
            <a class="skip-link" href="#bmi-calcBtn"><?php esc_html_e( 'Skip to calculator', 'bmi-calculator-block' ); ?></a>
            <div class="theme-toggle">
                <button id="bmi-themeBtn" aria-label="<?php esc_attr_e( 'Toggle dark mode', 'bmi-calculator-block' ); ?>">🌙 <?php esc_html_e( 'Dark Mode', 'bmi-calculator-block' ); ?></button>
            </div>
            <h1><?php esc_html_e( 'BMI Calculator', 'bmi-calculator-block' ); ?></h1>

            <?php
            // Enhancement 5: Form fields filter
            $form_fields_html = '
            <div class="form-group">
                <label for="bmi-name">' . esc_html__( 'Name', 'bmi-calculator-block' ) . '</label>
                <input type="text" id="bmi-name" placeholder="' . esc_attr__( 'Enter your name', 'bmi-calculator-block' ) . '" aria-label="' . esc_attr__( 'Your name', 'bmi-calculator-block' ) . '">
            </div>
            <div class="form-group">
                <label for="bmi-surname">' . esc_html__( 'Surname', 'bmi-calculator-block' ) . '</label>
                <input type="text" id="bmi-surname" placeholder="' . esc_attr__( 'Enter your surname', 'bmi-calculator-block' ) . '" aria-label="' . esc_attr__( 'Your surname', 'bmi-calculator-block' ) . '">
            </div>

            <div class="form-row">
                <div class="form-group half-width">
                    <label for="bmi-age">' . esc_html__( 'Age', 'bmi-calculator-block' ) . '</label>
                    <input type="number" id="bmi-age" min="1" max="120" placeholder="e.g. 25" aria-label="' . esc_attr__( 'Age in years', 'bmi-calculator-block' ) . '" aria-required="true">
                </div>
                <div class="form-group half-width">
                    <label for="bmi-gender">' . esc_html__( 'Gender', 'bmi-calculator-block' ) . '</label>
                    <select id="bmi-gender" aria-label="' . esc_attr__( 'Gender', 'bmi-calculator-block' ) . '">
                        <option value="male">' . esc_html__( 'Male', 'bmi-calculator-block' ) . '</option>
                        <option value="female">' . esc_html__( 'Female', 'bmi-calculator-block' ) . '</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="bmi-activity">' . esc_html__( 'Activity Level', 'bmi-calculator-block' ) . '</label>
                <select id="bmi-activity" aria-label="' . esc_attr__( 'Activity level', 'bmi-calculator-block' ) . '">
                    <option value="1.2">' . esc_html__( 'Sedentary (little or no exercise)', 'bmi-calculator-block' ) . '</option>
                    <option value="1.375">' . esc_html__( 'Lightly active (light exercise/sports 1-3 days/week)', 'bmi-calculator-block' ) . '</option>
                    <option value="1.55">' . esc_html__( 'Moderately active (moderate exercise/sports 3-5 days/week)', 'bmi-calculator-block' ) . '</option>
                    <option value="1.725">' . esc_html__( 'Very active (hard exercise/sports 6-7 days a week)', 'bmi-calculator-block' ) . '</option>
                    <option value="1.9">' . esc_html__( 'Extra active (very hard exercise/sports & physical job)', 'bmi-calculator-block' ) . '</option>
                </select>
            </div>

            <div class="form-group">
                <label for="bmi-dietType">' . esc_html__( 'Diet Type', 'bmi-calculator-block' ) . '</label>
                <select id="bmi-dietType" aria-label="' . esc_attr__( 'Diet type', 'bmi-calculator-block' ) . '">
                    <option value="balanced">' . esc_html__( 'Balanced', 'bmi-calculator-block' ) . '</option>
                    <option value="keto">' . esc_html__( 'Keto', 'bmi-calculator-block' ) . '</option>
                    <option value="paleo">' . esc_html__( 'Paleo', 'bmi-calculator-block' ) . '</option>
                    <option value="high-protein">' . esc_html__( 'High Protein', 'bmi-calculator-block' ) . '</option>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group half-width">
                    <label for="bmi-mainWeight">' . esc_html__( 'Weight', 'bmi-calculator-block' ) . '</label>
                    <div class="input-group">
                        <input type="number" id="bmi-mainWeight" step="0.1" min="1" placeholder="e.g. 70" aria-label="' . esc_attr__( 'Weight value', 'bmi-calculator-block' ) . '" aria-required="true">
                        <select id="bmi-weightUnit" aria-label="' . esc_attr__( 'Weight unit', 'bmi-calculator-block' ) . '">
                            <option value="kg">kg</option>
                            <option value="lbs">lbs</option>
                        </select>
                    </div>
                </div>
                <div class="form-group half-width">
                    <label for="bmi-mainHeight">' . esc_html__( 'Height', 'bmi-calculator-block' ) . '</label>
                    <div class="input-group">
                        <input type="number" id="bmi-mainHeight" step="0.1" min="1" placeholder="e.g. 175" aria-label="' . esc_attr__( 'Height value', 'bmi-calculator-block' ) . '" aria-required="true">
                        <select id="bmi-heightUnit" aria-label="' . esc_attr__( 'Height unit', 'bmi-calculator-block' ) . '">
                            <option value="cm">cm</option>
                            <option value="in">' . esc_html__( 'inches', 'bmi-calculator-block' ) . '</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="button-group">
                <button id="bmi-calcBtn" class="btn-primary" aria-label="' . esc_attr__( 'Calculate BMI', 'bmi-calculator-block' ) . '">' . esc_html__( 'Calculate', 'bmi-calculator-block' ) . '</button>
                <button id="bmi-clearBtn" class="btn-secondary" aria-label="' . esc_attr__( 'Clear form', 'bmi-calculator-block' ) . '">' . esc_html__( 'Clear', 'bmi-calculator-block' ) . '</button>
            </div>';
            echo apply_filters( 'bmi_calculator_form_fields', $form_fields_html );
            ?>

            <?php
            // Enhancement 5: Results HTML filter
            $results_html = '
            <div id="bmi-resultsArea" class="results-grid hidden" role="status" aria-live="polite" tabindex="-1">
                <div class="result-card" id="bmi-bmiCard">
                    <h4>' . esc_html__( 'Body Mass Index', 'bmi-calculator-block' ) . '</h4>
                    <div class="result-value" id="bmi-bmiResult">--</div>
                    <div class="gauge-container" role="img" aria-label="' . esc_attr__( 'BMI gauge showing current value', 'bmi-calculator-block' ) . '">
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
                    <h4>' . esc_html__( 'Health Status', 'bmi-calculator-block' ) . '</h4>
                    <div class="result-value" id="bmi-commentResult" style="font-size: 16px;">--</div>
                    <div class="ideal-weight" id="bmi-idealWeightArea">
                        ' . esc_html__( 'Ideal', 'bmi-calculator-block' ) . ': <span id="bmi-idealWeightRange">--</span>
                    </div>
                </div>
                <div class="result-card">
                    <h4>' . esc_html__( 'Basal Metabolic Rate (BMR)', 'bmi-calculator-block' ) . '</h4>
                    <div class="result-value" id="bmi-calInResult">--</div>
                    <div class="result-subtext">' . esc_html__( 'kcal / day (Resting)', 'bmi-calculator-block' ) . '</div>
                </div>
                <div class="result-card">
                    <h4>' . esc_html__( 'Daily Energy Needs (TDEE)', 'bmi-calculator-block' ) . '</h4>
                    <div class="result-value" id="bmi-calOutResult">--</div>
                    <div class="result-subtext">' . esc_html__( 'kcal / day (Active)', 'bmi-calculator-block' ) . '</div>
                </div>
                <div class="result-card full-width-card">
                    <h4>' . esc_html__( 'Daily Macronutrient Guide', 'bmi-calculator-block' ) . '</h4>
                    <div class="macros-grid">
                        <div class="macro-item">
                            <span class="macro-label" id="bmi-proteinLabel">' . esc_html__( 'Protein', 'bmi-calculator-block' ) . ' (' . esc_html( $macro_defaults['protein'] ) . '%)</span>
                            <span class="macro-value" id="bmi-proteinResult">--</span>
                        </div>
                        <div class="macro-item">
                            <span class="macro-label" id="bmi-carbsLabel">' . esc_html__( 'Carbs', 'bmi-calculator-block' ) . ' (' . esc_html( $macro_defaults['carbs'] ) . '%)</span>
                            <span class="macro-value" id="bmi-carbsResult">--</span>
                        </div>
                        <div class="macro-item">
                            <span class="macro-label" id="bmi-fatsLabel">' . esc_html__( 'Fats', 'bmi-calculator-block' ) . ' (' . esc_html( $macro_defaults['fats'] ) . '%)</span>
                            <span class="macro-value" id="bmi-fatsResult">--</span>
                        </div>
                    </div>
                </div>
            </div>';
            echo apply_filters( 'bmi_calculator_results_html', $results_html );
            ?>

            <?php
            // Enhancement 5: Specialized hook for product recommendations/upsells
            do_action( 'bmi_calculator_after_results', $attributes );
            ?>

            <!-- Enhancement 2: Lead capture form (shown after calculation) -->
            <div id="bmi-leadForm" class="bmi-lead-form hidden">
                <p><?php esc_html_e( 'Get personalized health tips — enter your email', 'bmi-calculator-block' ); ?></p>
                <div class="form-row">
                    <div class="form-group" style="flex:1;">
                        <input type="email" id="bmi-leadEmail" placeholder="your@email.com" aria-label="<?php esc_attr_e( 'Email address', 'bmi-calculator-block' ); ?>">
                    </div>
                </div>
                <label class="bmi-lead-optin">
                    <input type="checkbox" id="bmi-leadOptIn" aria-label="<?php esc_attr_e( 'Agree to receive health tips via email', 'bmi-calculator-block' ); ?>"> <?php esc_html_e( 'I agree to receive health tips via email', 'bmi-calculator-block' ); ?>
                </label>
                <button id="bmi-leadSubmit" class="btn-primary" style="margin-top:10px;"><?php esc_html_e( 'Subscribe', 'bmi-calculator-block' ); ?></button>
                <div id="bmi-leadMessage" class="bmi-lead-success hidden"></div>
            </div>

            <hr class="divider">

            <?php if ( $show_history ) : ?>
            <div id="bmi-historyArea" class="hidden">
                <div class="history-header">
                    <h2><?php esc_html_e( 'Previous Records', 'bmi-calculator-block' ); ?></h2>
                    <div class="user-filter">
                        <label for="bmi-userSelect"><?php esc_html_e( 'View records for:', 'bmi-calculator-block' ); ?></label>
                        <select id="bmi-userSelect">
                            <option value="all"><?php esc_html_e( 'All Users', 'bmi-calculator-block' ); ?></option>
                        </select>
                        <button id="bmi-exportBtn" class="btn-export"><?php esc_html_e( 'Export CSV', 'bmi-calculator-block' ); ?></button>
                    </div>
                </div>

                <div class="chart-container hidden" id="bmi-chartContainer">
                    <canvas id="bmi-bmiChart"></canvas>
                </div>

                <table id="bmi-historyTable">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Name', 'bmi-calculator-block' ); ?></th>
                            <th><?php esc_html_e( 'Weight', 'bmi-calculator-block' ); ?></th>
                            <th><?php esc_html_e( 'Height', 'bmi-calculator-block' ); ?></th>
                            <th><?php esc_html_e( 'BMI', 'bmi-calculator-block' ); ?></th>
                            <th><?php esc_html_e( 'Date', 'bmi-calculator-block' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="bmi-historyBody">
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <hr class="divider">

            <?php if ( $show_converter ) : ?>
            <details class="advanced-converter">
                <summary><?php esc_html_e( 'Advanced Real-Time Unit Converter', 'bmi-calculator-block' ); ?></summary>
                <div class="converter-wrapper">
                    <div class="converter-section">
                        <h3><?php esc_html_e( 'Weight Converter', 'bmi-calculator-block' ); ?></h3>
                        <div class="form-group"><label>Kilograms (kg)</label><input type="number" id="bmi-kgInput" step="0.0001"></div>
                        <div class="form-group"><label>Pounds (lbs)</label><input type="number" id="bmi-lbsInput" step="0.0001"></div>
                        <div class="form-group"><label>Grams (gm)</label><input type="number" id="bmi-gmInput" step="0.0001"></div>
                        <div class="form-group"><label>Ounces (oz)</label><input type="number" id="bmi-ozInput" step="0.0001"></div>
                    </div>
                    <div class="converter-section">
                        <h3><?php esc_html_e( 'Height Converter', 'bmi-calculator-block' ); ?></h3>
                        <div class="form-group"><label>Centimeters (cm)</label><input type="number" id="bmi-cmInput" step="0.0001"></div>
                        <div class="form-group"><label>Meters (m)</label><input type="number" id="bmi-mInput" step="0.0001"></div>
                        <div class="form-group"><label>Inches (in)</label><input type="number" id="bmi-inchInput" step="0.0001"></div>
                        <div class="form-group"><label>Feet (ft)</label><input type="number" id="bmi-ftInput" step="0.0001"></div>
                    </div>
                </div>
            </details>
            <?php endif; ?>

            <div class="visitor-counter">
                <?php echo esc_html__( 'Visitors', 'bmi-calculator-block' ) . ': ' . esc_html( $visitorCount ); ?>
            </div>
        </div>
    </div>
    <?php
    // Enhancement 5: After render hook
    do_action( 'bmi_calculator_after_render', $attributes );

    return ob_get_clean();
}
