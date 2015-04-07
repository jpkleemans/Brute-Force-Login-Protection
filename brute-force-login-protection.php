<?php

require_once ABSPATH . '/wp-admin/includes/file.php';
require_once 'includes/Htaccess.php';
require_once 'includes/Whitelist.php';

/**
 * Plugin Name: Brute Force Login Protection
 * Plugin URI: http://wordpress.org/plugins/brute-force-login-protection/
 * Description: Protects your website against brute force login attacks using .htaccess
 * Text Domain: brute-force-login-protection
 * Author: Fresh-Media
 * Author URI: http://fresh-media.nl/
 * Version: 1.5
 * License: GPL2
 * 
 * Copyright 2014  Fresh-Media
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as 
 * published by the Free Software Foundation.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */
class BruteForceLoginProtection
{
    /**
     * Options array
     * 
     * @var array
     */
    private $options;

    /**
     * Htaccess object
     * 
     * @var Htaccess
     */
    private $htaccess;

    /**
     * Whitelist object
     * 
     * @var Whitelist
     */
    private $whitelist;

    /**
     * Initialize $options and $htaccess.
     * Interact with WordPress hooks.
     * 
     * @return void
     */
    public function __construct()
    {
        // Set default options
        $this->setDefaultOptions();

        // Instantiate Htaccess class
        $this->htaccess = new Htaccess();
        $this->whitelist = new Whitelist();

        // Activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Init hooks
        add_action('plugins_loaded', array($this, 'init'));
        add_action('admin_init', array($this, 'adminInit'));
        add_action('admin_menu', array($this, 'menuInit'));

        // Login hooks
        add_action('wp_login_failed', array($this, 'loginFailed'));
        add_action('wp_login', array($this, 'loginSucceeded'));

        // Auth cookie hooks
        add_action('auth_cookie_bad_username', array($this, 'loginFailed'));
        add_action('auth_cookie_bad_hash', array($this, 'loginFailed'));
    }

    /**
     * Called once any activated plugins have been loaded.
     * 
     * @return void
     */
    public function init()
    {
        // Load textdomain for i18n
        load_plugin_textdomain('brute-force-login-protection', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }

    /**
     * Called when a user accesses the admin area.
     * 
     * @return void
     */
    public function adminInit()
    {
        // Register plugin options
        $this->registerOptions();

        // Set htaccess path
        $this->setHtaccessPath();

        // Set protected files
        $this->setProtectedFiles();

        // Call checkRequirements to check for .htaccess errors
        add_action('admin_notices', array($this, 'showRequirementsErrors'));
    }

    /**
     * Called after the basic admin panel menu structure is in place.
     * 
     * @return void
     */
    public function menuInit()
    {
        // Add settings page to the Settings menu
        add_options_page(
                __('Brute Force Login Protection Settings', 'brute-force-login-protection'), // Page title
                   'Brute Force Login Protection', // Menu title
                   'manage_options', // Required capability
                   'brute-force-login-protection', // Menu slug
                   array($this, 'showSettingsPage') // Content callback
        );
    }

    /**
     * Called When the plugin is activated
     * 
     * @return boolean
     */
    public function activate()
    {
        $this->setHtaccessPath();
        $this->htaccess->uncommentLines();
    }

    /**
     * Called When the plugin is deactivated
     * 
     * @return boolean
     */
    public function deactivate()
    {
        $this->htaccess->commentLines();
    }

    /**
     * Check requirements and shows errors
     * 
     * @return void
     */
    public function showRequirementsErrors()
    {
        $status = $this->htaccess->checkRequirements();

        if (!$status['found']) {
            $this->showError(__('Brute Force Login Protection error: .htaccess file not found', 'brute-force-login-protection'));
        } elseif (!$status['readable']) {
            $this->showError(__('Brute Force Login Protection error: .htaccess file not readable', 'brute-force-login-protection'));
        } elseif (!$status['writeable']) {
            $this->showError(__('Brute Force Login Protection error: .htaccess file not writeable', 'brute-force-login-protection'));
        }
    }

    /**
     * Show settings page and handle user actions.
     * 
     * @return void
     */
    public function showSettingsPage()
    {
        $this->handlePostRequests();

        // Load options and show page
        $this->fillOptions();
        include 'includes/settings-page.php';
    }

    /**
     * Handle settings page POST requests
     */
    private function handlePostRequests()
    {
        if (isset($_POST['IP'])) {
            $IP = $_POST['IP'];

            if (isset($_POST['block'])) { // Manually block IP
                $this->manualBlockIP($IP);
            } elseif (isset($_POST['unblock'])) { // Unblock IP
                $this->manualUnblockIP($IP);
            } elseif (isset($_POST['whitelist'])) { // Add IP to whitelist
                $this->manualWhitelistIP($IP);
            } elseif (isset($_POST['unwhitelist'])) { // Remove IP from whitelist
                $this->manualUnwhitelistIP($IP);
            }
        } elseif (isset($_POST['reset'])) { // Reset options
            $this->resetOptions();
        }
    }

    /**
     * Block IP and show status message
     * 
     * @param string $IP
     */
    private function manualBlockIP($IP)
    {
        $whitelist = $this->whitelist->getAll();
        if (in_array($IP, $whitelist)) {
            $this->showError(sprintf(__('You can\'t block a whitelisted IP', 'brute-force-login-protection'), $IP));
        } elseif ($this->htaccess->denyIP($IP)) {
            $this->showMessage(sprintf(__('IP %s blocked', 'brute-force-login-protection'), $IP));
        } else {
            $this->showError(sprintf(__('An error occurred while blocking IP %s', 'brute-force-login-protection'), $IP));
        }
    }

    /**
     * Unblock IP and show status message
     * 
     * @param string $IP
     */
    private function manualUnblockIP($IP)
    {
        if ($this->htaccess->undenyIP($IP)) {
            $this->showMessage(sprintf(__('IP %s unblocked', 'brute-force-login-protection'), $IP));
        } else {
            $this->showError(sprintf(__('An error occurred while unblocking IP %s', 'brute-force-login-protection'), $IP));
        }
    }

    /**
     * Add IP to whitelist and show status message
     * 
     * @param string $IP
     */
    private function manualWhitelistIP($IP)
    {
        if ($this->whitelist->add($IP) && $this->htaccess->undenyIP($IP)) {
            $this->showMessage(sprintf(__('IP %s added to whitelist', 'brute-force-login-protection'), $IP));
        } else {
            $this->showError(sprintf(__('An error occurred while adding IP %s to whitelist', 'brute-force-login-protection'), $IP));
        }
    }

    /**
     * Remove IP from whitelist and show status message
     * 
     * @param string $IP
     */
    private function manualUnwhitelistIP($IP)
    {
        if ($this->whitelist->remove($IP)) {
            $this->showMessage(sprintf(__('IP %s removed from whitelist', 'brute-force-login-protection'), $IP));
        } else {
            $this->showError(sprintf(__('An error occurred while removing IP %s from whitelist', 'brute-force-login-protection'), $IP));
        }
    }

    /**
     * Called when a user login has failed
     * Increase number of attempts for clients IP.
     * Deny IP if max attempts is reached.
     * 
     * @return void
     */
    public function loginFailed()
    {
        $IP = $this->getClientIP();
        $whitelist = $this->whitelist->getAll();

        if (!in_array($IP, $whitelist)) {
            $this->fillOptions();

            sleep($this->options['login_failed_delay']);

            // Get login attempts array from database
            $attempts = get_option('bflp_login_attempts');

            // Create option in database if it doesn't exist
            if (!is_array($attempts)) {
                $attempts = array();
                add_option('bflp_login_attempts', $attempts, '', 'no');
            }

            $denyIP = false;

            // Check if IP has failed login attempts within reset_time
            if ($IP && isset($attempts[$IP]) && $attempts[$IP]['time'] > (time() - ($this->options['reset_time'] * 60))) {
                $attempts[$IP]['attempts'] ++;

                // Check if IP has reached max failed login attempts
                if ($attempts[$IP]['attempts'] >= $this->options['allowed_attempts']) {
                    $denyIP = true;
                    unset($attempts[$IP]);
                } else {
                    $attempts[$IP]['time'] = time();
                }
            } else { // First failed login attempt of IP
                $attempts[$IP]['attempts'] = 1;
                $attempts[$IP]['time'] = time();
            }

            update_option('bflp_login_attempts', $attempts);

            if ($denyIP) {
                $this->blockIP($IP);
            }

            if ($this->options['inform_user']) {
                global $error;
                $remainingAttempts = $this->options['allowed_attempts'] - $attempts[$IP]['attempts'];
                $error .= '<br />';
                $error .= sprintf(_n("%d attempt remaining.", "%d attempts remaining.", $remainingAttempts, 'brute-force-login-protection'), $remainingAttempts);
            }
        }
    }

    /**
     * Called when a user has successfully logged in
     * Remove IP from bflp_login_attempts if exist.
     * 
     * @return void
     */
    public function loginSucceeded()
    {
        $attempts = get_option('bflp_login_attempts');

        if (is_array($attempts)) {
            $IP = $this->getClientIP();

            if (isset($attempts[$IP])) {
                unset($attempts[$IP]);
                update_option('bflp_login_attempts', $attempts);
            }
        }
    }

    /**
     * Validate bflp_allowed_attempts field.
     * 
     * @param mixed $input
     * @return int
     */
    public function validateAllowedAttempts($input)
    {
        if (is_numeric($input) && ($input >= 1 && $input <= 100)) {
            return $input;
        }

        add_settings_error(
                'bflp_allowed_attempts', // Setting slug
                'bflp_allowed_attempts', // Error slug
                __('Allowed login attempts must be a number (between 1 and 100)', 'brute-force-login-protection') // Message
        );

        $this->fillOption('allowed_attempts');
        return $this->options['allowed_attempts'];
    }

    /**
     * Validate bflp_reset_time field.
     * 
     * @param mixed $input
     * @return int
     */
    public function validateResetTime($input)
    {
        if (is_numeric($input) && $input >= 1) {
            return $input;
        }

        add_settings_error(
                'bflp_reset_time', // Setting slug
                'bflp_reset_time', // Error slug
                __('Minutes before resetting must be a number (higher than 1)', 'brute-force-login-protection') // Message
        );

        $this->fillOption('reset_time');
        return $this->options['reset_time'];
    }

    /**
     * Validate bflp_login_failed_delay field.
     * 
     * @param mixed $input
     * @return int
     */
    public function validateLoginFailedDelay($input)
    {
        if (is_numeric($input) && ($input >= 1 && $input <= 10)) {
            return $input;
        }

        add_settings_error(
                'bflp_login_failed_delay', // Setting slug
                'bflp_login_failed_delay', // Error slug
                __('Failed login delay must be a number (between 1 and 10)', 'brute-force-login-protection') // Message
        );

        $this->fillOption('login_failed_delay');
        return $this->options['login_failed_delay'];
    }

    /**
     * Save bflp_403_message field to .htaccess.
     * 
     * @param mixed $input
     * @return string
     */
    public function validate403Message($input)
    {
        $message = htmlentities($input);

        if ($this->htaccess->edit403Message($message)) {
            return $message;
        }

        add_settings_error(
                'bflp_403_message', // Setting slug
                'bflp_403_message', // Error slug
                __('An error occurred while saving the blocked users message', 'brute-force-login-protection') // Message
        );

        $this->fillOption('403_message');
        return $this->options['403_message'];
    }

    /**
     * Save bflp_protected_files field to .htaccess.
     * 
     * @param mixed $input
     * @return string
     */
    public function validateProtectedFiles($input)
    {
        $trimmed = trim($input, ',');

        if ($this->htaccess->setFilesMatch($trimmed, true)) {
            return $trimmed;
        }

        add_settings_error(
                'bflp_protected_files', // Setting slug
                'bflp_protected_files', // Error slug
                __('An error occurred while saving the list of protected files', 'brute-force-login-protection') // Message
        );

        $this->fillOption('bflp_protected_files');
        return $this->options['bflp_protected_files'];
    }

    /**
     * Set htaccess path to $options['htaccess_dir'].
     * 
     * @return void
     */
    private function setHtaccessPath()
    {
        $this->fillOption('htaccess_dir');
        $this->htaccess->setPath($this->options['htaccess_dir']);
    }

    /**
     * Set htaccess protected files to $options['protected_files'].
     * 
     * @return void
     */
    private function setProtectedFiles()
    {
        $this->fillOption('protected_files');
        $this->htaccess->setFilesMatch($this->options['protected_files']);
    }

    /**
     * Set default options
     * 
     * @return void
     */
    private function setDefaultOptions()
    {
        $this->options = array(
            'allowed_attempts' => 20, // Allowed login attempts before deny,
            'reset_time' => 60, // Minutes before resetting login attempts count
            'login_failed_delay' => 1, // Delay in seconds when a user login has failed
            'inform_user' => true, // Inform user about remaining login attempts on login page
            'send_email' => false, // Send email to administrator when an IP has been blocked
            '403_message' => '', // Message to show to a blocked user
            'protected_files' => '', // Comma separated list of protected files
            'htaccess_dir' => get_home_path() // .htaccess file location
        );
    }

    /**
     * Register options (settings).
     * 
     * @return void
     */
    private function registerOptions()
    {
        register_setting('brute-force-login-protection', 'bflp_allowed_attempts', array($this, 'validateAllowedAttempts'));
        register_setting('brute-force-login-protection', 'bflp_reset_time', array($this, 'validateResetTime'));
        register_setting('brute-force-login-protection', 'bflp_login_failed_delay', array($this, 'validateLoginFailedDelay'));
        register_setting('brute-force-login-protection', 'bflp_inform_user');
        register_setting('brute-force-login-protection', 'bflp_send_email');
        register_setting('brute-force-login-protection', 'bflp_403_message', array($this, 'validate403Message'));
        register_setting('brute-force-login-protection', 'bflp_protected_files', array($this, 'validateProtectedFiles'));
        register_setting('brute-force-login-protection', 'bflp_htaccess_dir');
    }

    /**
     * Fill single option with value (from database).
     * 
     * @param string $name
     * @return void
     */
    private function fillOption($name)
    {
        $this->options[$name] = get_option('bflp_' . $name, $this->options[$name]);
    }

    /**
     * Fill all options with value (from database).
     * 
     * @return void
     */
    private function fillOptions()
    {
        $this->fillOption('allowed_attempts');
        $this->fillOption('reset_time');
        $this->fillOption('login_failed_delay');
        $this->fillOption('inform_user');
        $this->fillOption('send_email');
        $this->fillOption('403_message');
        $this->fillOption('protected_files');
    }

    /**
     * Reset options to their default value
     * 
     * @return void
     */
    private function resetOptions()
    {
        $this->htaccess->remove403Message();
        $this->deleteOptions();
        $this->setDefaultOptions();
        $this->setHtaccessPath();
        $this->showMessage(__('The Options have been successfully reset', 'brute-force-login-protection'));
    }

    /**
     * Delete options from database.
     * 
     * @return void
     */
    private function deleteOptions()
    {
        delete_option('bflp_allowed_attempts');
        delete_option('bflp_reset_time');
        delete_option('bflp_login_failed_delay');
        delete_option('bflp_inform_user');
        delete_option('bflp_send_email');
        delete_option('bflp_403_message');
        delete_option('bflp_protected_files');
        delete_option('bflp_htaccess_dir');
    }

    /**
     * Deny IP with .htaccess and show forbidden message
     * 
     * @param string $IP
     */
    private function blockIP($IP)
    {
        if ($this->options['send_email']) {
            $this->sendEmail($IP);
        }

        $this->setHtaccessPath();
        $this->htaccess->denyIP($IP);

        status_header(403);
        wp_die($this->options['403_message']);
    }

    /**
     * Return the client ip address.
     * 
     * @return mixed
     */
    private function getClientIP()
    {
        return $_SERVER['REMOTE_ADDR'];
    }

    /**
     * Send email to admin with info about blocked IP
     * 
     * @return mixed
     */
    private function sendEmail($IP)
    {
        $to = get_option('admin_email');
        $subject = sprintf(__('IP %s has been blocked', 'brute-force-login-protection'), $IP);
        $message = sprintf(__('Brute Force Login Protection has blocked IP %s from access to %s on %s', 'brute-force-login-protection'), $IP, get_site_url(), date('Y-m-d H:i:s'));

        return wp_mail($to, $subject, $message);
    }

    /**
     * Echo message
     * 
     * @param string $message
     * @return void
     */
    private function showMessage($message, $class = 'updated')
    {
        echo '<div class="' . $class . '"><p>' . $message . '</p></div>';
    }

    /**
     * Echo message with class 'error'.
     * 
     * @param string $message
     * @return void
     */
    private function showError($message)
    {
        $this->showMessage($message, 'error');
    }
}

// Instantiate BruteForceLoginProtection class
new BruteForceLoginProtection();
