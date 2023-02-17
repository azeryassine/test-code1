<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Tool;

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Exception\CryptoException;
use Pimcore\Logger;
use Pimcore\Model\User;
use Pimcore\Tool;
use Symfony\Component\HttpFoundation\Request;

class Authentication
{
    /**
     * @deprecated
     *
     * @param string $username
     * @param string $password
     *
     * @return null|User
     */
    public static function authenticatePlaintext($username, $password)
    {
        trigger_deprecation(
            'pimcore/pimcore',
            '10.6',
            sprintf('%s is deprecated and will be removed in Pimcore 11', __METHOD__),
        );

        /** @var User $user */
        $user = User::getByName($username);

        // user needs to be active, needs a password and an ID (do not allow system user to login, ...)
        if (self::isValidUser($user)) {
            if (self::verifyPassword($user, $password)) {
                $user->setLastLoginDate(); //set user current login date

                return $user;
            }
        }

        return null;
    }

    /**
     * @param Request|null $request
     *
     * @return User|null
     */
    public static function authenticateSession(Request $request = null)
    {
        if (null === $request) {
            $request = \Pimcore::getContainer()->get('request_stack')->getCurrentRequest();

            if (null === $request) {
                return null;
            }
        }

        if (!Session::requestHasSessionId($request, true)) {
            // if no session cookie / ID no authentication possible, we don't need to start a session
            return null;
        }

        $session = Session::getReadOnly();
        $user = $session->get('user');

        if ($user instanceof User) {
            // renew user
            $user = User::getById($user->getId());

            if (self::isValidUser($user)) {
                return $user;
            }
        }

        return null;
    }

    /**
     * @deprecated
     *
     * @throws \Exception
     *
     * @return User
     */
    public static function authenticateHttpBasic()
    {
        trigger_deprecation(
            'pimcore/pimcore',
            '10.6',
            sprintf('%s is deprecated and will be removed in Pimcore 11', __METHOD__),
        );

        // we're using Sabre\HTTP for basic auth
        $request = \Sabre\HTTP\Sapi::getRequest();
        $response = new \Sabre\HTTP\Response();
        $auth = new \Sabre\HTTP\Auth\Basic(Tool::getHostname(), $request, $response);
        $result = $auth->getCredentials();

        if (is_array($result)) {
            list($username, $password) = $result;
            $user = self::authenticatePlaintext($username, $password);
            if ($user) {
                return $user;
            }
        }

        $auth->requireLogin();
        $response->setBody('Authentication required');
        Logger::error('Authentication Basic (WebDAV) required');
        \Sabre\HTTP\Sapi::sendResponse($response);
        die();
    }

    /**
     * @param string $token
     * @param bool $adminRequired
     *
     * @return null|User
     */
    public static function authenticateToken($token, $adminRequired = false)
    {
        $username = null;
        $timestamp = null;

        try {
            $decrypted = self::tokenDecrypt($token);
            list($timestamp, $username) = $decrypted;
        } catch (CryptoException $e) {
            return null;
        }

        $user = User::getByName($username);
        if (self::isValidUser($user)) {
            if ($adminRequired && !$user->isAdmin()) {
                return null;
            }

            $timeZone = date_default_timezone_get();
            date_default_timezone_set('UTC');

            if ($timestamp > time() || $timestamp < (time() - (60 * 60 * 24))) {
                return null;
            }
            date_default_timezone_set($timeZone);

            return $user;
        }

        return null;
    }

    /**
     * @param User $user
     * @param string $password
     *
     * @return bool
     */
    public static function verifyPassword($user, $password)
    {
        if (!$user->getPassword()) {
            // do not allow logins for users without a password
            return false;
        }

        $password = self::preparePlainTextPassword($user->getName(), $password);

        if (!password_verify($password, $user->getPassword())) {
            return false;
        }

        $config = \Pimcore::getContainer()->getParameter('pimcore.config')['security']['password'];

        if (password_needs_rehash($user->getPassword(), $config['algorithm'], $config['options'])) {
            $user->setPassword(self::getPasswordHash($user->getName(), $password));
            $user->save();
        }

        return true;
    }

    /**
     * @param User|null $user
     *
     * @return bool
     */
    public static function isValidUser($user)
    {
        if ($user instanceof User && $user->isActive() && $user->getId() && $user->getPassword()) {
            return true;
        }

        return false;
    }

    /**
     * @internal
     *
     * @param string $username
     * @param string $plainTextPassword
     *
     * @return string
     *
     * @throws \Exception
     */
    public static function getPasswordHash($username, $plainTextPassword)
    {
        $password = self::preparePlainTextPassword($username, $plainTextPassword);
        $config = \Pimcore::getContainer()->getParameter('pimcore.config')['security']['password'];

        if ($hash = password_hash($password, $config['algorithm'], $config['options'])) {
            return $hash;
        }

        throw new \Exception('Unable to create password hash for user: ' . $username);
    }

    /**
     * @param string $username
     * @param string $plainTextPassword
     *
     * @return string
     */
    private static function preparePlainTextPassword($username, $plainTextPassword)
    {
        // plaintext password is prepared as digest A1 hash, this is to be backward compatible because this was
        // the former hashing algorithm in pimcore (< version 2.1.1)
        return md5($username . ':pimcore:' . $plainTextPassword);
    }

    /**
     * @internal
     *
     * @param string $username
     *
     * @return string
     */
    public static function generateToken($username)
    {
        $secret = \Pimcore::getContainer()->getParameter('secret');

        $data = time() - 1 . '|' . $username;
        $token = Crypto::encryptWithPassword($data, $secret);

        return $token;
    }

    /**
     * @param string $token
     *
     * @return array
     */
    private static function tokenDecrypt($token)
    {
        $secret = \Pimcore::getContainer()->getParameter('secret');
        $decrypted = Crypto::decryptWithPassword($token, $secret);

        return explode('|', $decrypted);
    }
}
