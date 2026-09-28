<?php

declare(strict_types=1);

namespace Featvalue\ContaoBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

final class FeatvalueContaoBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
