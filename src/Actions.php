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

/**
 * Brute Force Login Protection Hooks
 */
class Actions
{
    /** @var HtaccessFirewall */
    private $htaccess;

    /**
     * Lazy load HtaccessFirewall.
     *
     * @return HtaccessFirewall
     */
    public function getHtaccess()
    {
        if (!isset($this->htaccess)) {
            $path = get_home_path() . '.htaccess';
            $this->htaccess = new HtaccessFirewall($path);
        }

        return $this->htaccess;
    }

    /**
     * Activate plugin.
     */
    public function activate()
    {
        $this->createDatabaseTables();
        $this->migrateFromOldVersions();

        try {
            $this->getHtaccess()->reactivate();
        } catch (FilesystemException $ex) {
            //
        }
    }

    private function createDatabaseTables()
    {
        //
    }

    private function migrateFromOldVersions()
    {
        // From 2.0.0 the attempts are stored in a separate table.
        delete_option('bflp_login_attempts');
    }

    /**
     * Deactivate plugin.
     */
    public function deactivate()
    {
        try {
            $this->getHtaccess()->deactivate();
        } catch (FilesystemException $ex) {
            //
        }
    }

    /**
     * Load textdomain for i18n.
     *
     * @return void
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
     * Register settings.
     *
     * @return void
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

    /**
     * Add settings page menu item to the Settings menu.
     *
     * @return void
     */
    public function addSettingsPage()
    {
        $action = new ShowSettingsPage($this->getHtaccess());

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
     *
     * @return void
     */
    public function showRequirementsErrors()
    {
        if (!$this->getHtaccess()->exists()) {
            Renderer::error(__('Brute Force Login Protection error: .htaccess file not found', 'brute-force-login-protection'));
        } elseif (!$this->getHtaccess()->readable()) {
            Renderer::error(__('Brute Force Login Protection error: .htaccess file not readable', 'brute-force-login-protection'));
        } elseif (!$this->getHtaccess()->writable()) {
            Renderer::error(__('Brute Force Login Protection error: .htaccess file not writeable', 'brute-force-login-protection'));
        }
    }

    /**
     * Called when a user login has failed.
     */
    public function loginFailed()
    {
        $action = new LoginFailed($this->getHtaccess());
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
     * Settings validation functions
     */

    /**
     * Validates bflp_allowed_attempts field.
     *
     * @param mixed $input
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
     * @param mixed $input
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
     * @param mixed $input
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
     * @param mixed $input
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
