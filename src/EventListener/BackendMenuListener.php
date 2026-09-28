<?php

declare(strict_types=1);

namespace Featvalue\ContaoBundle\EventListener;

use Contao\BackendUser;
use Contao\CoreBundle\Event\ContaoCoreEvents;
use Contao\CoreBundle\Event\MenuEvent;
use Featvalue\ContaoBundle\Api\SiteApi;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Adds "FeatValue" (and "Tasks" once setup is complete) to the System group.
 */
#[AsEventListener(ContaoCoreEvents::BACKEND_MENU_BUILD)]
final class BackendMenuListener
{
    public function __construct(
        private readonly RouterInterface $router,
        private readonly RequestStack $requestStack,
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
        private readonly SiteApi $siteApi,
    ) {
    }

    public function __invoke(MenuEvent $event): void
    {
        $tree = $event->getTree();
        if ('mainMenu' !== $tree->getName()) {
            return;
        }
        $user = $this->security->getUser();
        if (!$user instanceof BackendUser || (!$user->isAdmin && !$user->hasAccess('featvalue', 'modules'))) {
            return;
        }
        $group = $tree->getChild('system');
        if ($group === null) {
            return;
        }
        $currentRoute = (string) $this->requestStack->getCurrentRequest()?->attributes->get('_route', '');
        $factory = $event->getFactory();

        $settings = $factory->createItem('featvalue', [
            'label' => $this->translator->trans('MOD.featvalue.0', [], 'contao_modules'),
            'uri' => $this->router->generate('featvalue_backend'),
            'linkAttributes' => [
                'class' => 'navigation featvalue',
                'title' => $this->translator->trans('MOD.featvalue.1', [], 'contao_modules'),
            ],
            'current' => \in_array($currentRoute, ['featvalue_backend', 'featvalue_backend_callback'], true),
        ]);
        $group->addChild($settings);

        $setupComplete = false;
        try {
            $setupComplete = $this->siteApi->isConnected() && $this->siteApi->isSetupComplete();
        } catch (\Throwable) {
            // Table missing until contao:migrate ran.
        }
        if ($setupComplete) {
            $tasks = $factory->createItem('featvalue_tasks', [
                'label' => $this->translator->trans('MOD.featvalue_tasks.0', [], 'contao_modules'),
                'uri' => $this->router->generate('featvalue_backend_tasks'),
                'linkAttributes' => ['class' => 'navigation featvalue_tasks'],
                'current' => $currentRoute === 'featvalue_backend_tasks',
            ]);
            $group->addChild($tasks);
        }
    }
}
