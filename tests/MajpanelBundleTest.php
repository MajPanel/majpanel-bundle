<?php

declare(strict_types=1);

namespace Majpanel\MajpanelBundle\Tests;

use Majpanel\MajpanelBundle\DependencyInjection\MajpanelExtension;
use Majpanel\MajpanelBundle\MajpanelBundle;
use PHPUnit\Framework\TestCase;

final class MajpanelBundleTest extends TestCase
{
    public function testBundleExposesExpectedExtension(): void
    {
        $bundle = new MajpanelBundle();

        self::assertInstanceOf(MajpanelExtension::class, $bundle->getContainerExtension());
        self::assertSame(\dirname(__DIR__), $bundle->getPath());
    }
}
