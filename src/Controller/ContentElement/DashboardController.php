<?php

declare(strict_types=1);

namespace Featvalue\ContaoBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Featvalue\ContaoBundle\Embed\DashboardRenderer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Content element "FeatValue dashboard".
 */
#[AsContentElement(type: self::TYPE, category: 'includes')]
final class DashboardController extends AbstractContentElementController
{
    public const TYPE = 'featvalue_dashboard';

    public function __construct(private readonly DashboardRenderer $renderer)
    {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $height = (string) ($model->featvalue_height ?: 'min(800px, 80vh)');
        $template->set('featvalue_html', $this->renderer->html($height, 'frontend', $request, $this->pageTitle($request)));

        $response = $template->getResponse();
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }

    private function pageTitle(Request $request): string
    {
        $page = $request->attributes->get('pageModel');
        $title = \is_object($page) ? (string) ($page->pageTitle ?: $page->title) : '';

        return $title !== '' ? $title : 'FeatValue';
    }
}
