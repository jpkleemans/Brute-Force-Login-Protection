<?php

namespace BFLP;

class BootstrapOld
{
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

        $this->settings->addError(
            'allowed_attempts',
            __('Allowed login attempts must be a number (between 1 and 100)', 'brute-force-login-protection')
        );

        return $this->settings->get('allowed_attempts');
    }

    /**
     * Validate reset_time field.
     *
     * @param mixed $input
     * @return int
     */
    public function validateResetTime($input)
    {
        if (is_numeric($input) && $input >= 1) {
            return $input;
        }

        $this->settings->addError(
            'reset_time',
            __('Minutes before resetting must be a number (higher than 1)', 'brute-force-login-protection')
        );

        return $this->settings->get('reset_time');
    }

    /**
     * Validate login_failed_delay field.
     *
     * @param mixed $input
     * @return int
     */
    public function validateLoginFailedDelay($input)
    {
        if (is_numeric($input) && ($input >= 1 && $input <= 10)) {
            return $input;
        }

        $this->settings->addError(
            'login_failed_delay',
            __('Failed login delay must be a number (between 1 and 10)', 'brute-force-login-protection')
        );

        return $this->settings->get('login_failed_delay');
    }

    /**
     * Validate 403_message field.
     *
     * @param mixed $input
     * @return string
     */
    public function validate403Message($input)
    {
        $message = htmlentities($input);

        // Save 403 message to htaccess
        if ($this->htaccess->editLineWithPrefix('ErrorDocument 403 ', 'ErrorDocument 403 ' . $message)) {
            return $message;
        }

        $this->settings->addError(
            '403_message',
            __('An error occurred while saving the blocked users message', 'brute-force-login-protection')
        );

        return $this->settings->get('403_message');
    }

    /**
     * Register settings
     *
     * @return void
     */
    private function registerSettings()
    {
        $this->settings->register('allowed_attempts', array($this, 'validateAllowedAttempts'));
        $this->settings->register('reset_time', array($this, 'validateResetTime'));
        $this->settings->register('login_failed_delay', array($this, 'validateLoginFailedDelay'));
        $this->settings->register('inform_user');
        $this->settings->register('send_email');
        $this->settings->register('403_message', array($this, 'validate403Message'));
        $this->settings->register('htaccess_dir');
    }

    /**
     * Set default settings
     *
     * @return void
     */
    private function setDefaultSettings()
    {
        $this->settings->set('allowed_attempts', 10); // Allowed login attempts before deny
        $this->settings->set('reset_time', 60); // Minutes before resetting login attempts count
        $this->settings->set('login_failed_delay', 1); // Delay in seconds when a user login has failed
        $this->settings->set('inform_user', true); // Inform user about remaining login attempts on login page
        $this->settings->set('send_email', false); // Send email to administrator when an IP has been blocked
        $this->settings->set('403_message', ''); // Message to show to a blocked user
        $this->settings->set('htaccess_dir', get_home_path()); // .htaccess file location
    }

    /**
     * Reset settings to their default value
     *
     * @return void
     */
    private function resetSettings()
    {
        $this->settings->remove('allowed_attempts');
        $this->settings->remove('reset_time');
        $this->settings->remove('login_failed_delay');
        $this->settings->remove('inform_user');
        $this->settings->remove('send_email');
        $this->settings->remove('403_message');
        $this->settings->remove('htaccess_dir');

        $this->htaccess->removeLineWithPrefix('ErrorDocument 403 ');

        $this->setDefaultSettings();

        $this->showMessage(__('The settings have been successfully reset', 'brute-force-login-protection'));
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
}
