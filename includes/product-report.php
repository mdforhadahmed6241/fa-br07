<?php
/**
 * Product Report Page Functionality for Business Report Plugin
 *
 * @package BusinessReport
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Include the new tab files
require_once BR_PLUGIN_DIR . 'includes/tabs/product-summary-tab.php';
require_once BR_PLUGIN_DIR . 'includes/tabs/product-list-tab.php';

/**
 * Renders the main Product Report page.
 */
function br_product_report_page_html() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) return;
    
    // Default to 'product_list' tab since summary is not yet built
    $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'product_list';
    ?>
    <div class="wrap br-wrap">
        <div class="br-header">
            <h1><?php _e( 'Product Report', 'business-report' ); ?></h1>
        </div>

        <h2 class="nav-tab-wrapper">
            <a href="?page=br-product-report&tab=summary" class="nav-tab <?php echo $active_tab == 'summary' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Summary', 'business-report' ); ?></a>
            <a href="?page=br-product-report&tab=product_list" class="nav-tab <?php echo $active_tab == 'product_list' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Product List (COGS)', 'business-report' ); ?></a>
        </h2>
        <div class="br-page-content">
        <?php
        switch ( $active_tab ) {
            case 'summary':
                br_product_summary_tab_html();
                break;
            case 'product_list':
                br_product_list_tab_html();
                break;
            default:
                br_product_list_tab_html();
                break;
        }
        ?>
        </div>
    </div>
    <?php
}
