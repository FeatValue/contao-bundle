<?php

declare(strict_types=1);

namespace Featvalue\ContaoBundle\Mail;

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Featvalue\ContaoBundle\Api\Endpoints;
use Featvalue\ContaoBundle\Api\SignedClient;
use Featvalue\ContaoBundle\Api\SiteApi;
use Featvalue\ContaoBundle\Store\OptionStore;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Pulls pending FeatValue emails and sends them through the Contao/Symfony mailer.
 */
final class MailRelay
{
    public function __construct(
        private readonly OptionStore $options,
        private readonly SiteApi $siteApi,
        private readonly SignedClient $client,
        private readonly MailerInterface $mailer,
        private readonly ContaoFramework $framework,
        private readonly Endpoints $endpoints,
    ) {
    }

    public function pull(): void
    {
        if (!$this->siteApi->isConnected() || !$this->siteApi->isSetupComplete()) {
            return;
        }
        $siteId = $this->options->getInt(OptionStore::SITE_ID);
        if ($siteId < 1) {
            return;
        }
        try {
            $result = $this->client->request('GET', '/api/self-hosted/'.$siteId.'/relay/messages');
        } catch (\Throwable) {
            return;
        }
        if ($result['status'] !== 200 || !\is_array($result['body']['messages'] ?? null)) {
            return;
        }
        $from = $this->fromAddress();
        $hadError = false;
        foreach ($result['body']['messages'] as $message) {
            if (!\is_array($message) || empty($message['id'])) {
                continue;
            }
            $to = (string) ($message['to_email'] ?? '');
            $id = (int) $message['id'];
            if ($to === '' || $id < 1 || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $sent = false;
            try {
                $email = (new Email())
                    ->to(new Address($to))
                    ->subject((string) ($message['subject'] ?? ''))
                    ->html((string) ($message['html'] ?? ''));
                if ($from !== '') {
                    $email->from(new Address($from));
                }
                $this->mailer->send($email);
                $sent = true;
            } catch (\Throwable) {
                $sent = false;
            }
            try {
                $this->client->request('POST', '/api/self-hosted/'.$siteId.'/relay/messages/ack', $sent
                    ? ['id' => $id, 'status' => 'sent']
                    : ['id' => $id, 'status' => 'failed', 'error' => 'contao_mail_failed']);
            } catch (\Throwable) {
                // Acked on the next pull.
            }
            if (!$sent) {
                $hadError = true;
            }
        }
        if ($hadError) {
            $this->options->set(OptionStore::MAIL_ERROR, 'FeatValue could not send one or more emails via Contao. Please check the mailer configuration.');
        } elseif ($result['body']['messages'] !== []) {
            $this->options->delete(OptionStore::MAIL_ERROR);
        }
    }

    private function fromAddress(): string
    {
        try {
            $this->framework->initialize();
            $admin = (string) $this->framework->getAdapter(Config::class)->get('adminEmail');
            if ($admin !== '' && filter_var($admin, FILTER_VALIDATE_EMAIL)) {
                return $admin;
            }
        } catch (\Throwable) {
            // Fall through.
        }
        $host = parse_url($this->endpoints->siteUrl(), PHP_URL_HOST);

        return \is_string($host) && $host !== '' ? 'noreply@'.$host : '';
    }
}
