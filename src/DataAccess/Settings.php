<?php

namespace BFLP\DataAccess;

class Settings
{
    /** @var array */
    private static $defaults = array(
        'allowed_attempts' => 20, // Allowed login attempts before deny,
        'reset_time' => 60, // Minutes before resetting login attempts count
        'login_failed_delay' => 1, // Delay in seconds when a user login has failed
        'inform_user' => true, // Inform user about remaining login attempts on login page
        'send_email' => false, // Send email to administrator when an IP has been blocked
        '403_message' => '', // Message to show to a blocked user
        'whitelist' => array(), // Whitelisted IPs
    );

    public static function get($name)
    {
        return get_option('bflp_' . $name, self::$defaults[$name]);
    }

    public static function all()
    {
        $settings = array();
        foreach (self::$defaults as $name => $value) {
            $settings[$name] = self::get($name);
        }

        return $settings;
    }

    /**
     * @param $name
     * @param $value
     *
     * @return bool
     */
    public static function set($name, $value)
    {
        return update_option('bflp_' . $name, $value);
    }

    public static function reset()
    {
        foreach (self::$defaults as $name => $value) {
            delete_option('bflp_' . $name);
        }
    }
}
