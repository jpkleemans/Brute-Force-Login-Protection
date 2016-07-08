<?php

namespace BFLP;

use BFLP\Action\LoginFailed;
use BFLP\Action\LoginSucceeded;
use BFLP\Action\ShowSettingsPage;

use BFLP\Repository\LoginAttemptRepository;
use BFLP\Repository\SettingRepository;
use BFLP\Repository\Whitelist;

use BFLP\Util\RemoteAddress;

use HtaccessFirewall\Firewall\HtaccessFirewall;

class Bootstrap
{
    /**
     * Bootstrap plugin.
     */
    public function __construct()
    {
        // Activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Internationalization hook
        add_action('plugins_loaded', array($this, 'loadTextdomain'));

        // Admin hooks
        //add_action('admin_init', array($this, 'adminInit')); //why?
        add_action('admin_menu', array($this, 'addMenuItem'));
        add_action('admin_notices', array($this, 'showRequirementsErrors'));

        // Login form hooks
        add_action('wp_login_failed', array($this, 'loginFailed'));
        add_action('wp_login', array($this, 'loginSucceeded'));

        // Auth cookie hooks
        add_action('auth_cookie_bad_username', array($this, 'loginFailed'));
        add_action('auth_cookie_bad_hash', array($this, 'loginFailed'));
    }

    /**
     * Activate plugin.
     *
     * @return boolean
     */
    public function activate()
    {
        $this->createDatabaseTables();
        $this->migrateFromOldVersions();

        $htaccess = new HtaccessFirewall();
        $htaccess->activate();
    }

    private function createDatabaseTables()
    {
        //
    }

    private function migrateFromOldVersions()
    {
        //
    }

    /**
     * Deactivate plugin.
     *
     * @return boolean
     */
    public function deactivate()
    {
        $htaccess = new HtaccessFirewall();
        $htaccess->deactivate();
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
     * Add settings page menu item to the Settings menu.
     *
     * @return void
     */
    public function addMenuItem()
    {
        $action = new ShowSettingsPage();

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
        if (!$this->filesystem->exists()) {
            $this->renderer->error(__('Brute Force Login Protection error: .htaccess file not found', 'brute-force-login-protection'));
        } elseif (!$this->filesystem->readable()) {
            $this->renderer->error(__('Brute Force Login Protection error: .htaccess file not readable', 'brute-force-login-protection'));
        } elseif (!$this->filesystem->writable()) {
            $this->renderer->error(__('Brute Force Login Protection error: .htaccess file not writeable', 'brute-force-login-protection'));
        }
    }

    /**
     * Called when a user login has failed.
     */
    public function loginFailed()
    {
        $action = new LoginFailed(
            new RemoteAddress(),
            new Whitelist(),
            new HtaccessFirewall(),
            new LoginAttemptRepository(),
            new SettingRepository()
        );

        $action();
    }

    /**
     * Called when a user has successfully logged in.
     */
    public function loginSucceeded()
    {
        $action = new LoginSucceeded(
            new RemoteAddress(),
            new LoginAttemptRepository()
        );

        $action();
    }

    private function getHtaccessFirewall()
    {
        $settings = new SettingRepository();
        $path = $settings->get('bflp_htaccess_path');
        $firewall = new HtaccessFirewall($path);

        return $firewall;
    }
}
