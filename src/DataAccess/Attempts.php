<?php

namespace BFLP\DataAccess;

class Attempts
{
    /**
     * @param $IP
     *
     * @return array
     */
    public static function get($IP)
    {
        global $wpdb;
        // array('count' => 1); if not found
    }

    public static function update($attempt)
    {
        global $wpdb;
        // also update timestamp
    }

    /**
     * @param string $IP
     *
     * @return bool
     */
    public static function remove($IP)
    {
        global $wpdb;
        // remove row
    }
}
