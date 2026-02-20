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

    $result = bmi_saveRecord( $name, $surname ?: '', $weight_kg, $height_cm );

    return new WP_REST_Response( array(
        'success' => true,
        'record'  => $result,
    ), 200 );
}

function bmi_calculator_get_history( WP_REST_Request $request ) {
    $records = bmi_getRecords();
    return new WP_REST_Response( $records, 200 );
}
