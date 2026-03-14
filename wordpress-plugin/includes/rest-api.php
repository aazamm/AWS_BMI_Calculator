<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bmi_calculator_register_routes() {
    register_rest_route( 'bmi-calculator/v1', '/record', array(
        'methods'             => 'POST',
        'callback'            => 'bmi_calculator_save_record',
        'permission_callback' => '__return_true',
    ) );

    register_rest_route( 'bmi-calculator/v1', '/history', array(
        'methods'             => 'GET',
        'callback'            => 'bmi_calculator_get_history',
        'permission_callback' => '__return_true',
    ) );

    // Enhancement 2: Lead capture endpoint
    register_rest_route( 'bmi-calculator/v1', '/lead', array(
        'methods'             => 'POST',
        'callback'            => 'bmi_calculator_save_lead_endpoint',
        'permission_callback' => '__return_true',
    ) );

    // Enhancement 4: User-specific record saving (requires login)
    register_rest_route( 'bmi-calculator/v1', '/user-record', array(
        'methods'             => 'POST',
        'callback'            => 'bmi_calculator_save_user_record',
        'permission_callback' => function() {
            return is_user_logged_in();
        },
    ) );

    // Enhancement 4: User history from user meta
    register_rest_route( 'bmi-calculator/v1', '/user-history', array(
        'methods'             => 'GET',
        'callback'            => 'bmi_calculator_get_user_history',
        'permission_callback' => function() {
            return is_user_logged_in();
        },
    ) );
}

function bmi_calculator_save_record( WP_REST_Request $request ) {
    $name      = sanitize_text_field( $request->get_param( 'name' ) );
    $surname   = sanitize_text_field( $request->get_param( 'surname' ) );
    $weight_kg = floatval( $request->get_param( 'weight_kg' ) );
    $height_cm = floatval( $request->get_param( 'height_cm' ) );

    if ( empty( $name ) ) {
        $name = 'Anonymous';
    }

    if ( $weight_kg <= 0 || $height_cm <= 0 ) {
        return new WP_REST_Response( array(
            'success' => false,
            'error'   => 'Weight and height must be positive numbers.',
        ), 400 );
    }

    // Enhancement 5: Filter record data before saving
    $record_data = apply_filters( 'bmi_calculator_record_data', array(
        'name'      => $name,
        'surname'   => $surname,
        'weight_kg' => $weight_kg,
        'height_cm' => $height_cm,
    ) );

    $result = bmi_saveRecord( $record_data['name'], $record_data['surname'], $record_data['weight_kg'], $record_data['height_cm'] );

    // Enhancement 5: Fire record saved action
    $record = array_merge( $record_data, $result );
    do_action( 'bmi_calculator_record_saved', $record );

    return new WP_REST_Response( array(
        'success' => true,
        'record'  => $result,
    ), 200 );
}

function bmi_calculator_get_history( WP_REST_Request $request ) {
    $records = bmi_getRecords();
    return new WP_REST_Response( $records, 200 );
}

/**
 * Enhancement 2: Save lead endpoint
 */
function bmi_calculator_save_lead_endpoint( WP_REST_Request $request ) {
    $email = sanitize_email( $request->get_param( 'email' ) );
    $name  = sanitize_text_field( $request->get_param( 'name' ) );
    $bmi   = floatval( $request->get_param( 'bmi' ) );

    if ( ! is_email( $email ) ) {
        return new WP_REST_Response( array(
            'success' => false,
            'error'   => 'Please enter a valid email address.',
        ), 400 );
    }

    if ( function_exists( 'bmi_save_lead' ) ) {
        bmi_save_lead( $email, $name, $bmi );
    }

    // Enhancement 2: Send email results to the user
    $subject = sprintf( __( 'Your BMI Results - %s', 'bmi-calculator-block' ), get_bloginfo( 'name' ) );
    $category = bmi_getBMICategory( $bmi );
    $message  = sprintf( __( 'Hello %s,', 'bmi-calculator-block' ), $name ) . "\n\n";
    $message .= __( 'Thank you for using our BMI Calculator. Here are your results:', 'bmi-calculator-block' ) . "\n\n";
    $message .= sprintf( __( 'BMI: %s', 'bmi-calculator-block' ), number_format( $bmi, 2 ) ) . "\n";
    $message .= sprintf( __( 'Status: %s', 'bmi-calculator-block' ), $category ) . "\n\n";
    $message .= __( 'For a more detailed health analysis and personalized tips, please visit our website.', 'bmi-calculator-block' ) . "\n\n";
    $message .= __( 'Best regards,', 'bmi-calculator-block' ) . "\n";
    $message .= get_bloginfo( 'name' );

    $headers = array( 'Content-Type: text/plain; charset=UTF-8' );
    wp_mail( $email, $subject, $message, $headers );

    $lead_data = array(
        'email' => $email,
        'name'  => $name,
        'bmi'   => $bmi,
    );

    // Enhancement 5: Fire lead captured action
    do_action( 'bmi_calculator_lead_captured', $lead_data );

    return new WP_REST_Response( array(
        'success' => true,
        'message' => 'Thank you! You have been subscribed.',
    ), 200 );
}

/**
 * Enhancement 4: Save record for logged-in user (SQLite + user meta)
 */
function bmi_calculator_save_user_record( WP_REST_Request $request ) {
    $user      = wp_get_current_user();
    $weight_kg = floatval( $request->get_param( 'weight_kg' ) );
    $height_cm = floatval( $request->get_param( 'height_cm' ) );
    $tdee      = floatval( $request->get_param( 'tdee' ) );
    $diet_type = sanitize_text_field( $request->get_param( 'diet_type' ) );

    if ( $weight_kg <= 0 || $height_cm <= 0 ) {
        return new WP_REST_Response( array(
            'success' => false,
            'error'   => 'Weight and height must be positive numbers.',
        ), 400 );
    }

    $name    = $user->display_name ?: 'User';
    $surname = '';

    // Enhancement 5: Filter record data before saving
    $record_data = apply_filters( 'bmi_calculator_record_data', array(
        'name'      => $name,
        'surname'   => $surname,
        'weight_kg' => $weight_kg,
        'height_cm' => $height_cm,
        'user_id'   => $user->ID,
    ) );

    // Save to SQLite
    $result = bmi_saveRecord( $record_data['name'], $record_data['surname'], $record_data['weight_kg'], $record_data['height_cm'] );

    // Save to user meta
    $height_m = $height_cm / 100;
    $bmi      = round( $weight_kg / ( $height_m * $height_m ), 2 );
    $history  = get_user_meta( $user->ID, 'bmi_history', true );
    if ( ! is_array( $history ) ) {
        $history = array();
    }
    $history_record = array(
        'weight_kg'  => $weight_kg,
        'height_cm'  => $height_cm,
        'bmi'        => $bmi,
        'category'   => bmi_getBMICategory( $bmi ),
        'tdee'       => $tdee,
        'diet_type'  => $diet_type,
        'created_at' => current_time( 'mysql' ),
    );
    $history[] = $history_record;
    update_user_meta( $user->ID, 'bmi_history', $history );

    // Enhancement 5: Fire record saved action
    $record = array_merge( $record_data, $result, array( 'bmi' => $bmi ) );
    do_action( 'bmi_calculator_record_saved', $record );

    return new WP_REST_Response( array(
        'success' => true,
        'record'  => $result,
    ), 200 );
}

/**
 * Enhancement 4: Get user history from user meta
 */
function bmi_calculator_get_user_history( WP_REST_Request $request ) {
    $user    = wp_get_current_user();
    $history = get_user_meta( $user->ID, 'bmi_history', true );
    if ( ! is_array( $history ) ) {
        $history = array();
    }
    return new WP_REST_Response( $history, 200 );
}
