<?php
/**
 * Enhancement 2: Lead Generation — Admin page
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register admin menu page under Tools.
 */
function bmi_leads_admin_menu() {
    add_management_page(
        'BMI Leads',
        'BMI Leads',
        'manage_options',
        'bmi-leads',
        'bmi_leads_admin_page'
    );
}
add_action( 'admin_menu', 'bmi_leads_admin_menu' );

/**
 * Register settings for enabling/disabling lead capture.
 */
function bmi_leads_register_settings() {
    register_setting( 'general', 'bmi_enable_lead_capture', array(
        'type'              => 'boolean',
        'default'           => true,
        'sanitize_callback' => 'rest_sanitize_boolean',
    ) );

    add_settings_field(
        'bmi_enable_lead_capture',
        'BMI Lead Capture',
        'bmi_leads_settings_field_callback',
        'general',
        'default'
    );
}
add_action( 'admin_init', 'bmi_leads_register_settings' );

function bmi_leads_settings_field_callback() {
    $enabled = get_option( 'bmi_enable_lead_capture', true );
    echo '<label><input type="checkbox" name="bmi_enable_lead_capture" value="1" ' . checked( $enabled, true, false ) . '> Enable email lead capture on BMI Calculator</label>';
}

/**
 * Render the admin page with leads table.
 */
function bmi_leads_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $page     = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
    $per_page = 20;
    $result   = bmi_get_leads( $page, $per_page );
    $leads    = $result['leads'];
    $total    = $result['total'];
    $pages    = $result['pages'];

    $export_url = wp_nonce_url(
        admin_url( 'tools.php?page=bmi-leads&bmi_export_leads=1' ),
        'bmi_export_leads_csv'
    );

    ?>
    <div class="wrap">
        <h1>BMI Calculator Leads</h1>

        <p>
            <strong><?php echo esc_html( $total ); ?></strong> total leads captured.
            <a href="<?php echo esc_url( $export_url ); ?>" class="button button-secondary">Export CSV</a>
        </p>

        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:30%;">Email</th>
                    <th style="width:20%;">Name</th>
                    <th style="width:10%;">BMI</th>
                    <th style="width:15%;">Source</th>
                    <th style="width:25%;">Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $leads ) ) : ?>
                    <tr><td colspan="5">No leads captured yet.</td></tr>
                <?php else : ?>
                    <?php foreach ( $leads as $lead ) : ?>
                        <tr>
                            <td><?php echo esc_html( $lead['email'] ); ?></td>
                            <td><?php echo esc_html( $lead['name'] ); ?></td>
                            <td><?php echo esc_html( $lead['bmi'] ); ?></td>
                            <td><?php echo esc_html( $lead['source'] ); ?></td>
                            <td><?php echo esc_html( $lead['created_at'] ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ( $pages > 1 ) : ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links( array(
                        'base'      => add_query_arg( 'paged', '%#%' ),
                        'format'    => '',
                        'current'   => $page,
                        'total'     => $pages,
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                    ) );
                    ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
