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

require_once __DIR__ . '/vendor/autoload.php';

// Load actions
$actions = new \BFLP\Actions;

// Hook activation and deactivation actions
register_activation_hook(__FILE__, array($actions, 'activate'));
register_deactivation_hook(__FILE__, array($actions, 'deactivate'));

// Hook internationalization action
add_action('plugins_loaded', array($actions, 'loadTextdomain'));

// Hook admin actions
add_action('admin_init', array($actions, 'registerSettings'));
add_action('admin_menu', array($actions, 'addSettingsPage'));
add_action('admin_notices', array($actions, 'showRequirementsErrors'));

// Hook login form actions
add_action('wp_login_failed', array($actions, 'loginFailed'));
add_action('wp_login', array($actions, 'loginSucceeded'));

// Hook auth cookie actions
add_action('auth_cookie_bad_username', array($actions, 'loginFailed'));
add_action('auth_cookie_bad_hash', array($actions, 'loginFailed'));
