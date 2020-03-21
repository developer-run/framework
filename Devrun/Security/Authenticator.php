<?php

namespace Devrun\Security;

use Nette;
use Nette\Security\AuthenticationException;
use Nette\Security\Identity;

/**
 * Users authenticator.
 */
class Authenticator implements Nette\Security\IAuthenticator
{

    /** @var string */
    protected $adminLogin;

    /** @var string */
    protected $adminPassword;


    /**
     * @param $adminLogin
     * @param $adminPassword
     */
    public function __construct($adminLogin, $adminPassword)
    {
        $this->adminLogin    = $adminLogin;
        $this->adminPassword = $adminPassword;
    }


    /**
     * Performs an authentication
     *
     * @param array
     * @return \Nette\Security\Identity
     * @throws \Nette\Security\AuthenticationException
     */
    public function authenticate(array $credentials): Nette\Security\IIdentity
    {
        list($username, $password) = $credentials;

        if (!$username OR !$password) {
            throw new AuthenticationException('The username or password is not filled.', self::INVALID_CREDENTIAL);
        }

        if ($this->adminLogin != $username) {
            throw new AuthenticationException('The username is incorrect.', self::INVALID_CREDENTIAL);
        }

        if ($this->adminPassword != $password) {
            throw new AuthenticationException('The password is incorrect.', self::IDENTITY_NOT_FOUND);
        }

        return new Identity($username, array('admin'));
    }


}
