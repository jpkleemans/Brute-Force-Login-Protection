<?php

class Whitelist
{

    /**
     * Return array of whitelisted IP addresses.
     * 
     * @return array
     */
    public function getAll()
    {
        $whitelist = get_option('bflp_whitelist');

        if (!is_array($whitelist)) return array();

        return $whitelist;
    }

    /**
     * Add IP to whitelist.
     * 
     * @param string $IP
     * @return boolean
     */
    public function add($IP)
    {
        if (!filter_var($IP, FILTER_VALIDATE_IP)) return false;

        $whitelist = get_option('bflp_whitelist');

        // Create option in database if it doesn't exist
        if (!is_array($whitelist)) {
            $whitelist = array($IP);
            return add_option('bflp_whitelist', $whitelist, '', 'no');
        }

        $whitelist[] = $IP;

        return update_option('bflp_whitelist', array_unique($whitelist));
    }

    /**
     * Remove IP from whitelist.
     * 
     * @param string $IP
     * @return boolean
     */
    public function remove($IP)
    {
        if (!filter_var($IP, FILTER_VALIDATE_IP)) return false;

        $whitelist = get_option('bflp_whitelist');

        if (!is_array($whitelist)) return false;

        $IPKey = array_search($IP, $whitelist);

        if ($IPKey === false) return false;

        unset($whitelist[$IPKey]);

        return update_option('bflp_whitelist', $whitelist);
    }
}
