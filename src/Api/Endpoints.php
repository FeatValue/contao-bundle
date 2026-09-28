<?php

declare(strict_types=1);

namespace Featvalue\ContaoBundle\Api;

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Featvalue\ContaoBundle\Targets;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * FeatValue service URLs. Overrides for local stacks via environment:
 * FEATVALUE_INTEGRATIONS_URL, FEATVALUE_INTEGRATIONS_URL_SERVER,
 * FEATVALUE_DASHBOARD_URL, FEATVALUE_APP_URL, FEATVALUE_FORCE_LOCAL_EMBED_HOST.
 */
final class Endpoints
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ContaoFramework $framework,
    ) {
    }

    public function integrationsUrlForBrowser(): string
    {
        return self::env('FEATVALUE_INTEGRATIONS_URL')
            ?? self::localConstant('INTEGRATIONS')
            ?? 'https://integrations.featvalue.io';
    }

    public function integrationsUrlForServer(): string
    {
        $override = self::env('FEATVALUE_INTEGRATIONS_URL_SERVER');
        if ($override !== null) {
            return $override;
        }
        $browser = $this->integrationsUrlForBrowser();
        if (self::localConstant('INTEGRATIONS') === null) {
            return $browser;
        }
        $host = parse_url($browser, PHP_URL_HOST);
        if (\is_string($host) && self::hostResolves($host)) {
            return $browser;
        }
        return $browser;
    }

    /**
     * @return array<string, string>
     */
    public function upstreamHeaders(): array
    {
        $serverHost = parse_url($this->integrationsUrlForServer(), PHP_URL_HOST);
        $publicHost = parse_url($this->integrationsUrlForBrowser(), PHP_URL_HOST);
        if (!\is_string($serverHost) || !\is_string($publicHost) || $serverHost === '' || $publicHost === '') {
            return [];
        }
        if (strcasecmp($serverHost, $publicHost) === 0) {
            return [];
        }

        return ['Host' => $publicHost];
    }

    public function dashboardUrl(): string
    {
        return self::env('FEATVALUE_DASHBOARD_URL') ?? self::localConstant('DASHBOARD') ?? 'https://dashboard.featvalue.io';
    }

    public function appUrl(): string
    {
        return self::env('FEATVALUE_APP_URL') ?? self::localConstant('APP') ?? 'https://app.featvalue.io';
    }

    public function forceLocalEmbedHost(): bool
    {
        return (bool) getenv('FEATVALUE_FORCE_LOCAL_EMBED_HOST');
    }

    public function localDashboardOrigin(): string
    {
        return self::originOf($this->dashboardUrl());
    }

    public static function originOf(string $url): string
    {
        $parts = parse_url($url);
        if (!\is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $parts['scheme'].'://'.$parts['host'].$port;
    }

    public function siteUrl(): string
    {
        $request = $this->requestStack->getMainRequest();
        if ($request === null) {
            return '';
        }

        return rtrim($request->getSchemeAndHttpHost().$request->getBasePath(), '/');
    }

    public function siteName(): string
    {
        try {
            $this->framework->initialize();
            $name = (string) $this->framework->getAdapter(Config::class)->get('websiteTitle');
        } catch (\Throwable) {
            $name = '';
        }
        if ($name !== '') {
            return $name;
        }
        $host = parse_url($this->siteUrl(), PHP_URL_HOST);

        return \is_string($host) && $host !== '' ? $host : 'Contao';
    }

    /**
     * Primary language code of the current request ('' if none).
     */
    public function langRaw(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        $tag = $request?->getLocale() ?? '';

        return strtolower(explode('-', str_replace('_', '-', $tag))[0] ?? '');
    }

    /**
     * Absolute URL of a bundled asset (public/ is published to bundles/featvaluecontao/).
     */
    public function assetUrl(string $path): string
    {
        $request = $this->requestStack->getMainRequest();
        $base = $request ? rtrim($request->getSchemeAndHttpHost().$request->getBasePath(), '/') : '';

        return $base.'/bundles/featvaluecontao/'.ltrim($path, '/');
    }

    private static function env(string $name): string|null
    {
        $value = getenv($name);
        if (!\is_string($value) || $value === '') {
            $value = $_SERVER[$name] ?? $_ENV[$name] ?? null;
        }

        return \is_string($value) && $value !== '' ? rtrim($value, '/') : null;
    }

    /**
     * Optional src/Targets.php overrides the default FeatValue hosts.
     */
    private static function localConstant(string $name): string|null
    {
        $file = \dirname(__DIR__).'/Targets.php';
        if (!is_file($file)) {
            return null;
        }
        require_once $file;
        $const = Targets::class.'::'.$name;
        if (!\defined($const)) {
            return null;
        }
        $value = \constant($const);

        return \is_string($value) && $value !== '' ? rtrim($value, '/') : null;
    }

    private static function hostResolves(string $host): bool
    {
        return gethostbyname($host) !== $host;
    }
}
