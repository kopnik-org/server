<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class AbstractApiController extends AbstractController
{
    const ERROR_UNAUTHORIZED    = 401; // No authentication
    const ERROR_NOT_VALID       = 2; // Not valid
    const ERROR_NO_WITNESS      = 3; // В системе отсутствуют заверители
    const ERROR_ACCESS_DENIED   = 4; // Доступ запрещен
    const ERROR_NO_PENDING      = 5; // Pending user not found

    /** @var User */
    // для сериалайзера
    protected $user;

    /**
     * В App\Tests\Controller\SecurityController этот метод перегружается!
     *
     * @todo вынести в сервис
     */
    protected function serializeUser(User $user, bool $forcePassport = false): array
    {
        $location = new \stdClass();
        $location->lat = $user->getLatitude();
        $location->lng = $user->getLongitude();

        $isAllowTenChatInviteLink = false;

        if ($this->user->getId() === $user->getId()
            or (
                $this->user->getForeman() and $this->user->getForeman()->getId() === $user->getId()
            )
        ) {
            $isAllowTenChatInviteLink = true;
        }

        return [
            'id' => $user->getId(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'patronymic' => $user->getPatronymic(),
            'locale' => $user->getLocale(),
            'foreman_id' => $user->getForeman() ? $user->getForeman()->getId() : null,
            'witness_id' => $user->getWitness() ? $user->getWitness()->getId() : null,
            'birthyear' => $user->getBirthYear(),
            'location' => $location,
            'rank' => $user->getRank(),
            'role' => $user->getKopnikRole(),
            'isWitness' => $user->isWitness(),
            'status' => $user->getStatus(),
            'photo' => $user->getPhoto(),
            'smallPhoto' => $user->getPhoto(),

            'passport' => ($this->user->getId() === $user->getId() or $forcePassport) ? $user->getPassportCode() : null,
            'foremanRequest_id' => ($this->user->getId() === $user->getId() and $user->getForemanRequest()) ? $user->getForemanRequest()->getId() : null,
            'tenChatInviteLink' => $isAllowTenChatInviteLink ? $user->getTenChatInviteLink() : null, // там где я старшина
            'witnessChatInviteLink' => ($this->user->getId() === $user->getId()) ? $user->getAssuranceChatInviteLink() : null,
        ];
    }

    protected function jsonResponse($data, int $status = 200, array $headers = [], array $context = []): JsonResponse
    {
        return parent::json(['response' => $data], $status, $headers, $context);
    }

    protected function jsonError($code, $msg, ?Request $request = null): JsonResponse
    {
        return new JsonResponse([
            'error' => [
                'error_code' => $code,
                'error_msg'  => $msg,
                //'request_params' => '@todo ',
            ]
        ]);
    }

    protected function jsonErrorWithValidation($code, $msg, $validation_errors, ?Request $request): JsonResponse
    {
        return new JsonResponse([
            'error' => [
                'error_code' => $code,
                'error_msg'  => $msg,
                'validation_errors' => $validation_errors,
                //'request_params' => '@todo ',
            ]
        ]);
    }
}
