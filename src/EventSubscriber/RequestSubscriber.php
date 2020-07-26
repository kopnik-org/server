<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use GeoIp2\Exception\AddressNotFoundException;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class RequestSubscriber implements EventSubscriberInterface
{
    use ContainerAwareTrait;

    /** @var RouterInterface */
    protected $router;

    /** @var TokenStorageInterface */
    protected $token_storage;

    /** @var EntityManagerInterface */
    protected $em;

    /**
     * RequestSubscriber constructor.
     */
    public function __construct(
        RouterInterface $router,
        TokenStorageInterface $token_storage,
        ContainerInterface $container,
        EntityManagerInterface $em
    ) {
        $this->container     = $container;
        $this->em            = $em;
        $this->router        = $router;
        $this->token_storage = $token_storage;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST  => 'autoupdateGeoCoords',
//            KernelEvents::REQUEST  => 'validateUser',
//            KernelEvents::REQUEST  => 'onKernelRequest',
//            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    /**
     * @param RequestEvent $event
     */
    public function autoupdateGeoCoords(RequestEvent $event): void
    {
        if (null === $token = $this->token_storage->getToken()) {
            return;
        }

        /** @var User $user */
        if (!\is_object($user = $token->getUser())) {
            // e.g. anonymous authentication
            return;
        }

        $geoIpService = $this->container->get('cravler_max_mind_geo_ip.service.geo_ip_service');

        if (empty($user->getLatitude()) or empty($user->getLongitude())) {
            try {
                $record = $geoIpService->getRecord($event->getRequest()->getClientIp(), 'city', ['locales' => ['ru']]);
                $user
                    ->setLatitude($record->location->latitude)
                    ->setLongitude($record->location->longitude)
                ;

                $this->em->persist($user);
                $this->em->flush();
            } catch (AddressNotFoundException $e) {
                // dummy
            } catch (\InvalidArgumentException $e) {
                // dummy
            }
        }
    }

    /**
     * @param RequestEvent $event
     */
    public function validateUser(RequestEvent $event): void
    {
        if (null === $token = $this->token_storage->getToken()) {
            return;
        }

        /** @var User $user */
        if (!\is_object($user = $token->getUser())) {
            // e.g. anonymous authentication
            return;
        }

        // Для апи запросов, нужно проверять разрешение сообщений от группы вк
        if (
            $event->getRequest()->get('_route') and
            (
                strpos($event->getRequest()->get('_route'), 'api_users_update_profile') === 0
                //or strpos($event->getRequest()->get('_route'), 'api_users_bla_bla__') === 0
            )
            and !$user->isAllowMessagesFromCommunity()
        ) {
            $response = new JsonResponse([
                'error' => [
                    'error_code' => 510,
                    'error_msg'  => 'Message From VK Group Is Not Allowed',
                    'request_params' => '@todo ',
                ]
            ]);

            $event->setResponse($response);
        }
    }

    /**
     * @param RequestEvent $event
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (null === $token = $this->token_storage->getToken()) {
            return;
        }

        /** @var User $user */
        if (!\is_object($user = $token->getUser())) {
            // e.g. anonymous authentication
            return;
        }

        if (empty($user->getPatronymic())
            or empty($user->getBirthYear())
            or empty($user->getPassportCode())
            or empty($user->getLatitude())
            or empty($user->getLongitude())
            or $user->getStatus() == User::STATUS_DECLINE
        ) {
            $route = 'profile';

            if ($route === $event->getRequest()->get('_route')
                or empty($event->getRequest()->get('_route'))
                or strpos($event->getRequest()->get('_route'), 'api_') === 0
            ) {
                return;
            }

            if ($user->getStatus() == User::STATUS_DECLINE) {
                $event->getRequest()->getSession()->getFlashBag()->add('warning', 'Ваша заявка была отклонена. Вы должны поправить ошибку в профиле и опять отправить на заверение.');
            } else {
                $event->getRequest()->getSession()->getFlashBag()->add('warning', 'Первым делом нужно заполнить профиль.');
            }

            $response = new RedirectResponse($this->router->generate($route));
            $event->setResponse($response);
        } elseif (!$user->isAllowMessagesFromCommunity()) {
            $route = 'profile_allow_messages_from_community';

            if ($route === $event->getRequest()->get('_route')) {
                return;
            }

            //$event->getRequest()->getSession()->getFlashBag()->add('warning', 'Необходимо разрешить получение уведомлений от сообщества в VK');

            $response = new RedirectResponse($this->router->generate($route));
            $event->setResponse($response);
        } elseif ($user->getStatus() == User::STATUS_NEW or $user->getStatus() == User::STATUS_PENDING) {
            $route = 'assurance';

            if ($route === $event->getRequest()->get('_route')) {
                return;
            }

            $response = new RedirectResponse($this->router->generate($route));
            $event->setResponse($response);
        }
    }

    /**
     * @param ResponseEvent $event
     */
    public function onKernelResponse(ResponseEvent $event)
    {
        $origin = $event->getRequest()->headers->get('origin', '*');

//        if (
//            $event->getRequest()->getPathInfo() === '/api/users/'
//            || $event->getRequest()->getPathInfo() === '/api/user/*' // @todo
//        ) {
            $event->getResponse()->headers->set('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept');
            $event->getResponse()->headers->set('Access-Control-Allow-Methods', 'POST');
            $event->getResponse()->headers->set('Access-Control-Allow-Origin', $origin);
//        }
    }
}
