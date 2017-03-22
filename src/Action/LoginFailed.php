<?php

namespace BFLP\Action;

use BFLP\DataAccess\Attempts;
use BFLP\DataAccess\Settings;
use BFLP\DataAccess\Whitelist;
use BFLP\Util\RemoteAddress;
use HtaccessFirewall\Host\IP;
use HtaccessFirewall\HtaccessFirewall;

class LoginFailed
{
    /** @var HtaccessFirewall */
    private $htaccess;

    public function __construct(HtaccessFirewall $htaccess)
    {
        $this->htaccess = $htaccess;
    }

    public function __invoke()
    {
        $IP = RemoteAddress::getClientIP();
        if (Whitelist::has($IP)) return;

        $settings = Settings::all();
        sleep($settings['login_failed_delay']);

        $attempt = Attempts::get($IP);
        if ($attempt['last_failed_at'] > (time() - ($settings['reset_time'] * 60))) {
            $attempt['count'] = 1;
        } else {
            $attempt['count']++;
        }

        // Block if IP has reached max failed login attempts.
        if ($attempt['count'] >= $settings['allowed_attempts']) {
            Attempts::remove($IP);
            $this->blockIP($IP, $settings['send_email'], $settings['403_message']);
            return;
        }

        Attempts::update($attempt);

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
     * @param string $IP
     * @param bool $sendEmail
     * @param string $message
     */
    private function blockIP($IP, $sendEmail = false, $message = '')
    {
        if ($sendEmail) $this->sendEmail($IP);

        $this->htaccess->deny(IP::fromString($IP));

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
