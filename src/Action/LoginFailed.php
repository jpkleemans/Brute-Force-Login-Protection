<?php

namespace BFLP\Action;

use BFLP\Util\RemoteAddress;
use BFLP\Repository\LoginAttemptRepository;
use BFLP\Repository\SettingRepository;
use BFLP\Repository\Whitelist;
use HtaccessFirewall\Firewall\HtaccessFirewall;
use HtaccessFirewall\Host\IP;

class LoginFailed
{
    /**
     * @var RemoteAddress
     */
    private $remoteAddress;

    /**
     * @var Whitelist
     */
    private $whitelist;

    /**
     * @var HtaccessFirewall
     */
    private $htaccess;

    /**
     * @var LoginAttemptRepository
     */
    private $loginAttempts;

    /**
     * @var SettingRepository
     */
    private $settings;

    /**
     * LoginController constructor.
     *
     * @param RemoteAddress $remoteAddress
     * @param Whitelist $whitelist
     * @param HtaccessFirewall $htaccess
     * @param LoginAttemptRepository $loginAttempts
     * @param SettingRepository $settings
     */
    public function __construct(RemoteAddress $remoteAddress,
                                Whitelist $whitelist,
                                HtaccessFirewall $htaccess,
                                LoginAttemptRepository $loginAttempts,
                                SettingRepository $settings)
    {
        $this->remoteAddress = $remoteAddress;
        $this->whitelist = $whitelist;
        $this->htaccess = $htaccess;
        $this->loginAttempts = $loginAttempts;
        $this->settings = $settings;
    }

    public function __invoke()
    {
        $ip = $this->remoteAddress->getIpAddress();

        if ($this->whitelist->has($ip)) {
            return;
        }

        $settings = $this->settings->getAll();

        sleep($settings['login_failed_delay']);

        $attempt = $this->loginAttempts->get($ip);

        $currentTime = time();

        if ($attempt == null) {
            $attempt = array('count' => 1);
        } elseif ($attempt['last_failed_login'] > ($currentTime - ($settings['reset_time'] * 60))) {
            $attempt['count'] = 1;
        } else {
            $attempt['count']++;
        }

        // Block if IP has reached max failed login attempts
        if ($attempt['count'] >= $settings['allowed_attempts']) {
            $this->blockIP($ip, $settings['send_email'], $settings['403_message']);
            return;
        }

        $attempt['last_failed_login'] = $currentTime;
        $this->loginAttempts->addOrUpdate($ip, $attempt);

        if ($settings['inform_user']) {
            global $error;
            $remainingAttempts = $settings['allowed_attempts'] - $attempt['count'];
            $error .= '<br />';
            $error .= sprintf(_n("%d attempt remaining.", "%d attempts remaining.", $remainingAttempts, 'brute-force-login-protection'), $remainingAttempts);
        }
    }

    /**
     * Block IP with .htaccess.
     *
     * @param string $ip
     * @param bool $sendEmail
     * @param string $message
     */
    private function blockIP($ip, $sendEmail = false, $message = '')
    {
        $this->loginAttempts->remove($ip);

        if ($sendEmail) {
            $this->sendEmail($ip);
        }

        $this->htaccess->deny(IP::fromString($ip));

        status_header(403);
        wp_die($message);
    }

    /**
     * Send email to admin with info about blocked IP.
     *
     * @param string $ip
     *
     * @return mixed
     */
    private function sendEmail($ip)
    {
        $to = get_option('admin_email');
        $subject = sprintf(__('IP %s has been blocked', 'brute-force-login-protection'), $ip);
        $message = sprintf(__('Brute Force Login Protection has blocked IP %s from access to %s on %s', 'brute-force-login-protection'), $ip, get_site_url(), date('Y-m-d H:i:s'));

        return wp_mail($to, $subject, $message);
    }
}
