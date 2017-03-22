<?php

/**
 * Plugin Name: Brute Force Login Protection
 * Plugin URI: http://wordpress.org/plugins/brute-force-login-protection/
 * Description: Protects your website against brute force login attacks using .htaccess
 * Text Domain: brute-force-login-protection
 * Author: Fresh-Media
 * Author URI: http://fresh-media.nl/
 * Version: 2.0.0
 * License: MIT
 *
 * The MIT License (MIT)
 *
 * Copyright (c) 2014-2017 Fresh-Media
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

use BFLP\Action\LoginFailed;
use BFLP\Action\ShowSettingsPage;
use BFLP\DataAccess\Attempts;
use BFLP\DataAccess\Settings;
use BFLP\Util\RemoteAddress;
use BFLP\Util\Renderer;
use HtaccessFirewall\Filesystem\Exception\FilesystemException;
use HtaccessFirewall\HtaccessFirewall;

require_once __DIR__ . '/vendor/autoload.php';

/**
 * @return HtaccessFirewall
 */
function htaccess_firewall()
{
    static $htaccess = null;
    if ($htaccess === null) {
        $htaccess = new HtaccessFirewall(get_home_path() . '.htaccess');
    }
    return $htaccess;
}

/*
 * Hooks
 */

// Hook activation and deactivation actions
register_activation_hook(__FILE__, 'bflp_activate');
register_deactivation_hook(__FILE__, 'bflp_deactivate');

// Hook internationalization action
add_action('plugins_loaded', 'bflp_load_textdomain');

// Hook admin actions
add_action('admin_init', 'bflp_register_settings');
add_action('admin_menu', 'bflp_add_settings_page');
add_action('admin_notices', 'bflp_show_requirements_errors');

// Hook login form actions
add_action('wp_login_failed', 'bflp_login_failed');
add_action('wp_login', 'bflp_login_succeeded');

// Hook auth cookie actions
add_action('auth_cookie_bad_username', 'bflp_login_failed');
add_action('auth_cookie_bad_hash', 'bflp_login_failed');

/**
 * Activate the plugin.
 */
function bflp_activate()
{
    global $wpdb;

    // From 2.0.0 the attempts are stored in a separate table.
    delete_option('bflp_login_attempts');

    $table_name = $wpdb->prefix . 'bflp_login_attempts';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
              id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
              ip VARCHAR(50) NOT NULL,
              count INT NOT NULL,
              last_failed_at DATETIME NOT NULL
            ) $charset;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    try {
        htaccess_firewall()->reactivate();
    } catch (FilesystemException $ex) {
        //
    }
}

/**
 * Deactivate the plugin.
 */
function bflp_deactivate()
{
    try {
        htaccess_firewall()->deactivate();
    } catch (FilesystemException $ex) {
        //
    }
}

/**
 * Load textdomain for i18n.
 */
function bflp_load_textdomain()
{
    load_plugin_textdomain(
        'brute-force-login-protection',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );
}

/**
 * Add settings page menu item to the Settings menu.
 */
function bflp_add_settings_page()
{
    $htaccess = htaccess_firewall();
    $action = new ShowSettingsPage($htaccess);

    add_options_page(
        __('Brute Force Login Protection Settings', 'brute-force-login-protection'), // Page title
        'Brute Force Login Protection', // Menu title
        'manage_options', // Required capability
        'brute-force-login-protection', // Menu slug
        $action // Content callback
    );
}

/**
 * Check requirements and shows errors.
 */
function bflp_show_requirements_errors()
{
    $htaccess = htaccess_firewall();
    if (!$htaccess->exists()) {
        Renderer::error(__('Brute Force Login Protection error: .htaccess file not found', 'brute-force-login-protection'));
    } elseif (!$htaccess->readable()) {
        Renderer::error(__('Brute Force Login Protection error: .htaccess file not readable', 'brute-force-login-protection'));
    } elseif (!$htaccess->writable()) {
        Renderer::error(__('Brute Force Login Protection error: .htaccess file not writeable', 'brute-force-login-protection'));
    }
}

/**
 * Called when a user login has failed.
 */
function bflp_login_failed()
{
    $htaccess = htaccess_firewall();
    $action = new LoginFailed($htaccess);
    $action();
}

/**
 * Called when a user has successfully logged in.
 */
function bflp_login_succeeded()
{
    $IP = RemoteAddress::getClientIP();
    Attempts::remove($IP);
}

/**
 * Register plugin settings.
 */
function bflp_register_settings()
{
    register_setting('brute-force-login-protection', 'bflp_allowed_attempts', 'bflp_validate_allowed_attempts');
    register_setting('brute-force-login-protection', 'bflp_reset_time', 'bflp_validate_reset_time');
    register_setting('brute-force-login-protection', 'bflp_login_failed_delay', 'bflp_validate_login_failed_delay');
    register_setting('brute-force-login-protection', 'bflp_inform_user');
    register_setting('brute-force-login-protection', 'bflp_send_email');
    register_setting('brute-force-login-protection', 'bflp_403_message', 'bflp_validate_403_message');
}

/*
 * Settings validation functions
 */

/**
 * Validates bflp_allowed_attempts field.
 *
 * @param string $input
 *
 * @return int
 */
function bflp_validate_allowed_attempts($input)
{
    if (is_numeric($input) && ($input >= 1 && $input <= 100)) {
        return $input;
    } else {
        add_settings_error('bflp_allowed_attempts', 'bflp_allowed_attempts', __('Allowed login attempts must be a number (between 1 and 100)', 'brute-force-login-protection'));
        return Settings::get('allowed_attempts');
    }
}

/**
 * Validates bflp_reset_time field.
 *
 * @param string $input
 *
 * @return int
 */
function bflp_validate_reset_time($input)
{
    if (is_numeric($input) && $input >= 1) {
        return $input;
    } else {
        add_settings_error('bflp_reset_time', 'bflp_reset_time', __('Minutes before resetting must be a number (higher than 1)', 'brute-force-login-protection'));
        return Settings::get('reset_time');
    }
}

/**
 * Validates bflp_login_failed_delay field.
 *
 * @param string $input
 *
 * @return int
 */
function bflp_validate_login_failed_delay($input)
{
    if (is_numeric($input) && ($input >= 1 && $input <= 10)) {
        return $input;
    } else {
        add_settings_error('bflp_login_failed_delay', 'bflp_login_failed_delay', __('Failed login delay must be a number (between 1 and 10)', 'brute-force-login-protection'));
        return Settings::get('login_failed_delay');
    }
}

/**
 * Saves bflp_403_message field to .htaccess.
 *
 * @param string $input
 *
 * @return string
 */
function bflp_validate_403_message($input)
{
    try {
        $message = htaccess_firewall()->set403Message($input);
        return $message;
    } catch (FilesystemException $ex) {
        add_settings_error('bflp_403_message', 'bflp_403_message', __('An error occurred while saving the blocked users message', 'brute-force-login-protection'));
        return Settings::get('403_message');
    }
}

