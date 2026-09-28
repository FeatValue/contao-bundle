<?php

declare(strict_types=1);

namespace Featvalue\ContaoBundle\Controller\Api;

use Featvalue\ContaoBundle\Api\HealthService;
use Featvalue\ContaoBundle\Crypto\KeyStore;
use Featvalue\ContaoBundle\Embed\DashboardRenderer;
use Featvalue\ContaoBundle\Mail\MailRelay;
use Featvalue\ContaoBundle\User\CmsUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Signed endpoints for FeatValue integrations: health check, webhook, resend.
 */
#[Route(defaults: ['_scope' => 'frontend', '_token_check' => false])]
final class ApiController extends AbstractController
{
    public function __construct(
        private readonly HealthService $health,
        private readonly KeyStore $keyStore,
        private readonly MailRelay $mailRelay,
        private readonly DashboardRenderer $renderer,
        private readonly CmsUser $cmsUser,
    ) {
    }

    #[Route('/featvalue/v1/health', name: 'featvalue_health', methods: ['POST'])]
    public function health(Request $request): JsonResponse
    {
        $data = json_decode((string) $request->getContent(), true);
        $challenge = \is_array($data) ? (string) ($data['challenge'] ?? '') : '';
        if ($challenge === '') {
            $challenge = (string) $request->request->get('challenge', '');
        }
        if (!HealthService::isValidChallenge($challenge)) {
            return new JsonResponse(['error' => 'invalid_challenge'], 400);
        }
        try {
            $this->keyStore->ensureKeypair();

            return new JsonResponse(['signature' => $this->health->signChallenge($challenge)]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/featvalue/v1/webhook', name: 'featvalue_webhook', methods: ['POST'])]
    public function webhook(Request $request): JsonResponse
    {
        $raw = (string) $request->getContent();
        $ts = (string) $request->headers->get('X-Fv-Timestamp', '');
        $sig = (string) $request->headers->get('X-Fv-Signature', '');
        if (!$this->health->verifyWebhook($raw, $ts, $sig)) {
            return new JsonResponse(['error' => 'invalid_signature'], 403);
        }
        $payload = json_decode($raw, true);
        if (\is_array($payload) && ($payload['event'] ?? '') === 'mail_pending') {
            try {
                $this->mailRelay->pull();
            } catch (\Throwable $e) {
                return new JsonResponse(['error' => $e->getMessage()], 500);
            }
        }

        return new JsonResponse(['status' => 'ok']);
    }

    #[Route('/featvalue/v1/resend-email-verification', name: 'featvalue_resend_email_verification', methods: ['POST'])]
    public function resendEmailVerification(Request $request): JsonResponse
    {
        $user = $this->cmsUser->current();
        if ($user === null) {
            return new JsonResponse(['error' => 'login_required'], 401);
        }
        // Same-origin only: the button lives on this site.
        $origin = (string) ($request->headers->get('Origin') ?: $request->headers->get('Referer', ''));
        if ($origin !== '' && !str_starts_with($origin, $request->getSchemeAndHttpHost())) {
            return new JsonResponse(['error' => 'forbidden'], 403);
        }
        $embed = $this->renderer->fetchDashboardEmbed($user, true);

        return new JsonResponse([
            'email_verification_required' => !empty($embed['email_verification_required']),
            'verification_email_sent' => !empty($embed['email_verification_required']),
            'embed_url' => $embed['embed_url'] ?? '',
        ]);
    }
}
