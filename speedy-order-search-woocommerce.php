<?php
/*
Plugin Name: Speedy Order Search for WooCommerce
Description: Search WooCommerce orders quickly. Suitable for sites with a large number of orders and not using HPOS yet.
Version: 1.1
Author: Kaushik Somaiya
Text Domain: speedy-order-search-woocommerce
License: GPL2
*/


if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Add submenu page to WooCommerce menu
function sosw_add_submenu_page() {
    add_submenu_page(
        'woocommerce',
        __('Speedy Order Search', 'speedy-order-search-woocommerce'),
        __('Speedy Order Search', 'speedy-order-search-woocommerce'),
        'manage_woocommerce',
        'speedy-order-search',
        'sosw_render_admin_page'
    );
}
add_action('admin_menu', 'sosw_add_submenu_page');

// Render admin page content
function sosw_render_admin_page() {
    ?>
    <div class="wrap">
        <h2><?php echo esc_html__('Speedy Order Search', 'speedy-order-search-woocommerce'); ?></h2>
        <form id="speedy-order-search-form" method="post">
            <?php wp_nonce_field('speedy-order-search', 'sosw_nonce'); ?>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="search-field"><?php echo esc_html__('Search Field:', 'speedy-order-search-woocommerce'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="search-field" name="sosw_search_field" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="search-type"><?php echo esc_html__('Search Type:', 'speedy-order-search-woocommerce'); ?></label>
                        </th>
                        <td>
                            <select id="search-type" name="sosw_search_type">
                                <option value="order_id"><?php echo esc_html__('Order ID', 'speedy-order-search-woocommerce'); ?></option>
                                <option value="email"><?php echo esc_html__('Email Address', 'speedy-order-search-woocommerce'); ?></option>
                                <option value="billing_address"><?php echo esc_html__('Billing Address', 'speedy-order-search-woocommerce'); ?></option>
                                <option value="shipping_address"><?php echo esc_html__('Shipping Address', 'speedy-order-search-woocommerce'); ?></option>
                                <option value="phone"><?php echo esc_html__('Phone', 'speedy-order-search-woocommerce'); ?></option>
                                <option value="meta"><?php echo esc_html__('Any Meta', 'speedy-order-search-woocommerce'); ?></option>
                            </select>
                        </td>
                    </tr>
                </tbody>
            </table>
            <button type="submit" class="button button-primary"><?php echo esc_html__('Search', 'speedy-order-search-woocommerce'); ?></button>
        </form>
        <div class="loader" style="display:none;"></div>

        <div id="search-results"></div>
    </div>

    <style>
        #search-results table {
            width: 100%;
            border-collapse: collapse;
        }
        #search-results th,
        #search-results td {
            padding: 8px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .loader {
            border: 5px solid #f3f3f3; /* Light grey */
            border-top: 5px solid #3498db; /* Blue */
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 2s linear infinite;
            margin: auto;
            margin-top: 20px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .sosw_wpo{
            text-decoration:none;
            color:#bb0000;
        }
    </style>

    <script>
        jQuery(document).ready(function($) {
            $('#speedy-order-search-form').submit(function(e) {
                e.preventDefault();
                $('.loader').show();
                $('#search-results').html("");
                var soswFormData = $(this).serialize();
                $.ajax({
                    type: 'POST',
                    url: ajaxurl,
                    data: {
                        action: 'sosw_order_search',
                        soswFormData: soswFormData,
                        soswNonce: $('#sosw_nonce').val()
                    },
                    success: function(response) {
                        $('.loader').hide();
                        $('#search-results').html(response.data);
                    }
                });
            });
        });
    </script>
    <?php
}

// AJAX search function
add_action('wp_ajax_sosw_order_search', 'sosw_order_search_ajax');
function sosw_order_search_ajax() {
    if (!is_user_logged_in() || !current_user_can('manage_woocommerce')) {
        wp_send_json_error(__('Unauthorized access!', 'speedy-order-search-woocommerce'));
    }
    
    global $wpdb;

    // Verify nonce
    $soswNonce = sanitize_text_field( $_POST['soswNonce'] );
    if (!wp_verify_nonce($soswNonce, 'speedy-order-search')) {
        wp_send_json_error(__('Unauthorized request!', 'speedy-order-search-woocommerce'));
    }

    // Validate search data
    $soswFormData = wp_unslash( sanitize_text_field (urldecode($_POST['soswFormData'])) );

    if(isset($soswFormData)){
        parse_str($soswFormData, $soswSearchDataArray);
        $soswSearchField    = sanitize_text_field($soswSearchDataArray['sosw_search_field']);
        $soswSearchType     = sanitize_text_field($soswSearchDataArray['sosw_search_type']);
    }

    if(empty($soswSearchField) || empty($soswSearchType)){
        wp_send_json_error(__('Invalid search!', 'speedy-order-search-woocommerce'));
        wp_die();
    }

    // Perform search based on search type
    switch ($soswSearchType) {
        case 'email':
            $soswQuery = $wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_billing_email' AND meta_value LIKE %s", '%' . $soswSearchField . '%');
            break;
        case 'order_id':
            $soswQuery = $wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE post_id = %s", $soswSearchField);
            break;
        case 'billing_address':
            $soswQuery = $wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_billing_address_index' AND meta_value LIKE %s", '%' . $soswSearchField . '%');
            break;
        case 'shipping_address':
            $soswQuery = $wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_shipping_address_index' AND meta_value LIKE %s", '%' . $soswSearchField . '%');
            break;
        case 'phone':
            $soswQuery = $wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_billing_phone' AND meta_value LIKE %s", '%' . $soswSearchField . '%');
            break;
        case 'meta':
            $soswQuery = $wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_value LIKE %s", '%' . $soswSearchField . '%');
            break;
        default:
            wp_send_json_error(__('Invalid search type!', 'speedy-order-search-woocommerce'));
            break;
    }

    // Run the query and get the results
    $soswResults = $wpdb->get_results($soswQuery, ARRAY_N);
    $soswFinalResults = array();
    if ($soswResults) {
        foreach ($soswResults as $soswResult) {
            $soswFinalResults[] = $soswResult[0];
        }
        $soswFinalResults = array_unique($soswFinalResults);
        rsort($soswFinalResults);
    }

    // Output the results
    if (!empty($soswFinalResults)) {
        ob_start();
        ?>
        <br>
        <h3><?php echo esc_html__('Search results for "', 'speedy-order-search-woocommerce') . esc_html($soswSearchField) . esc_html__('"', 'speedy-order-search-woocommerce'); ?></h3>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php echo esc_html__('ID', 'speedy-order-search-woocommerce'); ?></th>
                    <th><?php echo esc_html__('Date', 'speedy-order-search-woocommerce'); ?></th>
                    <th><?php echo esc_html__('Email', 'speedy-order-search-woocommerce'); ?></th>
                    <th><?php echo esc_html__('Order Link', 'speedy-order-search-woocommerce'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($soswFinalResults as $soswResult) {
                    $soswOrder = wc_get_order($soswResult);
                    $soswCustomerEmail = $soswOrder->get_billing_email();
                    $soswOrderDate = $soswOrder->get_date_created(); ?>
                    <tr>
                        <td><?php echo esc_html($soswResult); ?></td>
                        <td><?php echo esc_html($soswOrderDate->date('F j, Y')); ?></td>
                        <td><?php echo esc_html($soswCustomerEmail); ?></td>
                        <td><a target="_blank" href="<?php echo esc_url(admin_url('post.php?post=' . $soswResult . '&action=edit')); ?>"><?php echo esc_html__('View Order', 'speedy-order-search-woocommerce'); ?></a></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        <?php
        $soswcontent = ob_get_clean();
        wp_send_json_success($soswcontent);
    } else {
        wp_send_json_error(__('No orders found.', 'speedy-order-search-woocommerce'));
    }
}