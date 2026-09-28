<?php

declare(strict_types=1);

namespace Featvalue\ContaoBundle\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Contao\ManagerPlugin\Routing\RoutingPluginInterface;
use Featvalue\ContaoBundle\FeatvalueContaoBundle;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouteCollection;

final class Plugin implements BundlePluginInterface, RoutingPluginInterface
{
    public function getBundles(ParserInterface $parser): array
    {
        return [
            BundleConfig::create(FeatvalueContaoBundle::class)
                ->setLoadAfter([ContaoCoreBundle::class]),
        ];
    }

    public function getRouteCollection(LoaderResolverInterface $resolver, KernelInterface $kernel): RouteCollection|null
    {
        $file = __DIR__.'/../../config/routes.yaml';
        $loader = $resolver->resolve($file);

        return $loader ? $loader->load($file) : null;
    }
}
