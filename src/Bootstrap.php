<?php

namespace BFLP;

use BFLP\Action\LoginFailed;
use BFLP\Action\ShowSettingsPage;
use BFLP\DataAccess\Attempts;
use BFLP\DataAccess\Settings;
use BFLP\Util\RemoteAddress;
use BFLP\Util\Renderer;
use HtaccessFirewall\Filesystem\Exception\FilesystemException;
use HtaccessFirewall\HtaccessFirewall;

class Bootstrap
{
    /** @var HtaccessFirewall */
    private $htaccess;

    public function __construct()
    {
        $path = get_home_path() . '.htaccess';
        $this->htaccess = new HtaccessFirewall($path);

        // Hook activation and deactivation actions
        $mainPluginFile = __DIR__ . '/../brute-force-login-protection.php';
        register_activation_hook($mainPluginFile, array($this, 'activate'));
        register_deactivation_hook($mainPluginFile, array($this, 'deactivate'));

        // Hook internationalization action
        add_action('plugins_loaded', array($this, 'loadTextdomain'));

        // Hook admin actions
        add_action('admin_init', array($this, 'registerSettings'));
        add_action('admin_menu', array($this, 'addSettingsPage'));
        add_action('admin_notices', array($this, 'showRequirementsErrors'));

        // Hook login form actions
        add_action('wp_login_failed', array($this, 'loginFailed'));
        add_action('wp_login', array($this, 'loginSucceeded'));

        // Hook auth cookie actions
        add_action('auth_cookie_bad_username', array($this, 'loginFailed'));
        add_action('auth_cookie_bad_hash', array($this, 'loginFailed'));
    }

    /**
     * Activate the plugin.
     */
    public function activate()
    {
        $this->migrateFromOldVersions();
        $this->createDatabaseTable();

        try {
            $this->htaccess->reactivate();
        } catch (FilesystemException $ex) {
            Renderer::error(__('Brute Force Login Protection error: cannot activate plugin', 'brute-force-login-protection'));
        }
    }

    private function migrateFromOldVersions()
    {
        // From 2.0.0 the attempts are stored in a separate table.
        delete_option('bflp_login_attempts');
    }

    private function createDatabaseTable()
    {
        global $wpdb;

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
    }

    /**
     * Deactivate the plugin.
     */
    public function deactivate()
    {
        try {
            $this->htaccess->deactivate();
        } catch (FilesystemException $ex) {
            Renderer::error(__('Brute Force Login Protection error: cannot deactivate plugin', 'brute-force-login-protection'));
        }
    }

    /**
     * Load textdomain for i18n.
     */
    public function loadTextdomain()
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
    public function addSettingsPage()
    {
        $action = new ShowSettingsPage($this->htaccess);

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
    public function showRequirementsErrors()
    {
        if (!$this->htaccess->exists()) {
            Renderer::error(__('Brute Force Login Protection error: .htaccess file not found', 'brute-force-login-protection'));
        } elseif (!$this->htaccess->readable()) {
            Renderer::error(__('Brute Force Login Protection error: .htaccess file not readable', 'brute-force-login-protection'));
        } elseif (!$this->htaccess->writable()) {
            Renderer::error(__('Brute Force Login Protection error: .htaccess file not writeable', 'brute-force-login-protection'));
        }
    }

    /**
     * Called when a user login has failed.
     */
    public function loginFailed()
    {
        $action = new LoginFailed($this->htaccess);
        $action();
    }

    /**
     * Called when a user has successfully logged in.
     */
    public function loginSucceeded()
    {
        $IP = RemoteAddress::getClientIP();
        Attempts::remove($IP);
    }

    /**
     * Register plugin settings.
     */
    public function registerSettings()
    {
        register_setting('brute-force-login-protection', 'bflp_allowed_attempts', array($this, 'validateAllowedAttempts'));
        register_setting('brute-force-login-protection', 'bflp_reset_time', array($this, 'validateResetTime'));
        register_setting('brute-force-login-protection', 'bflp_login_failed_delay', array($this, 'validateLoginFailedDelay'));
        register_setting('brute-force-login-protection', 'bflp_inform_user');
        register_setting('brute-force-login-protection', 'bflp_send_email');
        register_setting('brute-force-login-protection', 'bflp_403_message', array($this, 'validate403Message'));
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
    public function validateAllowedAttempts($input)
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
    public function validateResetTime($input)
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
    public function validateLoginFailedDelay($input)
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
    public function validate403Message($input)
    {
        try {
            $message = $this->htaccess->set403Message($input);
            return $message;
        } catch (FilesystemException $ex) {
            add_settings_error('bflp_403_message', 'bflp_403_message', __('An error occurred while saving the blocked users message', 'brute-force-login-protection'));
            return Settings::get('403_message');
        }
    }
}
