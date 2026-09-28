<?php

declare(strict_types=1);

namespace Featvalue\ContaoBundle\Controller\FrontendModule;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\ModuleModel;
use Featvalue\ContaoBundle\Embed\DashboardRenderer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Front end module "FeatValue dashboard" (for layouts and theme sections).
 */
#[AsFrontendModule(type: self::TYPE, category: 'miscellaneous')]
final class DashboardController extends AbstractFrontendModuleController
{
    public const TYPE = 'featvalue_dashboard';

    public function __construct(private readonly DashboardRenderer $renderer)
    {
    }

    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        $height = (string) ($model->featvalue_height ?: 'min(800px, 80vh)');
        $page = $request->attributes->get('pageModel');
        $title = \is_object($page) ? (string) ($page->pageTitle ?: $page->title) : 'FeatValue';
        $template->set('featvalue_html', $this->renderer->html($height, 'frontend', $request, $title));

        $response = $template->getResponse();
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
