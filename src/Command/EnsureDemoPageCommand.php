<?php

declare(strict_types=1);

namespace Featvalue\ContaoBundle\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Local demo: creates a theme, layout, root page and a "Client portal" page with
 * the FeatValue dashboard content element when the site has no root page yet.
 */
#[AsCommand(name: 'featvalue:ensure-demo-page', description: 'Creates a demo site with the FeatValue dashboard content element (local development).')]
final class EnsureDemoPageCommand extends Command
{
    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $now = time();
        $rootId = (int) $this->connection->fetchOne("SELECT id FROM tl_page WHERE type = 'root' ORDER BY sorting LIMIT 1");
        if ($rootId < 1) {
            $this->connection->insert('tl_theme', ['tstamp' => $now, 'name' => 'Nordlicht Studio', 'author' => 'FeatValue', 'templates' => '']);
            $themeId = (int) $this->connection->lastInsertId();
            // "rows" is a reserved word in MySQL 8, so quote every identifier.
            $this->connection->executeStatement(
                'INSERT INTO tl_layout (`pid`, `tstamp`, `name`, `rows`, `cols`, `modules`, `template`, `viewport`, `framework`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$themeId, $now, 'Default', '1rw', '1cl', serialize([['mod' => '0', 'col' => 'main', 'enable' => '1']]), 'fe_page', 'width=device-width,initial-scale=1.0', serialize(['layout.css', 'responsive.css'])]
            );
            $layoutId = (int) $this->connection->lastInsertId();
            $this->connection->insert('tl_page', [
                'tstamp' => $now, 'sorting' => 128, 'title' => 'Nordlicht Studio', 'alias' => 'nordlicht-studio', 'type' => 'root',
                'language' => 'en', 'fallback' => 1, 'includeLayout' => 1, 'layout' => $layoutId, 'published' => 1,
                'useSSL' => 0, 'adminEmail' => 'hello@nordlicht.studio', 'robots' => 'index,follow', 'urlPrefix' => '', 'urlSuffix' => '',
            ]);
            $rootId = (int) $this->connection->lastInsertId();
            $output->writeln('Root page, theme and layout created.');
        }

        $portalId = (int) $this->connection->fetchOne("SELECT id FROM tl_page WHERE alias = 'client-portal' AND pid = ?", [$rootId]);
        if ($portalId < 1) {
            $hasHome = (int) $this->connection->fetchOne("SELECT COUNT(*) FROM tl_page WHERE pid = ? AND type = 'regular'", [$rootId]);
            if ($hasHome < 1) {
                $this->connection->insert('tl_page', [
                    'pid' => $rootId, 'tstamp' => $now, 'sorting' => 128, 'title' => 'Home', 'alias' => 'index', 'type' => 'regular',
                    'robots' => 'index,follow', 'published' => 1,
                ]);
                $homeId = (int) $this->connection->lastInsertId();
                $this->connection->insert('tl_article', ['pid' => $homeId, 'tstamp' => $now, 'sorting' => 128, 'title' => 'Home', 'alias' => 'home', 'author' => 1, 'inColumn' => 'main', 'published' => 1]);
                $articleId = (int) $this->connection->lastInsertId();
                $this->connection->insert('tl_content', [
                    'pid' => $articleId, 'ptable' => 'tl_article', 'tstamp' => $now, 'sorting' => 128, 'type' => 'text',
                    'headline' => serialize(['unit' => 'h1', 'value' => 'We rebuild brands people remember.']),
                    'text' => '<p>Nordlicht Studio partners with ambitious companies from strategy to launch — then keeps the work moving in a client portal on this site.</p><p><a href="{{link_url::client-portal}}">Open client portal</a></p>',
                ]);
            }
            $this->connection->insert('tl_page', [
                'pid' => $rootId, 'tstamp' => $now, 'sorting' => 256, 'title' => 'Client portal', 'alias' => 'client-portal', 'type' => 'regular',
                'robots' => 'index,follow', 'published' => 1,
            ]);
            $portalId = (int) $this->connection->lastInsertId();
            $this->connection->insert('tl_article', ['pid' => $portalId, 'tstamp' => $now, 'sorting' => 128, 'title' => 'Client portal', 'alias' => 'client-portal', 'author' => 1, 'inColumn' => 'main', 'published' => 1]);
            $articleId = (int) $this->connection->lastInsertId();
            $this->connection->insert('tl_content', [
                'pid' => $articleId, 'ptable' => 'tl_article', 'tstamp' => $now, 'sorting' => 128, 'type' => 'featvalue_dashboard',
                'headline' => serialize(['unit' => 'h1', 'value' => 'Your work, in one place']),
            ]);
            $output->writeln('Client portal page with the FeatValue dashboard created.');
        } else {
            $output->writeln('Client portal page already exists.');
        }

        return Command::SUCCESS;
    }
}
