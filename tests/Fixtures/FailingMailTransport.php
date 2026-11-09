<?php

namespace Tok3\SectionEngineNewsletter\Tests\Fixtures;

use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

class FailingMailTransport extends AbstractTransport
{
    public int $attempts = 0;

    protected function doSend(SentMessage $message): void
    {
        $this->attempts++;

        throw new TransportException('Confirmation transport unavailable');
    }

    public function __toString(): string
    {
        return 'failing-newsletter-transport';
    }
}
