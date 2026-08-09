<?php

declare(strict_types=1);

namespace Majpanel\MajpanelBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Majpanel\MajpanelBundle\Controller\AdminController;
use Majpanel\MajpanelBundle\DependencyInjection\MajpanelExtension;
use Majpanel\MajpanelBundle\MajpanelBundle;
use Majpanel\MajpanelBundle\Security\MajpanelAuthenticator;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\SecurityBundle\DependencyInjection\SecurityExtension;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Yaml\Yaml;

final class MajpanelBundleTest extends TestCase
{
    public function testBundleExposesExpectedExtension(): void
    {
        $bundle = new MajpanelBundle();

        self::assertInstanceOf(MajpanelExtension::class, $bundle->getContainerExtension());
        self::assertSame(\dirname(__DIR__), $bundle->getPath());
    }

    public function testBundleDefinesAProtectedAdminDashboardRoute(): void
    {
        $controller = new \ReflectionClass(AdminController::class);
        $authorization = $controller->getAttributes(IsGranted::class)[0]->newInstance();
        $route = $controller->getMethod('dashboard')->getAttributes(Route::class)[0]->newInstance();

        self::assertSame('ROLE_ADMIN', $authorization->attribute);
        self::assertSame('/majpanel/admin', $route->path);
        self::assertSame('majpanel_admin_dashboard', $route->name);
        self::assertSame(['GET'], $route->methods);
    }

    public function testSecurityExampleConfiguresTheMajpanelLoginFirewall(): void
    {
        $configuration = Yaml::parseFile(\dirname(__DIR__).'/docs/config-examples/security.yaml');
        $firewalls = $configuration['security']['firewalls'];
        $majpanelFirewall = $firewalls['majpanel'];

        self::assertSame(false, $firewalls['dev']['security']);
        self::assertSame('^/(?:majpanel/admin|api/admin)(?:/|$)', $majpanelFirewall['pattern']);
        self::assertArrayNotHasKey('form_login', $majpanelFirewall);
        self::assertArrayHasKey('main', $firewalls);
    }

    public function testBundleCompletesAHostDeclaredMajpanelFirewall(): void
    {
        $container = new ContainerBuilder();
        $securityExtension = new SecurityExtension();
        $container->registerExtension($securityExtension);
        (new SecurityBundle())->build($container);
        (new DoctrineBundle())->build($container);
        $container->loadFromExtension('security', [
            'firewalls' => [
                'majpanel' => ['pattern' => '^/majpanel/admin'],
                'main' => ['lazy' => true],
            ],
        ]);

        (new MajpanelExtension())->prepend($container);

        $configs = $container->getExtensionConfig('security');
        $bundleFirewallConfig = $configs[array_key_last($configs)]['firewalls']['majpanel'];

        self::assertSame('majpanel_admin_provider', $bundleFirewallConfig['provider']);
        self::assertSame([MajpanelAuthenticator::class], $bundleFirewallConfig['custom_authenticators']);
        self::assertSame(MajpanelAuthenticator::class, $bundleFirewallConfig['entry_point']);
        self::assertSame('majpanel_logout', $bundleFirewallConfig['logout']['path']);
        self::assertTrue($bundleFirewallConfig['logout']['enable_csrf']);

        $processedConfig = (new Processor())->processConfiguration(
            $securityExtension->getConfiguration($configs, $container),
            $configs,
        );
        self::assertSame(
            [MajpanelAuthenticator::class],
            $processedConfig['firewalls']['majpanel']['custom_authenticators'],
        );
    }
}
