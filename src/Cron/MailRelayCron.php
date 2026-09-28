<?php

declare(strict_types=1);

namespace Featvalue\ContaoBundle\Cron;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Featvalue\ContaoBundle\Mail\MailRelay;

/**
 * Pulls pending FeatValue emails every five minutes (webhook pulls immediately).
 */
#[AsCronJob('*/5 * * * *')]
final class MailRelayCron
{
    public function __construct(private readonly MailRelay $mailRelay)
    {
    }

    public function __invoke(): void
    {
        $this->mailRelay->pull();
    }
}
