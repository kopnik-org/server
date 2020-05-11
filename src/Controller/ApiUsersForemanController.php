<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Event\UserEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @Route("/api/users")
 */
class ApiUsersForemanController extends AbstractApiController
{
    /**
     * Подать/отменить заявку от имени текущего пользователя на выбор другого пользователя старшиной
     *
     * @Route("/putForemanRequest", methods={"POST"}, name="api_users_put_foreman_request")
     */
    public function putForemanRequest(Request $request, EntityManagerInterface $em, EventDispatcherInterface $dispatcher): JsonResponse
    {
        if (empty($this->getUser())) {
            return $this->jsonError(self::ERROR_UNAUTHORIZED, 'No authentication');
        }

        /** @var User $user */
        $this->user = $user = $this->getUser();

        $input = json_decode($request->getContent(), true);
        $foreman = $input['id'] ?? null;

        if ($foreman) {
            $foreman = $em->getRepository(User::class)->findOneBy(['id' => $foreman]);

            if (empty($foreman)) {
                return $this->jsonError(1000 + 404, 'Старшина не найден');
            }

            if ($foreman->getRole() != User::ROLE_KOPNIK or $foreman->getRole() != User::ROLE_DANILOV_KOPNIK) {
                return $this->jsonError(1000 + 510, 'Старшина не Копник и не Копник по Данилову');
            }

            if ($foreman->getStatus() != User::STATUS_CONFIRMED) {
                return $this->jsonError(1000 + 510, 'Старшина не является заверенным пользователем');
            }
        }

        $user->setForemanRequest($foreman);

        $em->flush();

        $dispatcher->dispatch($user, UserEvent::FOREMAN_REQUEST);

        return $this->json(true);
    }

    /**
     * Получить заявки других пользователей на выбор текущего пользователя своим старшиной.
     *
     * @Route("/getForemanRequests", methods={"GET"}, name="api_users_get_foreman_requests")
     */
    public function getForemanRequests(): JsonResponse
    {
        if (empty($this->getUser())) {
            return $this->jsonError(self::ERROR_UNAUTHORIZED, 'No authentication');
        }

        /** @var User $user */
        $this->user = $user = $this->getUser();

        $response = [];
        foreach ($user->getForemanRequests() as $foremanRequest) {
            $response[] = $this->serializeUser($foremanRequest);
        }

        return $this->json($response);
    }

    /**
     * Одобрить заявку другого пользователя на выбор текущего пользователя старшиной.
     *
     * @Route("/confirmForemanRequest", methods={"POST"}, name="api_users_confirm_foreman_request")
     */
    public function confirmForemanRequest(Request $request, EntityManagerInterface $em, EventDispatcherInterface $dispatcher): JsonResponse
    {
        if (empty($this->getUser())) {
            return $this->jsonError(self::ERROR_UNAUTHORIZED, 'No authentication');
        }

        /** @var User $user */
        $this->user = $user = $this->getUser();

        $input = json_decode($request->getContent(), true);
        $challenger = $input['id'] ?? null; // Идентификатор пользователя, подавшего заявку

        if ($challenger) {
            $challenger = $em->getRepository(User::class)->findOneBy(['id' => $challenger]);

            if (empty($challenger)) {
                return $this->jsonError(1000 + 404, 'User не найден');
            }

            if ($challenger->getForemanRequest() == $user) {
                $user->setForeman($challenger);

                $em->flush();

                $dispatcher->dispatch($challenger, UserEvent::FOREMAN_CONFIRM);
            } else {
                return $this->jsonError(1000 + 511, 'Неверная заявка на выбор старшины');
            }
        } else {
            return $this->jsonError(1000 + 404, 'User не найден');
        }

        return $this->json(true);
    }

    /**
     * Отклонить заявку другого пользователя на выбор текущего пользователя старшиной.
     *
     * @Route("/declineForemanRequest", methods={"POST"}, name="api_users_decline_foreman_request")
     */
    public function declineForemanRequest(Request $request, EntityManagerInterface $em, EventDispatcherInterface $dispatcher): JsonResponse
    {
        if (empty($this->getUser())) {
            return $this->jsonError(self::ERROR_UNAUTHORIZED, 'No authentication');
        }

        /** @var User $user */
        $this->user = $user = $this->getUser();

        $input = json_decode($request->getContent(), true);
        $challenger = $input['id'] ?? null; // Идентификатор пользователя, подавшего заявку

        if ($challenger) {
            $challenger = $em->getRepository(User::class)->findOneBy(['id' => $challenger]);

            if (empty($challenger)) {
                return $this->jsonError(1000 + 404, 'User не найден');
            }

            if ($challenger->getForemanRequest() == $user) {
                $user->setForemanRequest(null);

                $em->flush();

                $dispatcher->dispatch($challenger, UserEvent::FOREMAN_DECLINE);
            } else {
                return $this->jsonError(1000 + 511, 'Неверная заявка на выбор старшины');
            }
        } else {
            return $this->jsonError(1000 + 404, 'User не найден');
        }

        return $this->json(true);
    }

    /**
     * Отменить выбор старшины. Метод имеет двойное значение в зависимости от того присутствует параметр id или нет.
     *
     * Если парамер присутствует, метод имеет значение: текущий пользователь исключает указанного в id пользователя из подчиненных.
     *
     * Если параметр отсутствует, метод имеет следующеее значение: текущий пользователь выходит из подчиненния своего текущего старшины.
     *
     * @Route("/resetForeman", methods={"POST"}, name="api_users_reset_foreman")
     *
     * @todo
     */
    public function resetForeman(Request $request, EntityManagerInterface $em): JsonResponse
    {
        if (empty($this->getUser())) {
            return $this->jsonError(self::ERROR_UNAUTHORIZED, 'No authentication');
        }

        /** @var User $user */
        $this->user = $user = $this->getUser();

        return $this->json(true);
    }

    /**
     * Получить подчиненных пользователя. Если параметр id===null, метод работает для текущего пользователя.
     *
     * @Route("/getSubordinates", methods={"GET"}, name="api_users_get_subordinates")
     */
    public function getSubordinates(Request $request, EntityManagerInterface $em): JsonResponse
    {
        if (empty($this->getUser())) {
            return $this->jsonError(self::ERROR_UNAUTHORIZED, 'No authentication');
        }

        $this->user = $this->getUser();

        $user = $em->getRepository(User::class)->find($request->query->get('id'));

        if (empty($user)) {
            $user = $this->getUser();
        }

        $response = [];
        foreach ($user->getSubordinatesUsers() as $subordinatesUser) {
            $response[] = $this->serializeUser($subordinatesUser);
        }

        return $this->json($response);
    }

    /**
     * Получить подчиненных пользователя включая подчиненных прямых подчиненных. Если параметр id===null, метод работает для текущего пользователя.
     *
     * @Route("/getAllSubordinates", methods={"GET"}, name="api_users_get_all_subordinates")
     *
     * @todo
     */
    public function getAllSubordinates(Request $request, EntityManagerInterface $em): JsonResponse
    {
        if (empty($this->getUser())) {
            return $this->jsonError(self::ERROR_UNAUTHORIZED, 'No authentication');
        }

        /** @var User $user */
        $this->user = $user = $this->getUser();

        return $this->json(true);
    }

    /**
     * Получить старшину пользователя. Если параметр id===null, метод работает для текущего пльзователя.
     *
     * @Route("/getForeman", methods={"GET"}, name="api_users_get_foreman")
     */
    public function getForeman(Request $request, EntityManagerInterface $em): JsonResponse
    {
        if (empty($this->getUser())) {
            return $this->jsonError(self::ERROR_UNAUTHORIZED, 'No authentication');
        }

        /** @var User $user */
        $this->user = $user = $this->getUser();

        $user = $em->getRepository(User::class)->find($request->query->get('id'));

        if (empty($user)) {
            $user = $this->getUser();
        }

        $foreman = $user->getForeman();

        if ($foreman) {
            return $this->json($this->serializeUser($foreman));
        }

        return $this->json(null);
    }

    /**
     * Получить всех старшин пользователя в порядке близости по копному дереву (непосредственный старшина идет первым в списке).
     * Если параметр id===null, метод работает для текущего пльзователя.
     *
     * @Route("/getAllForemans", methods={"GET"}, name="api_users_get_all_foremans")
     *
     * @todo
     */
    public function getAllForemans(Request $request, EntityManagerInterface $em): JsonResponse
    {
        if (empty($this->getUser())) {
            return $this->jsonError(self::ERROR_UNAUTHORIZED, 'No authentication');
        }

        /** @var User $user */
        $this->user = $user = $this->getUser();

        return $this->json(true);
    }
}