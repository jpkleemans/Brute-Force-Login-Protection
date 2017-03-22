<?php

namespace BFLP\DataAccess;

use HtaccessFirewall\Host\IP;

class Whitelist
{
    public static function all()
    {
        $whitelist = Settings::get('whitelist');
        if (!is_array($whitelist)) {
            $whitelist = array();
        }

        return $whitelist;
    }

    /**
     * @param IP $IP
     *
     * @return bool
     */
    public static function remove($IP)
    {
        $whitelist = self::all();

        $IPKey = array_search($IP->toString(), $whitelist);
        if ($IPKey === false) {
            return false;
        }

        unset($whitelist[$IPKey]);

        return Settings::set('whitelist', $whitelist);
    }

    /**
     * @param IP $IP
     *
     * @return bool
     */
    public static function add($IP)
    {
        $whitelist = self::all();

        $whitelist[] = $IP->toString();

        return Settings::set('whitelist', array_unique($whitelist));
    }

    public static function has($IP)
    {
        $whitelist = self::all();
        return array_search($IP, $whitelist) !== false;
    }
}
