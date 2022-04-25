<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api;


use Kiener\MolliePayments\Facade\MollieSupportFacade;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\MailTemplate\Exception\MailTransportFailedException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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


    /**
     * @param MollieSupportFacade $supportFacade
     * @param LoggerInterface $logger
     */
    public function __construct(MollieSupportFacade $supportFacade, LoggerInterface $logger)
    {
        $this->supportFacade = $supportFacade;
        $this->logger = $logger;
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/_action/mollie/support/request", defaults={"auth_enabled"=true}, name="api.action.mollie.support.request", methods={"POST"})
     *
     * @param Request $request
     * @param Context $context
     * @return JsonResponse
     */
    public function requestSupport(Request $request, Context $context): JsonResponse
    {
        $data = $request->request;

        $name = $data->get('name');
        $email = $data->get('email');
        $recipientLocale = $data->get('recipientLocale');
        $subject = $data->get('subject');
        $message = $data->get('message');

        return $this->sendSupportRequest(
            $name,
            $email,
            $recipientLocale,
            $request->getHost(),
            $subject,
            $message,
            $context
        );
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/v{version}/_action/mollie/support/request", defaults={"auth_enabled"=true}, name="api.action.mollie.support.request.legacy", methods={"POST"})
     *
     * @param Request $request
     * @param Context $context
     * @return JsonResponse
     */
    public function requestSupportLegacy(Request $request, Context $context): JsonResponse
    {
        $data = $request->request;

        $name = $data->get('name');
        $email = $data->get('email');
        $recipientLocale = $data->get('recipientLocale');
        $subject = $data->get('subject');
        $message = $data->get('message');

        return $this->sendSupportRequest(
            $name,
            $email,
            $recipientLocale,
            $request->getHost(),
            $subject,
            $message,
            $context
        );
    }

    /**
     * @param string $name
     * @param string $email
     * @param string|null $recipientLocale
     * @param string $host
     * @param string $subject
     * @param string $message
     * @param Context $context
     * @return JsonResponse
     */
    private function sendSupportRequest(string $name, string $email, ?string $recipientLocale, string $host, string $subject, string $message, Context $context): JsonResponse
    {
        try {

            $this->logger->info('Sending Support Request to Mollie: ' . $subject);

            $this->supportFacade->sendSupportRequest(
                $name,
                $email,
                $recipientLocale,
                $host,
                $subject,
                $message,
                $context
            );

            return $this->json(
                [
                    'success' => true,
                ]
            );

        } catch (ConstraintViolationException|MailTransportFailedException $e) {

            $this->logger->error(
                $e->getMessage(),
                [
                    'error' => $message,
                    'exceptionParams' => $e->getParameters()
                ]
            );

            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);

        } catch (\Throwable $e) {

            $this->logger->error(
                $e->getMessage(),
                [
                    'error' => $message,
                ]
            );

            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);

        }
    }

}
