<?php

declare(strict_types=1);

namespace Majpanel\MajpanelBundle\Tests;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Majpanel\MajpanelBundle\Controller\AdminController;
use Majpanel\MajpanelBundle\DependencyInjection\MajpanelExtension;
use Majpanel\MajpanelBundle\Entity\Blog;
use Majpanel\MajpanelBundle\MajpanelBundle;
use Majpanel\MajpanelBundle\Security\MajpanelAuthenticator;
use Majpanel\MajpanelBundle\Service\FrontendInstaller;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\SecurityBundle\DependencyInjection\SecurityExtension;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Yaml\Yaml;

final class MajpanelBundleTest extends TestCase
{
    public function testBundleExcludesWebpackSourcesFromAssetMapper(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new FrameworkExtension());

        (new MajpanelExtension())->prepend($container);

        $patterns = $container->getExtensionConfig('framework')[0]['asset_mapper']['excluded_patterns'];
        self::assertContains('*/assets/majpanel*', $patterns);
        self::assertContains('*/assets/styles/majpanel.css', $patterns);
        self::assertContains('*/assets/react/**/*.tsx', $patterns);
    }

    public function testDemoBlogIsAStatefulProtectedApiResource(): void
    {
        $resource = (new \ReflectionClass(Blog::class))->getAttributes(ApiResource::class)[0]->newInstance();

        self::assertSame('/admin', $resource->getRoutePrefix());
        self::assertSame("is_granted('ROLE_ADMIN')", $resource->getSecurity());
        self::assertFalse($resource->getStateless());
    }

    public function testFrontendInstallerCreatesAnEncoreReactScaffoldWithoutOverwritingFiles(): void
    {
        $filesystem = new Filesystem();
        $projectDir = sys_get_temp_dir().'/majpanel-frontend-'.bin2hex(random_bytes(6));
        $filesystem->mkdir($projectDir.'/assets');
        $filesystem->dumpFile($projectDir.'/assets/controllers.json', "{\n    \"controllers\": {},\n    \"entrypoints\": []\n}\n");
        $filesystem->dumpFile($projectDir.'/assets/app.js', "import './stimulus_bootstrap.js';\n");
        $filesystem->dumpFile($projectDir.'/assets/stimulus_bootstrap.js', "import { startStimulusApp } from '@symfony/stimulus-bundle';\n");
        $filesystem->dumpFile($projectDir.'/webpack.config.js', <<<'JS'
import Encore from '@symfony/webpack-encore';

Encore
    .setOutputPath('public/build/')
    .setPublicPath('/build')
    .addEntry('app', './assets/app.js')
    .enableReactPreset()
    .enableSourceMaps(true)
;

export default await Encore.getWebpackConfig();
JS);

        try {
            $installer = new FrontendInstaller($filesystem, $projectDir);
            $installed = $installer->install();

            self::assertContains($projectDir.'/package.json', $installed);
            self::assertFileExists($projectDir.'/webpack.config.js');
            self::assertFileExists($projectDir.'/assets/majpanel.ts');
            self::assertFileExists($projectDir.'/assets/majpanel_stimulus_bootstrap.cjs');
            self::assertFileExists($projectDir.'/assets/react/components/EntityCrudGrid.tsx');
            self::assertFileExists($projectDir.'/assets/react/components/RelationAutocomplete.tsx');
            self::assertFileExists($projectDir.'/assets/react/components/RichTextEditor.tsx');

            $styles = (string) file_get_contents($projectDir.'/assets/styles/majpanel.css');
            self::assertStringContainsString('@source "../../templates";', $styles);
            self::assertStringContainsString('@source "../../vendor/majpanel/majpanel-bundle/templates";', $styles);

            $grid = (string) file_get_contents($projectDir.'/assets/react/components/EntityCrudGrid.tsx');
            self::assertStringContainsString("url.searchParams.set('page', String(page))", $grid);
            self::assertStringContainsString("object.totalItems ?? object['hydra:totalItems']", $grid);
            self::assertStringContainsString('hasNextPage', $grid);
            self::assertStringContainsString("type?: 'oneToOne' | 'manyToOne' | 'oneToMany' | 'manyToMany'", $grid);
            self::assertStringContainsString('targetApiUrl?: string', $grid);
            self::assertStringContainsString("typeof value !== 'string' || value.trim() === ''", $grid);
            self::assertStringContainsString('value = null', $grid);

            $webpack = (string) file_get_contents($projectDir.'/webpack.config.js');
            self::assertStringNotContainsString(".addEntry('app', './assets/app.js')", $webpack);
            self::assertStringContainsString(".addEntry('majpanel', './assets/majpanel.ts')", $webpack);
            self::assertStringContainsString(".enableStimulusBridge('./assets/controllers.json')", $webpack);
            self::assertStringContainsString('.enablePostCssLoader()', $webpack);
            self::assertStringContainsString('.enableTypeScriptLoader()', $webpack);

            $controllers = json_decode((string) file_get_contents($projectDir.'/assets/controllers.json'), true, 512, JSON_THROW_ON_ERROR);
            self::assertTrue($controllers['controllers']['@symfony/ux-react']['react']['enabled']);
            self::assertSame([], $installer->install());
        } finally {
            $filesystem->remove($projectDir);
        }
    }

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

    public function testBundleDefinesAProtectedGeneratedEntityRoute(): void
    {
        $controller = new \ReflectionClass(AdminController::class);
        $authorization = $controller->getAttributes(IsGranted::class)[0]->newInstance();
        $route = $controller->getMethod('entity')->getAttributes(Route::class)[0]->newInstance();

        self::assertSame('ROLE_ADMIN', $authorization->attribute);
        self::assertSame('/majpanel/admin/{entity}', $route->path);
        self::assertSame('majpanel_admin_entity', $route->name);
        self::assertSame('[a-z0-9]+(?:[-_][a-z0-9]+)*', $route->requirements['entity']);
        self::assertSame(['GET'], $route->methods);
        self::assertSame(-100, $route->priority);
    }

    public function testSecurityExampleConfiguresTheMajpanelLoginFirewall(): void
    {
        $configuration = Yaml::parseFile(\dirname(__DIR__).'/docs/config-examples/security.yaml');
        $firewalls = $configuration['security']['firewalls'];
        $majpanelFirewall = $firewalls['majpanel'];
        $accessControl = $configuration['security']['access_control'];

        self::assertSame(false, $firewalls['dev']['security']);
        self::assertSame('^/(?:majpanel/admin(?:/|$)|api/admin(?:/|$)|api/docs(?:[./]|$))', $majpanelFirewall['pattern']);
        self::assertArrayNotHasKey('form_login', $majpanelFirewall);
        self::assertArrayHasKey('main', $firewalls);
        self::assertContains(
            ['path' => '^/api/docs(?:[./]|$)', 'roles' => 'ROLE_ADMIN'],
            $accessControl,
        );
    }

    public function testBundleProtectsApiDocsWhenHostHasNoAccessControl(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new SecurityExtension());

        (new MajpanelExtension())->prepend($container);

        $accessControl = $container->getExtensionConfig('security')[0]['access_control'];
        self::assertSame(
            ['path' => '^/api/docs(?:[./]|$)', 'roles' => 'ROLE_ADMIN'],
            $accessControl[1],
        );
        self::assertSame(
            ['path' => '^/api/admin(?:/|$)', 'roles' => 'ROLE_ADMIN'],
            $accessControl[2],
        );
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
        self::assertSame(
            '^/(?:majpanel/admin(?:/|$)|api/admin(?:/|$)|api/docs(?:[./]|$))',
            $bundleFirewallConfig['pattern'],
        );
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
