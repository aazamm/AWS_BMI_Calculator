<?php
/**
 * Enhancement 2: Lead Generation — database functions
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Create the leads table on plugin activation.
 */
function bmi_create_leads_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bmi_leads';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        name VARCHAR(100) DEFAULT '',
        bmi DECIMAL(5,2) DEFAULT NULL,
        source VARCHAR(50) DEFAULT 'bmi_calculator',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}

/**
 * Save a lead record.
 */
function bmi_save_lead( $email, $name = '', $bmi = null ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bmi_leads';

    return $wpdb->insert(
        $table_name,
        array(
            'email'      => sanitize_email( $email ),
            'name'       => sanitize_text_field( $name ),
            'bmi'        => $bmi ? floatval( $bmi ) : null,
            'source'     => 'bmi_calculator',
            'created_at' => current_time( 'mysql' ),
        ),
        array( '%s', '%s', '%f', '%s', '%s' )
    );
}

/**
 * Get leads with pagination.
 */
function bmi_get_leads( $page = 1, $per_page = 20 ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bmi_leads';
    $offset = ( $page - 1 ) * $per_page;

    $leads = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ),
        ARRAY_A
    );

    $total = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );

    return array(
        'leads' => $leads,
        'total' => (int) $total,
        'pages' => ceil( $total / $per_page ),
    );
}

/**
 * Export leads as CSV download.
 */
function bmi_export_leads_csv() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Unauthorized' );
    }

    if ( ! isset( $_GET['bmi_export_leads'] ) || $_GET['bmi_export_leads'] !== '1' ) {
        return;
    }

    check_admin_referer( 'bmi_export_leads_csv' );

    global $wpdb;
    $table_name = $wpdb->prefix . 'bmi_leads';
    $leads = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY created_at DESC", ARRAY_A );

    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=bmi-leads-' . date( 'Y-m-d' ) . '.csv' );

    $output = fopen( 'php://output', 'w' );
    fputcsv( $output, array( 'ID', 'Email', 'Name', 'BMI', 'Source', 'Date' ) );

    foreach ( $leads as $lead ) {
        fputcsv( $output, array(
            $lead['id'],
            $lead['email'],
            $lead['name'],
            $lead['bmi'],
            $lead['source'],
            $lead['created_at'],
        ) );
    }

    fclose( $output );
    exit;
}
add_action( 'admin_init', 'bmi_export_leads_csv' );
