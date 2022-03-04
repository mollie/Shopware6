<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api;

use Kiener\MolliePayments\Facade\MollieSupportFacade;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\MailTemplate\Exception\MailTransportFailedException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;

class SupportController extends AbstractController
{
    /**
     * @var MollieSupportFacade
     */
    protected $supportFacade;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(
        MollieSupportFacade $supportFacade,
        LoggerInterface     $logger
    )
    {
        $this->supportFacade = $supportFacade;
        $this->logger = $logger;
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/_action/mollie/support/request",
     *         defaults={"auth_enabled"=true}, name="api.action.mollie.support.request", methods={"POST"})
     *
     * @param Request $request
     * @param Context $context
     * @return JsonResponse
     */
    public function requestSupport(RequestDataBag $data, Context $context): JsonResponse
    {
        $name = $data->get('name');
        $email = $data->get('email');
        $subject = $data->get('subject');
        $message = $data->get('message');

        return $this->requestSupportResponse($name, $email, $subject, $message, $context);
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/v{version}/_action/mollie/support/request",
     *         defaults={"auth_enabled"=true}, name="api.action.mollie.support.request.legacy", methods={"POST"})
     *
     * @param Request $request
     * @param Context $context
     * @return JsonResponse
     */
    public function requestSupportLegacy(RequestDataBag $data, Context $context): JsonResponse
    {
        $name = $data->get('name');
        $email = $data->get('email');
        $subject = $data->get('subject');
        $message = $data->get('message');

        return $this->requestSupportResponse($name, $email, $subject, $message, $context);
    }

    private function requestSupportResponse(
        string  $name,
        string  $email,
        string  $subject,
        string  $message,
        Context $context
    ): JsonResponse
    {
        try {
            $mail = $this->supportFacade->request($name, $email, $subject, $message, $context);

            if ($mail instanceof Email) {
                return $this->json(['sent' => true]);
            }
        } catch (MailTransportFailedException $e) {
            $this->logger->error(
                $e->getMessage(),
                [
                    'name' => $name,
                    'email' => $email,
                    'subject' => $subject,
                    'message' => $message,
                    'exceptionParams' => $e->getParameters()
                ]
            );
        }

        return $this->json(['sent' => false]);
    }
}
