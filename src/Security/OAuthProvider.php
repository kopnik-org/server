<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Entity\UserOauth;
use Doctrine\ORM\EntityManagerInterface;
use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use HWI\Bundle\OAuthBundle\Security\Core\User\OAuthAwareUserProviderInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class OAuthProvider implements UserProviderInterface, OAuthAwareUserProviderInterface
{
    protected EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * Loads the user by a given UserResponseInterface object.
     *
     * @return UserInterface
     *
     * @throws UsernameNotFoundException if the user is not found
     */
    public function loadUserByOAuthUserResponse(UserResponseInterface $response)
    {
        $userOauth = $this->em->getRepository(UserOauth::class)->findOneBy(['identifier' => $response->getUsername()]);

        if (empty($userOauth)) {
            if ($response->getEmail()) {
                $userOauth = $this->em->getRepository(UserOauth::class)->findOneBy(['email' => $response->getEmail()]);
            }

            if ($userOauth) {
                $user = $userOauth->getUser();
            } else {
                $user = new User();

                $source = $response->getResourceOwner();

                if ($source->getName() === 'vkontakte') {
                    $user
                        ->setFirstName($response->getFirstName())
                        ->setLastName($response->getLastName())
                        ->setPhoto($response->getProfilePicture())
                        ->setSmallPhoto($response->getProfilePicture())
                    ;
                }

                if ($source->getName() === 'github') {
                    $user->setFirstName($response->getData()['name']);
                }
            }

            $userOauth = new UserOauth();
            $userOauth
                ->setUser($user)
                ->setEmail($response->getEmail())
                ->setIdentifier($response->getUsername())
                ->setAccessToken($response->getAccessToken())
                ->setProvider($response->getResourceOwner()->getName())
            ;

            $this->em->persist($user);
            $this->em->persist($userOauth);
            $this->em->flush();


        } else {
            $user = $userOauth->getUser();

            if ($user->getPhoto() !== $response->getProfilePicture()) {
                $user
                    ->setPhoto($response->getProfilePicture())
                    ->setSmallPhoto($response->getProfilePicture())
                ;

                $this->em->flush();
            }
        }

        return $user;
    }

    /**
     * Loads the user for the given username.
     * This method must throw UsernameNotFoundException if the user is not
     * found.
     *
     * @return UserInterface
     * @throws UsernameNotFoundException if the user is not found
     */
    public function loadUserByUsername(string $username)
    {
        // TODO: Implement loadUserByUsername() method.
    }

    /**
     * Refreshes the user.
     * It is up to the implementation to decide if the user data should be
     * totally reloaded (e.g. from the database), or if the UserInterface
     * object can just be merged into some internal array of users / identity
     * map.
     *
     * @return UserInterface
     * @throws UnsupportedUserException  if the user is not supported
     * @throws UsernameNotFoundException if the user is not found
     */
    public function refreshUser(UserInterface $user)
    {
        return $user;
    }

    /**
     * Whether this provider supports the given user class.
     *
     * @return bool
     */
    public function supportsClass(string $class)
    {
        // TODO: Implement supportsClass() method.

        return true;
    }
}
