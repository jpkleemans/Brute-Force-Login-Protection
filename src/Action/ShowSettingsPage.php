<?php

namespace BFLP\Action;

use BFLP\DataAccess\Settings;
use BFLP\DataAccess\Whitelist;
use BFLP\Util\RemoteAddress;
use BFLP\Util\Renderer;
use HtaccessFirewall\Filesystem\Exception\FilesystemException;
use HtaccessFirewall\Host\Exception\InvalidArgumentException;
use HtaccessFirewall\Host\IP;
use HtaccessFirewall\HtaccessFirewall;

class ShowSettingsPage
{
    /** @var HtaccessFirewall */
    private $htaccess;

    public function __construct(HtaccessFirewall $htaccess)
    {
        $this->htaccess = $htaccess;
    }

    public function __invoke()
    {
        $this->handlePostRequest();

        try {
            $blockedIPs = $this->htaccess->getDenied();
        } catch (FilesystemException $ex) {
            $blockedIPs = array();
        }

        Renderer::template('settings', array(
            'status' => array(
                'exists' => $this->htaccess->exists(),
                'readable' => $this->htaccess->readable(),
                'writable' => $this->htaccess->writable()
            ),
            'settings' => Settings::all(),
            'blockedIPs' => $blockedIPs,
            'currentIP' => RemoteAddress::getClientIP(),
        ));
    }

    /**
     * Handle settings page POST request.
     */
    private function handlePostRequest()
    {
        if (isset($_POST['IP'])) {
            try {
                $IP = IP::fromString($_POST['IP']);
            } catch (InvalidArgumentException $ex) {
                Renderer::error(__('Invalid IP', 'brute-force-login-protection'));
                return;
            }

            if (isset($_POST['block'])) $this->blockIP($IP);
            elseif (isset($_POST['unblock'])) $this->unblockIP($IP);
            elseif (isset($_POST['whitelist'])) $this->whitelistIP($IP);
            elseif (isset($_POST['unwhitelist'])) $this->unwhitelistIP($IP);
        } elseif (isset($_POST['reset'])) {
            $this->reset();
        }
    }

    /**
     * Block IP and show status message.
     *
     * @param IP $IP
     */
    private function blockIP($IP)
    {
        $whitelist = Whitelist::all();
        if (in_array($IP->toString(), $whitelist)) {
            Renderer::error(sprintf(__('You can\'t block a whitelisted IP', 'brute-force-login-protection'), $IP));
            return;
        }

        try {
            $this->htaccess->deny($IP);
            Renderer::message(sprintf(__('IP %s blocked', 'brute-force-login-protection'), $IP));
        } catch (FilesystemException $ex) {
            Renderer::error(sprintf(__('An error occurred while blocking IP %s', 'brute-force-login-protection'), $IP));
        }
    }

    /**
     * Unblock IP and show status message.
     *
     * @param IP $IP
     */
    private function unblockIP($IP)
    {
        try {
            $this->htaccess->undeny($IP);
            Renderer::message(sprintf(__('IP %s unblocked', 'brute-force-login-protection'), $IP));
        } catch (FilesystemException $ex) {
            Renderer::error(sprintf(__('An error occurred while unblocking IP %s', 'brute-force-login-protection'), $IP));
        }
    }

    /**
     * Add IP to whitelist and show status message.
     *
     * @param IP $IP
     */
    private function whitelistIP($IP)
    {
        try {
            $this->htaccess->undeny($IP);
        } catch (FilesystemException $ex) {
            Renderer::error(sprintf(__('An error occurred while adding IP %s to whitelist', 'brute-force-login-protection'), $IP));
        }

        if (Whitelist::add($IP)) {
            Renderer::message(sprintf(__('IP %s added to whitelist', 'brute-force-login-protection'), $IP));
        } else {
            Renderer::error(sprintf(__('An error occurred while adding IP %s to whitelist', 'brute-force-login-protection'), $IP));
        }
    }

    /**
     * Remove IP from whitelist and show status message.
     *
     * @param IP $IP
     */
    private function unwhitelistIP($IP)
    {
        if (Whitelist::remove($IP)) {
            Renderer::message(sprintf(__('IP %s removed from whitelist', 'brute-force-login-protection'), $IP));
        } else {
            Renderer::error(sprintf(__('An error occurred while removing IP %s from whitelist', 'brute-force-login-protection'), $IP));
        }
    }

    private function reset()
    {
        Settings::reset();

        try {
            $this->htaccess->remove403Message();
        } catch (FilesystemException $ex) {
            Renderer::error(__('An error occurred while resetting the blocked user message', 'brute-force-login-protection'));
        }
    }
}
