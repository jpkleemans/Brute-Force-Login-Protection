<?php

namespace BFLP\Util;

class RemoteAddress
{
    /**
     * Returns client IP address.
     *
     * @return string IP address.
     */
    public static function getClientIP()
    {
        return $_SERVER['REMOTE_ADDR'];
    }
}
