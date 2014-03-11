<?php

namespace Oro\Bundle\UserBundle\Security;

use Oro\Bundle\UserBundle\Entity\User;

use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

use Doctrine\Common\Cache\Cache;

use Escape\WSSEAuthenticationBundle\Security\Core\Authentication\Provider\Provider;
use Escape\WSSEAuthenticationBundle\Security\Core\Authentication\Token\Token;

class WsseUserProvider extends Provider
{
    /**
     * Need to override parent's "private" declaration
     *
     * @var UserProviderInterface
     */
    protected $userProvider;

    /**
     * Constructor.
     *
     * @param UserProviderInterface    $userProvider An UserProviderInterface instance
     * @param PasswordEncoderInterface $encoder      A PasswordEncoderInterface instance
     * @param Cache                    $nonceCache   Cache instance
     * @param int                      $lifetime     The lifetime, in seconds
     */
    public function __construct(
        UserProviderInterface $userProvider,
        PasswordEncoderInterface $encoder,
        Cache $nonceCache,
        $lifetime = 300
    ) {
        parent::__construct($userProvider, $encoder, $nonceCache, $lifetime);

        $this->userProvider = $userProvider;
    }

    /**
     * Authenticate API user by API key
     *
     * @param  TokenInterface $token
     *
     * @return Token
     * @throws AuthenticationException
     */
    public function authenticate(TokenInterface $token)
    {
        /** @var User $user */
        $user = $this->userProvider->loadUserByUsername($token->getUsername());

        if ($user && $user->getApi() &&
            $this->validateDigest(
                $token->getAttribute('digest'),
                $token->getAttribute('nonce'),
                $token->getAttribute('created'),
                $user->getApi()->getApiKey(),
                $user->getSalt()
            )
        ) {
            $authenticatedToken = new Token($user->getRoles());
            $authenticatedToken->setUser($user);
            $authenticatedToken->setAuthenticated(true);

            return $authenticatedToken;
        }

        throw new AuthenticationException('WSSE authentication failed.');
    }
}
