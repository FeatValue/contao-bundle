<?php

declare(strict_types=1);

namespace Featvalue\ContaoBundle\Controller\Backend;

use Contao\BackendUser;
use Contao\CoreBundle\Controller\AbstractBackendController;
use Featvalue\ContaoBundle\Api\SiteApi;
use Featvalue\ContaoBundle\Embed\DashboardRenderer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Task board inside the Contao back end.
 */
#[Route('%contao.backend.route_prefix%/featvalue/tasks', name: 'featvalue_backend_tasks', defaults: ['_scope' => 'backend', '_token_check' => true], methods: ['GET'])]
final class TasksController extends AbstractBackendController
{
    public function __construct(
        private readonly SiteApi $siteApi,
        private readonly DashboardRenderer $renderer,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof BackendUser || (!$user->isAdmin && !$user->hasAccess('featvalue', 'modules'))) {
            throw $this->createAccessDeniedException('FeatValue module not permitted.');
        }
        if (!$this->siteApi->isSetupComplete()) {
            return $this->redirectToRoute('featvalue_backend');
        }
        $title = $this->translator->trans('MOD.featvalue_tasks.0', [], 'contao_modules');

        return $this->render('@FeatvalueContao/backend/tasks.html.twig', [
            'title' => $title,
            'headline' => $title,
            'featvalueHtml' => $this->renderer->html('calc(100vh - 220px)', 'backend', $request, $title),
        ]);
    }
}
