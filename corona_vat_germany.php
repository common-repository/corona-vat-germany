<?php

/**
 * Plugin Name: Corona VAT Germany
 * Description: Adjusts the WooCommerce VAT rates (19% -> 16% / 7% -> 5%) for period beginning from 2020-07-01 and resets the tax rates after 2020-12-31.
 * Version: 1.0.0
 * Author: Chris Ebbinger
 * Author URI: https://ebbinger.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: cect
 * Domain Path: /languages
 */

function cect_reduce_taxes()
{
    cect_update_standard_tax_rates(16.0);
    cect_update_reduced_tax_rates(5.0);
}
add_action('cect_reduce_taxes', 'cect_reduce_taxes');

function cect_restore_taxes()
{
    cect_update_standard_tax_rates(19.0);
    cect_update_reduced_tax_rates(7.0);
}
add_action('cect_restore_taxes', 'cect_restore_taxes');

/**
 * Sets the standard tax rates to the given value
 * 
 * @param float $new_rate the new rate for standard ax tclasses
 */
function cect_update_standard_tax_rates($new_rate)
{
    $options = get_option('ce_corona_tax_page', false);
    $standard_tax_class_names = cect_standard_tax_class_names();
    $standard_tax_rates = array();

    foreach ($standard_tax_class_names as $tax_class_name) {
        $standard_tax_rates += WC_Tax::find_rates(array('country' => 'DE', 'tax_class' => $tax_class_name));
    }

    foreach ($standard_tax_rates as $tax_rate_id => $tax_rate) {
        $updated_tax_rate['tax_rate'] = $new_rate;
        WC_Tax::_update_tax_rate($tax_rate_id, $updated_tax_rate);
    }
}

/**
 * Sets the reduced tax rates to the given value
 * 
 * @param float $new_rate the new rate for reduced tax classes
 */
function cect_update_reduced_tax_rates($new_rate)
{
    $options = get_option('ce_corona_tax_page', false);
    $reduced_tax_class_names = cect_reduced_tax_class_names();
    $reduced_tax_rates = array();

    foreach ($reduced_tax_class_names as $tax_class_name) {
        $reduced_tax_rates += WC_Tax::find_rates(array('country' => 'DE', 'tax_class' => $tax_class_name));
    }

    foreach ($reduced_tax_rates as $tax_rate_id => $tax_rate) {
        $updated_tax_rate['tax_rate'] = $new_rate;
        WC_Tax::_update_tax_rate($tax_rate_id, $updated_tax_rate);
    }
}

function cect_standard_tax_class_names()
{
    $options = get_option('ce_corona_tax_page', false);
    return cect_parse_comma_separated_tax_class_names($options['cect_tax_class_standard']);
}

function cect_reduced_tax_class_names()
{
    $options = get_option('ce_corona_tax_page', false);
    return cect_parse_comma_separated_tax_class_names($options['cect_tax_class_reduced']);
}

function cect_parse_comma_separated_tax_class_names($comma_separated_text)
{
    $standard_placeholders = array(
        "''",
        "-",
        " ",
        "Standard",
        "standard"
    );
    $lines = cect_array_explode(array(',', ', '), $comma_separated_text);
    for ($i = 0; $i < count($lines); $i++) {
        $line = $lines[$i];
        if (in_array($line, $standard_placeholders, true)) {
            $lines[$i] = '';
        }
        $lines[$i] = trim($lines[$i]);
    }

    return $lines;
}

/**
 * @see https://stackoverflow.com/a/51746000/5753451
 */
function cect_array_explode($delimiters, $string)
{
    if (!is_array(($delimiters)) && !is_array($string)) {
        //if neither the delimiter nor the string are arrays
        return explode($delimiters, $string);
    } else if (!is_array($delimiters) && is_array($string)) {
        //if the delimiter is not an array but the string is
        foreach ($string as $item) {
            foreach (explode($delimiters, $item) as $sub_item) {
                $items[] = $sub_item;
            }
        }
        return $items;
    } else if (is_array($delimiters) && !is_array($string)) {
        //if the delimiter is an array but the string is not
        $string_array[] = $string;
        foreach ($delimiters as $delimiter) {
            $string_array = cect_array_explode($delimiter, $string_array);
        }
        return $string_array;
    }
}

function cect_schedule_events()
{
    wp_clear_scheduled_hook('cect_reduce_taxes');
    wp_clear_scheduled_hook('cect_restore_taxes');

    $options = get_option('ce_corona_tax_page', array(
        'cect_reduce_tax_date' => '2020-07-01',
        'cect_restore_tax_date' => '2021-01-01',
    ));

    $reduce_tax_date = strtotime($options['cect_reduce_tax_date']);
    $restore_tax_date = strtotime($options['cect_restore_tax_date']);

    $reduce_time_passed = strtotime('now') > $reduce_tax_date;
    $restore_time_passed = strtotime('now') > $restore_tax_date;

    if (!wp_next_scheduled('cect_reduce_taxes') && !$reduce_time_passed) {
        wp_schedule_single_event($reduce_tax_date, 'cect_reduce_taxes');
    }
    if (!wp_next_scheduled('cect_restore_taxes') && !$restore_time_passed) {
        wp_schedule_single_event($restore_tax_date, 'cect_restore_taxes');
    }

    $warning_texts = array();

    if ($reduce_time_passed) {
        do_action('cect_reduce_taxes');
        $warning_texts[] = __('Tax Reduce Date already passed and the automated action was executed immediately.', 'cect');
    }

    if ($restore_time_passed) {
        do_action('cect_reduce_taxes');
        $warning_texts[] = __('Tax Restore Date already passed and the automated action was executed immediately.', 'cect');
    }

    add_option('cect_warning', implode('<br>', $warning_texts));
}

function cect_show_notice($notice_text, $category)
{
?>
    <div class="notice notice-<?php echo $category; ?> is-dismissible">
        <p><?php echo $notice_text; ?></p>
    </div>
<?php
}

// Show previously accumulated warnings
function cect_display_notices()
{
    $warning_text = get_option('cect_warning', false);
    delete_option('cect_warning');

    if (!$warning_text) {
        return;
    }

    cect_show_notice($warning_text, 'warning');
}
add_action('woocommerce_init', 'cect_display_notices');


// Schedule changes
add_action('update_option_ce_corona_tax_page', 'cect_schedule_events');
add_action('update_option_ce_corona_tax_page', 'cect_schedule_events');

function cect_unschedule_events()
{
    wp_clear_scheduled_hook('cect_reduce_taxes');
    wp_clear_scheduled_hook('cect_restore_taxes');
}

// Activation Schedule
function cect_plugin_activate()
{
    cect_schedule_events();
}
register_activation_hook(__FILE__, 'cect_plugin_activate');

// Deactivation Unschedule
function cect_plugin_deactivate()
{
    cect_unschedule_events();
}
register_deactivation_hook(__FILE__, 'myplugin_deactivate');

/**
 * Create Admin Settings page
 */
require_once('includes/RationalOptionPages.php');
function cect_create_plugin_settings_page()
{
    $tax_classes = array_merge(array('' => 'Standard'), array_combine(WC_Tax::get_tax_classes(), WC_Tax::get_tax_classes()));
    $tax_classes_string = '<code>' . implode(', ', $tax_classes) . '</code>';

    $options = get_option('ce_corona_tax_page', false);
    $pages = array(
        'ce_corona_tax_page' => array(
            'menu_title' => __('Corona VAT Germany', 'cect'),
            'page_title' => __('Corona VAT Germany Settings', 'cect'),
            'parent_slug' => 'options-general.php',
            'menu_slug' => 'ce_corona_tax',
            'capabilities' => 'manage_woocommerce',
            'sections' => array(
                'general' => array(
                    'title' => __('General', 'cect'),
                    'text' => __('Select the tax classes that should be adjusted automatically. Separate multiple entries with a comma (<code>, </code>). The following classes are defined in WooCommerce:', 'cect') . '<p>' . $tax_classes_string . '</p>',
                    'fields' => array(
                        'cect_tax_class_standard' => array(
                            'id' => 'cect_tax_class_standard',
                            'title' => __('Standard Tax Classes<br>(previously 19%)', 'cect'),
                            'type' => 'text',
                        ),
                        'cect_tax_class_reduced' => array(
                            'id' => 'cect_tax_class_reduced',
                            'title' => __('Reduced Tax Classes<br>(previously 7%)', 'cect'),
                            'type' => 'text',
                        ),
                    ),
                ),
                'schedule' => array(
                    'title' => __('Schedule', 'cect'),
                    'text' => __('Choose the timing for the tax changes. The changes will be executed during the first visit of the website after the given time.', 'cect'),
                    'fields' => array(
                        'cect_reduce_tax_date' => array(
                            'id' => 'cect_reduce_tax_date',
                            'title' => __('Reduce Tax Date', 'cect'),
                            'text' > __('Choose when the tax rate will be reduced.', 'cect'),
                            'type' => 'date',
                            'value' => '2020-07-01',
                        ),
                        'cect_restore_tax_date' => array(
                            'id' => 'cect_restore_tax_date',
                            'title' => __('Restore Tax Date', 'cect'),
                            'text' > __('Choose when the original tax rate will be restored.', 'cect'),
                            'type' => 'date',
                            'value' => '2021-01-01',
                        ),
                    ),
                ),
            ),
        ),
    );
    $option_page = new RationalOptionPages($pages);
}
add_action('woocommerce_init', 'cect_create_plugin_settings_page');

// Add Options page link in plugin list
function cect_settings_link($links)
{
    $mylinks = array(
        '<a href="' . admin_url('options-general.php?page=ce_corona_tax') . '">' . __('Settings', 'cect') . '</a>',
    );
    return array_merge($links, $mylinks);
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'cect_settings_link');

/**
 * Load the translations
 */
function cect_load_plugin_textdomain()
{
    load_plugin_textdomain('cect', false, basename(__DIR__) . '/languages/');
}
add_action('plugins_loaded', 'cect_load_plugin_textdomain');
