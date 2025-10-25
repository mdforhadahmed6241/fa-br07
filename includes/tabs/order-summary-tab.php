<?php
/**
 * Order Report Summary Tab Content
 *
 * @package BusinessReport
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Renders the HTML for the custom date range modal.
 */
function br_order_summary_custom_range_filter_modal_html() {
    $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
    $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';
    ?>
    <div id="br-order-custom-range-filter-modal" class="br-modal" style="display: none;">
        <div class="br-modal-content">
            <button class="br-modal-close">&times;</button>
            <h3><?php _e('Select Custom Date Range', 'business-report'); ?></h3>
            <p><?php _e('Filter the report by a specific date range.', 'business-report'); ?></p>
            <form id="br-order-custom-range-filter-form" method="GET">
                <input type="hidden" name="page" value="br-order-report">
                <input type="hidden" name="tab" value="summary">
                <div class="form-row">
                    <div>
                        <label for="br_order_filter_start_date"><?php _e('Start Date', 'business-report'); ?></label>
                        <input type="text" id="br_order_filter_start_date" name="start_date" class="br-datepicker" value="<?php echo esc_attr($start_date); ?>" autocomplete="off" required>
                    </div>
                    <div>
                        <label for="br_order_filter_end_date"><?php _e('End Date', 'business-report'); ?></label>
                        <input type="text" id="br_order_filter_end_date" name="end_date" class="br-datepicker" value="<?php echo esc_attr($end_date); ?>" autocomplete="off" required>
                    </div>
                </div>
                <div class="form-footer">
                    <div></div>
                    <div>
                        <button type="button" class="button br-modal-cancel"><?php _e('Cancel', 'business-report'); ?></button>
                        <button type="submit" class="button button-primary"><?php _e('Apply Filter', 'business-report'); ?></button>
                    </div>
                </div>
            </form>
        </div>
    </div>
<?php }


/**
 * Renders the date filter buttons and dropdown.
 */
function br_order_summary_render_date_filters_html($current_tab = 'summary') {
    $current_range_key = isset($_GET['range']) ? sanitize_key($_GET['range']) : 'today';
    $start_date_get = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : null;
    $end_date_get = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : null;
    $is_custom_range = !empty($start_date_get) && !empty($end_date_get);

    $filters_main = ['today' => 'Today', 'yesterday' => 'Yesterday', 'last_7_days' => '7D', 'last_30_days' => '30D'];
    $filters_dropdown = ['this_month' => 'This Month', 'this_year' => 'This Year', 'lifetime' => 'Lifetime', 'custom' => 'Custom Range'];
    ?>
    <div class="br-filters">
        <div class="br-date-filters">
            <?php
            foreach($filters_main as $key => $label) {
                $is_active = ($current_range_key === $key) && !$is_custom_range;
                echo sprintf('<a href="?page=br-order-report&tab=%s&range=%s" class="button %s">%s</a>', esc_attr($current_tab), esc_attr($key), $is_active ? 'active' : '', esc_html($label));
            }
            ?>
            <div class="br-dropdown">
                <button class="button br-dropdown-toggle <?php echo ($is_custom_range || in_array($current_range_key, array_keys($filters_dropdown))) ? 'active' : ''; ?>">...</button>
                <div class="br-dropdown-menu">
                    <?php
                    foreach($filters_dropdown as $key => $label) {
                        if ($key === 'custom') {
                            echo sprintf('<a href="#" id="br-order-custom-range-trigger">%s</a>', esc_html($label));
                        } else {
                            echo sprintf('<a href="?page=br-order-report&tab=%s&range=%s">%s</a>', esc_attr($current_tab), esc_attr($key), esc_html($label));
                        }
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}


/**
 * Main HTML function for the Order Summary Tab.
 */
function br_order_summary_tab_html() {
    global $wpdb;
    $orders_table = $wpdb->prefix . 'br_orders';
    $meta_summary_table = $wpdb->prefix . 'br_meta_ad_summary';
    $meta_accounts_table = $wpdb->prefix . 'br_meta_ad_accounts';

    if (!function_exists('br_get_date_range')) {
        // Fallback or dependency check for date range function
        require_once BR_PLUGIN_DIR . 'includes/meta-ads.php';
    }
    
    // Date Range Logic (Uses order_date)
    $current_range_key = isset($_GET['range']) ? sanitize_key($_GET['range']) : 'today';
    $start_date_get = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : null;
    $end_date_get = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : null;
    $is_custom_range = !empty($start_date_get) && !empty($end_date_get);

    $date_range = br_get_date_range($is_custom_range ? '' : $current_range_key, $start_date_get, $end_date_get);
    $start_date = $date_range['start'] . ' 00:00:00';
    $end_date = $date_range['end'] . ' 23:59:59';

    // Get stats for the selected period
    $current_stats_sql = $wpdb->prepare(
        "SELECT
            COUNT(id) AS total_orders,
            SUM(CASE WHEN is_converted = 1 THEN 1 ELSE 0 END) AS converted_orders,
            SUM(CASE WHEN is_converted = 0 THEN 1 ELSE 0 END) AS not_converted_orders,
            SUM(CASE WHEN is_converted = 1 THEN total_items ELSE 0 END) AS total_items,
            SUM(CASE WHEN is_converted = 1 THEN total_order_value ELSE 0 END) AS total_order_value,
            SUM(CASE WHEN is_converted = 1 THEN cogs_total ELSE 0 END) AS total_cogs,
            SUM(CASE WHEN is_converted = 1 THEN gross_profit ELSE 0 END) AS converted_gross_profit
         FROM {$orders_table}
         WHERE order_date BETWEEN %s AND %s",
        $start_date, $end_date
    );
    $current_stats = $wpdb->get_row( $current_stats_sql );

    // Get stats for the previous period comparison
    $start_obj = new DateTime($date_range['start']);
    $end_obj = new DateTime($date_range['end']);
    $interval = $start_obj->diff($end_obj);
    $days = $interval->days + 1;

    $previous_end_obj = ( clone $start_obj )->modify( '-1 day' );
    $previous_start_obj = ( clone $previous_end_obj )->modify( '-' . ($days - 1) . ' days' );

    $previous_start = $previous_start_obj->format( 'Y-m-d 00:00:00' );
    $previous_end = $previous_end_obj->format( 'Y-m-d 23:59:59' );


     $previous_stats_sql = $wpdb->prepare(
        "SELECT
            COUNT(id) AS total_orders,
            SUM(CASE WHEN is_converted = 1 THEN 1 ELSE 0 END) AS converted_orders,
            SUM(CASE WHEN is_converted = 0 THEN 1 ELSE 0 END) AS not_converted_orders,
            SUM(CASE WHEN is_converted = 1 THEN total_items ELSE 0 END) AS total_items,
            SUM(CASE WHEN is_converted = 1 THEN total_order_value ELSE 0 END) AS total_order_value,
            SUM(CASE WHEN is_converted = 1 THEN cogs_total ELSE 0 END) AS total_cogs,
            SUM(CASE WHEN is_converted = 1 THEN gross_profit ELSE 0 END) AS converted_gross_profit
         FROM {$orders_table}
         WHERE order_date BETWEEN %s AND %s",
        $previous_start, $previous_end
    );
    $previous_stats = $wpdb->get_row( $previous_stats_sql );
    
    // --- Ads Cost Calculation ---
    // Current Period Ads Cost (BDT)
    $current_ads_cost_bdt = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(s.spend_usd * a.usd_to_bdt_rate) 
         FROM {$meta_summary_table} s 
         JOIN {$meta_accounts_table} a ON s.account_fk_id = a.id 
         WHERE a.is_active = 1 AND s.report_date BETWEEN %s AND %s", 
        $date_range['start'], $date_range['end']
    ));
    $current_ads_cost_bdt = floatval($current_ads_cost_bdt);

    // Previous Period Ads Cost (BDT)
    $previous_ads_cost_bdt = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(s.spend_usd * a.usd_to_bdt_rate) 
         FROM {$meta_summary_table} s 
         JOIN {$meta_accounts_table} a ON s.account_fk_id = a.id 
         WHERE a.is_active = 1 AND s.report_date BETWEEN %s AND %s", 
        $previous_start_obj->format('Y-m-d'), $previous_end_obj->format('Y-m-d')
    ));
    $previous_ads_cost_bdt = floatval($previous_ads_cost_bdt);

    // --- Profit Calculation ---
    $current_gross_profit = $current_stats->converted_gross_profit ?? 0;
    $previous_gross_profit = $previous_stats->converted_gross_profit ?? 0;
    
    $current_net_profit = $current_gross_profit - $current_ads_cost_bdt;
    $previous_net_profit = $previous_gross_profit - $previous_ads_cost_bdt;

    // --- Cost Per Order Calculations ---
    $current_total_orders = $current_stats->total_orders ?? 0;
    $previous_total_orders = $previous_stats->total_orders ?? 0;
    $current_converted_orders = $current_stats->converted_orders ?? 0;
    $previous_converted_orders = $previous_stats->converted_orders ?? 0;

    $current_ads_cost_per_order = $current_total_orders > 0 ? $current_ads_cost_bdt / $current_total_orders : 0;
    $previous_ads_cost_per_order = $previous_total_orders > 0 ? $previous_ads_cost_bdt / $previous_total_orders : 0;
    
    $current_ads_cost_per_converted = $current_converted_orders > 0 ? $current_ads_cost_bdt / $current_converted_orders : 0;
    $previous_ads_cost_per_converted = $previous_converted_orders > 0 ? $previous_ads_cost_bdt / $previous_converted_orders : 0;


    br_order_summary_render_date_filters_html('summary');
    ?>
    <div class="br-kpi-grid">
        <?php
        br_display_kpi_card( 'Total Orders', $current_total_orders, $previous_total_orders, 'number' );
        br_display_kpi_card( 'Converted Orders', $current_converted_orders, $previous_converted_orders, 'number' );
        br_display_kpi_card( 'Not Converted Orders', $current_stats->not_converted_orders ?? 0, $previous_stats->not_converted_orders ?? 0, 'number' );
        
        br_display_kpi_card( 'Total Selling Value (Converted)', $current_stats->total_order_value ?? 0, $previous_stats->total_order_value ?? 0, 'price' );
        br_display_kpi_card( 'Total Item Cost (Converted)', $current_stats->total_cogs ?? 0, $previous_stats->total_cogs ?? 0, 'price' );
        br_display_kpi_card( 'Converted Order Gross Profit', $current_gross_profit, $previous_gross_profit, 'price' );
        
        br_display_kpi_card( 'Total Ads Cost (BDT)', $current_ads_cost_bdt, $previous_ads_cost_bdt, 'price' );
        br_display_kpi_card( 'Ads Cost Per Order', $current_ads_cost_per_order, $previous_ads_cost_per_order, 'price' );
        br_display_kpi_card( 'Ads Cost Per Converted Order', $current_ads_cost_per_converted, $previous_ads_cost_per_converted, 'price' );

        br_display_kpi_card( 'Net Profit (Gross - Ads)', $current_net_profit, $previous_net_profit, 'price' );
        br_display_kpi_card( 'Total Items Sold (Converted)', $current_stats->total_items ?? 0, $previous_stats->total_items ?? 0, 'number' );
        ?>
    </div>
    <?php
}
