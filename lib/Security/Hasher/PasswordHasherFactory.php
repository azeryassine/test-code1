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

namespace Pimcore\Security\Hasher;

use Pimcore\Security\Encoder\EncoderFactoryAwareInterface;
use Pimcore\Security\Hasher\Factory\UserAwarePasswordHasherFactory;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @internal
 *
 * Password encoding and verification for Pimcore objects and admin users is implemented on the user object itself.
 * Therefore the encoder needs the user object when encoding or verifying a password. This factory decorates the core
 * factory and allows to delegate building the encoder to a type specific factory which then is able to create a
 * dedicated encoder for a user object.
 *
 * If the given user is not configured to be handled by one of the encoder factories, the normal framework encoder
 * logic applies.
 */
class PasswordHasherFactory implements PasswordHasherFactoryInterface
{
    /**
     * @var PasswordHasherFactoryInterface
     */
    protected $frameworkFactory;

    /**
     * @var PasswordHasherFactoryInterface[]
     */
    protected $encoderFactories = [];

    /**
     * @param PasswordHasherFactoryInterface $frameworkFactory
     * @param array $encoderFactories
     */
    public function __construct(PasswordHasherFactoryInterface $frameworkFactory, array $encoderFactories = [])
    {
        $this->frameworkFactory = $frameworkFactory;
        $this->encoderFactories = $encoderFactories;
    }

    /**
     * {@inheritdoc}
     */
    public function getPasswordHasher($user): PasswordHasherInterface
    {
        if ($hasher = $this->getPasswordHasherFromFactory($user)) {
            return $hasher;
        }

        // fall back to default implementation
        return $this->frameworkFactory->getPasswordHasher($user);
    }

    /**
     * Returns the password hasher factory to use for the given account.
     *
     * @param UserInterface|string $user A UserInterface instance or a class name
     *
     * @return PasswordHasherInterface|null
     */
    private function getPasswordHasherFromFactory($user)
    {
        $factoryKey = null;

        if ($user instanceof EncoderFactoryAwareInterface && (null !== $factoryName = $user->getEncoderFactoryName())) {
            if (!array_key_exists($factoryName, $this->encoderFactories)) {
                throw new \RuntimeException(sprintf('The encoder factory "%s" was not configured.', $factoryName));
            }

            $factoryKey = $factoryName;
        }
        if ($user instanceof PasswordHasherFactoryAwareInterface && (null !== $factoryName = $user->getHasherFactoryName())) {
            if (!array_key_exists($factoryName, $this->encoderFactories)) {
                throw new \RuntimeException(sprintf('The hasher factory "%s" was not configured.', $factoryName));
            }

            $factoryKey = $factoryName;
        } else {
            foreach ($this->encoderFactories as $class => $factory) {
                if ((is_object($user) && $user instanceof $class) || (!is_object($user) && (is_subclass_of($user, $class) || $user == $class))) {
                    $factoryKey = $class;

                    break;
                }
            }
        }

        if (null !== $factoryKey) {
            $factory = $this->encoderFactories[$factoryKey];

            if ($factory instanceof UserAwarePasswordHasherFactory) {
                return $factory->getPasswordHasher($user);
            }
        }

        return null;
    }
}
