<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Support\Fake;

use Shopware\Core\Content\Mail\Service\AbstractMailSender;
use Symfony\Component\Mime\Email;

final class FakeMailSender extends AbstractMailSender
{
    /** @var list<Email> */
    private array $sent = [];

    public function getDecorated(): AbstractMailSender
    {
        return $this;
    }

    public function send(Email $email): void
    {
        $this->sent[] = $email;
    }

    /**
     * @return list<Email>
     */
    public function getSent(): array
    {
        return $this->sent;
    }
}
