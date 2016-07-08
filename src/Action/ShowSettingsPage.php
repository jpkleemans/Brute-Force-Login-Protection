<?php

namespace BFLP\Action;

class ShowSettingsPage
{
    public function __invoke()
    {
        $this->handlePostRequest();

        // Load options and show page
        $this->fillOptions();

        $this->renderer->template('settings', array(
            'status' => array(
                'exists' => $this->filesystem->exists(),
                'readable' => $this->filesystem->readable(),
                'writable' => $this->filesystem->writable()
            ),
            'settings' => [],
            'blockedIPs' => [],
            'whitelistedIPs' => [],
            'currentIP' => ''
        ));
    }

    /**
     * Handle settings page POST request.
     */
    private function handlePostRequest()
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
            $this->resetSettings();
        }
    }

    /**
     * Block IP and show status message.
     *
     * @param string $IP
     */
    private function blockIP($IP)
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
     * Unblock IP and show status message.
     *
     * @param string $IP
     */
    private function unblockIP($IP)
    {
        if ($this->htaccess->undenyIP($IP)) {
            $this->showMessage(sprintf(__('IP %s unblocked', 'brute-force-login-protection'), $IP));
        } else {
            $this->showError(sprintf(__('An error occurred while unblocking IP %s', 'brute-force-login-protection'), $IP));
        }
    }

    /**
     * Add IP to whitelist and show status message.
     *
     * @param string $IP
     */
    private function whitelistIP($IP)
    {
        if ($this->whitelist->add($IP) && $this->htaccess->undenyIP($IP)) {
            $this->showMessage(sprintf(__('IP %s added to whitelist', 'brute-force-login-protection'), $IP));
        } else {
            $this->showError(sprintf(__('An error occurred while adding IP %s to whitelist', 'brute-force-login-protection'), $IP));
        }
    }

    /**
     * Remove IP from whitelist and show status message.
     *
     * @param string $IP
     */
    private function unwhitelistIP($IP)
    {
        if ($this->whitelist->remove($IP)) {
            $this->showMessage(sprintf(__('IP %s removed from whitelist', 'brute-force-login-protection'), $IP));
        } else {
            $this->showError(sprintf(__('An error occurred while removing IP %s from whitelist', 'brute-force-login-protection'), $IP));
        }
    }
}
