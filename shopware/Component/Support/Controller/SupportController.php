<?php

declare(strict_types=1);

namespace Mollie\Shopware\Component\Support\Controller;

use Mollie\Shopware\Component\Support\SupportMailerInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\MailTemplate\Exception\MailTransportFailedException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api'], 'auth_required' => true, 'auth_enabled' => true])]
final class SupportController extends AbstractController
{
    public function __construct(
        private readonly SupportMailerInterface $supportMailer,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route(
        path: '/api/_action/mollie/support/request',
        name: 'api.action.mollie.support.request',
        methods: ['POST'],
    )]
    public function requestSupport(Request $request, Context $context): JsonResponse
    {
        $name = (string) $request->request->get('name');
        $email = (string) $request->request->get('email');
        $recipientLocale = (string) $request->request->get('recipientLocale');
        $subject = (string) $request->request->get('subject');
        $message = (string) $request->request->get('message');

        try {
            $this->logger->info('Sending Support Request to Mollie: ' . $subject);

            $this->supportMailer->send(
                $name,
                $email,
                $recipientLocale,
                $request->getHost(),
                $subject,
                $message,
                $context
            );

            return new JsonResponse(['success' => true]);
        } catch (ConstraintViolationException|MailTransportFailedException $e) {
            $this->logger->error($e->getMessage(), [
                'error' => $message,
                'exceptionParams' => $e->getParameters(),
            ]);

            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage(), [
                'error' => $message,
            ]);

            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
