<?php

declare(strict_types=1);

namespace Majpanel\MajpanelBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

final class MajpanelBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
