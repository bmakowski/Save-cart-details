<?php 
/**
 * Plugin Name: Save Cart Details
 * Plugin URI: https://404blog.pl
 * Description: Save cart details for later.
 * Version: 1.0.0
 * Author: Bartek Makowski
 * Author URI: https://404blog.pl
 *
 * @package Save_Cart_Details
 */
// Add action for including scripts and styles.
add_action('wp_enqueue_scripts', 'scd_scripts');
/**
 * Register plugin scripts and stylesheets.
 *
 * @since    1.0.0
 */
function scd_scripts()
{
    wp_enqueue_style('scd', plugin_dir_url(__FILE__) . 'scd.css', array(), time(), 'all');
}

/**
 * Save cart details in user meta from current cart.
 *
 * @since    1.0.0
 */
function scd_save_cart_details()
{
    global $woocommerce;

    // get user details
    $current_user = wp_get_current_user();

    if ( is_user_logged_in() )
    {
        $user_id = $current_user->ID;
        $cart_contents = $woocommerce->cart->get_cart();
        if( !empty( $cart_contents ) ){
            $time = time();
            $meta_value = $cart_contents;
            $saved_carts = get_user_meta( $user_id, 'saved_carts', true);
            if( !$saved_carts ){
                $saved_carts = [];
            }
            $saved_carts[$time] = $cart_contents;
            update_usermeta( $user_id, 'saved_carts', $saved_carts );
            return true;
        }
    }
    return false;
}

/**
 * Restore chosen cart details from user meta into current cart (clears current cart).
 *
 * @since    1.0.0
 */
function sdc_restore_cart_details( $selected_cart_key )
{
    global $woocommerce;

    $current_user = wp_get_current_user();
    if ( is_user_logged_in() )
    {
        $user_id = $current_user->ID;
        $saved_carts = get_user_meta( $user_id, 'saved_carts', true ) ;
        if( $saved_carts ){
            foreach( $saved_carts as $date => $cart_contents ){
                if( $cart_contents && $date == $selected_cart_key ){
                    // clear current cart
                    $woocommerce->cart->empty_cart();

                    // add cart contents
                    foreach ( $cart_contents as $cart_item_key => $values )
                    {
                        $id = $values['product_id'];
                        $quant = $values['quantity'];
                        $woocommerce->cart->add_to_cart( $id, $quant );
                    }
                    return true;
                }
            }
        }
        return false;
    }
}

/**
 * Delete chosen cart details from user meta.
 *
 * @since    1.0.0
 *
 * @param string $selected_cart_key 
 */
function sdc_delete_cart_details( $selected_cart_key ){
    global $woocommerce;

    $current_user = wp_get_current_user();
    if ( is_user_logged_in() )
    {
        $user_id = $current_user->ID;
        $saved_carts = get_user_meta( $user_id, 'saved_carts', true ) ;
        if( $saved_carts ){
            unset( $saved_carts[$selected_cart_key] );
            update_usermeta( $user_id, 'saved_carts', $saved_carts );
            return true;
        }
        return false;
    }
}

// Add action for generating "Save cart" button uunder cart table.
add_action( 'woocommerce_after_cart_table', 'scd_generate_button' );

/**
 * Generate "Save cart" button.
 *
 * @since    1.0.0
 */
function scd_generate_button(){
    echo '<div class="scd-buttons"><a class="checkout-button button" href="' . site_url() . '/scd-save-cart">Save cart</a></div>';
}

// Add "Saved carts" tables under Cart totals on checkout and on empty checkout page.
add_action( 'woocommerce_after_cart', 'scd_generate_table' );
add_action( 'woocommerce_cart_is_empty', 'scd_generate_table', 11);

/**
 * Generate "Saved carts" table
 *
 * @since    1.0.0
 */
function scd_generate_table(){
    echo '<div class="scd-cart-section"><h2>Saved carts</h2><table class="scd-carts"><tr><th>Saved on</th><th>Action</th><th>Delete</th></tr>';
    $current_user = wp_get_current_user();
    $saved_carts = get_user_meta( $current_user->ID, 'saved_carts', true);
    if( $saved_carts ){
        foreach ( $saved_carts as $date => $cart_contents ){
            echo '<tr><td class="text-center">' . date('M d Y', $date ) . '</td><td class="text-center"><a href="' . site_url() . '/scd-restore-cart/?scd-cart=' . $date .'">Restore</a></td><td class="text-center"><a href="' . site_url() . '/scd-delete-cart/?scd-cart=' . $date .'">Delete</a></td></tr>';
        }
    }else{
        echo '<tr><td class="text-center" colspan="3">No saved carts</td><tr>';
    }
    echo '</table></div>';
}

// Add revrite rules for the plugin.
add_action( 'init', 'scd_endpoint_rewrite_rules' );

/**
 * Rewrite rules for the plugin.
 *
 * @since    1.0.0
 */
function scd_endpoint_rewrite_rules(){
    add_rewrite_endpoint( 'scd-save-cart', EP_ROOT );
    add_rewrite_endpoint( 'scd-restore-cart', EP_ROOT );
    add_rewrite_endpoint( 'scd-delete-cart', EP_ROOT );
}

// Add revrite rules handling function.
add_action( 'template_redirect', 'handle_endpoint_functions' );

/**
 * Endpoint's handling functions.
 *
 * @since    1.0.0
 */
function handle_endpoint_functions(){
    
    // Save cart
    if( false !== get_query_var( 'scd-save-cart', false )  ) {
        if( scd_save_cart_details() ){
            wc_add_notice( 'Cart saved successfully.' );
        }
        wp_redirect( wc_get_cart_url() );
        exit();
    }

    // Restore cart
    if( false !== get_query_var( 'scd-restore-cart', false ) && !empty( get_query_var( 'scd-cart', false )) ) {  
        $cart_key = get_query_var( 'scd-cart' );
        if( sdc_restore_cart_details( $cart_key ) ){
            wc_add_notice( 'Cart restored successfully.' );
        }
        wp_redirect( wc_get_cart_url() );
        exit();
    }

    // Delete saved carts
    if( false !== get_query_var( 'scd-delete-cart', false ) && !empty( get_query_var( 'scd-cart', false )) ) {  
        $cart_key = get_query_var( 'scd-cart' );
        if( sdc_delete_cart_details( $cart_key ) ){
            wc_add_notice( 'Cart deleted successfully.' );
        }
        wp_redirect( wc_get_cart_url() );
        exit();
    }
}

// Add query vars.
add_filter( 'query_vars', 'scd_query_vars');

/**
 * Define custom query vars for the plugin.
 *
 * @since    1.0.0
 */
function scd_query_vars( $vars ){
    $vars[] = "scd-cart";
    return $vars;
}