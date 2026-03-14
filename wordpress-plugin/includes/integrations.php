<?php
/**
 * Sample Integrations for BMI Calculator
 * This file demonstrates how other plugins (WooCommerce, BuddyPress) can hook into the BMI Calculator.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WooCommerce Integration: Suggest a product based on Diet Type or BMI category.
 */
function bmi_calculator_woocommerce_upsell( $attributes ) {
    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }

    $diet_type = isset( $attributes['dietType'] ) ? $attributes['dietType'] : 'balanced';
    $product_name = '';
    $product_link = '#'; // Placeholder

    switch ( $diet_type ) {
        case 'keto':
            $product_name = __( 'Keto Electrolytes & MCT Oil', 'bmi-calculator-block' );
            break;
        case 'high-protein':
            $product_name = __( 'Premium Whey Protein Isolate', 'bmi-calculator-block' );
            break;
        case 'paleo':
            $product_name = __( 'Organic Grass-Fed Collagen', 'bmi-calculator-block' );
            break;
        default:
            $product_name = __( 'Daily Multivitamin & Omega-3', 'bmi-calculator-block' );
            break;
    }

    echo '<div class="bmi-upsell-box" style="margin-top: 20px; padding: 15px; border: 1px solid #e0e0e0; border-radius: 8px; background: #fafafa;">';
    echo '<h4>' . esc_html__( 'Recommended for your goals:', 'bmi-calculator-block' ) . '</h4>';
    echo '<p>' . sprintf( esc_html__( 'Support your %s diet with our %s.', 'bmi-calculator-block' ), '<strong>' . esc_html( ucfirst( $diet_type ) ) . '</strong>', '<strong>' . esc_html( $product_name ) . '</strong>' ) . '</p>';
    echo '<a href="' . esc_url( $product_link ) . '" class="btn-primary" style="display:inline-block; text-decoration:none; font-size:14px; padding: 8px 15px;">' . esc_html__( 'View Product', 'bmi-calculator-block' ) . '</a>';
    echo '</div>';
}
add_action( 'bmi_calculator_after_results', 'bmi_calculator_woocommerce_upsell' );

/**
 * BuddyPress Integration: Post BMI activity to the user's stream (optional).
 */
function bmi_calculator_buddypress_activity( $record ) {
    if ( ! function_exists( 'bp_is_active' ) || ! bp_is_active( 'activity' ) ) {
        return;
    }

    $user_id = isset( $record['user_id'] ) ? $record['user_id'] : 0;
    if ( ! $user_id ) {
        return;
    }

    $bmi = isset( $record['bmi'] ) ? $record['bmi'] : 0;
    $category = bmi_getBMICategory( $bmi );

    bp_activity_add( array(
        'user_id'   => $user_id,
        'action'    => sprintf( __( '%s just calculated their BMI: %s (%s)', 'bmi-calculator-block' ), bp_core_get_userlink( $user_id ), $bmi, $category ),
        'component' => 'bmi_calculator',
        'type'      => 'bmi_recorded',
    ) );
}
add_action( 'bmi_calculator_record_saved', 'bmi_calculator_buddypress_activity' );

/**
 * Filter macro defaults for WooCommerce "Fitness" category products.
 * Example of how to override defaults based on external logic.
 */
function bmi_calculator_override_macros_for_athletes( $defaults ) {
    // If we're on a specific page or have a specific user role, we could change the defaults.
    if ( current_user_can( 'administrator' ) ) {
        // $defaults['protein'] = 45; // Just an example
    }
    return $defaults;
}
add_filter( 'bmi_calculator_macro_defaults', 'bmi_calculator_override_macros_for_athletes' );
