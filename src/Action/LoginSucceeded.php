<?php

namespace BFLP\Action;

use BFLP\Repository\LoginAttemptRepository;
use BFLP\Util\RemoteAddress;

class LoginSucceeded
{
    /**
     * @var RemoteAddress
     */
    private $remoteAddress;

    /**
     * @var LoginAttemptRepository
     */
    private $loginAttempts;

    /**
     * LoginSucceeded constructor.
     *
     * @param RemoteAddress $remoteAddress
     * @param LoginAttemptRepository $loginAttempts
     */
    public function __construct(RemoteAddress $remoteAddress,
                                LoginAttemptRepository $loginAttempts)
    {
        $this->remoteAddress = $remoteAddress;
        $this->loginAttempts = $loginAttempts;
    }

    public function __invoke()
    {
        $ip = $this->remoteAddress->getIpAddress();
        $this->loginAttempts->remove($ip);
    }
}
