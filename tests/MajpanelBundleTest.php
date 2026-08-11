<?php

declare(strict_types=1);

namespace Majpanel\MajpanelBundle\Tests;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Majpanel\MajpanelBundle\ApiPlatform\AdminFilterMetadataCollectionFactory;
use Majpanel\MajpanelBundle\Controller\AdminController;
use Majpanel\MajpanelBundle\DependencyInjection\MajpanelExtension;
use Majpanel\MajpanelBundle\MajpanelBundle;
use Majpanel\MajpanelBundle\Security\MajpanelAuthenticator;
use Majpanel\MajpanelBundle\Service\AdminGenerator;
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
    public function testGeneratorRendersASeparateCustomizableSearchComponent(): void
    {
        $reflection = new \ReflectionClass(AdminGenerator::class);
        $generator = $reflection->newInstanceWithoutConstructor();
        $source = $reflection->getMethod('renderSearchComponent')->invoke($generator, 'ProductAdminSearch');

        self::assertStringContainsString('function ProductAdminSearch', $source);
        self::assertStringContainsString("from '../../components/majpanel/EntityGridSearch'", $source);
        self::assertStringContainsString('<EntityGridSearch {...props} />', $source);
    }

    public function testGeneratorRendersAnEntitySpecificSymfonyControllerAndTemplate(): void
    {
        $reflection = new \ReflectionClass(AdminGenerator::class);
        $generator = $reflection->newInstanceWithoutConstructor();
        $controller = $reflection->getMethod('renderSymfonyController')->invoke(
            $generator,
            'ProductController',
            'products',
            'majpanel_admin_products',
        );
        $template = $reflection->getMethod('renderEntityTemplate')->invoke(
            $generator,
            'ProductAdmin',
            'Products',
            '/api/admin/products',
        );

        self::assertStringContainsString('namespace App\\Controller\\Majpanel;', $controller);
        self::assertStringContainsString('final class ProductController', $controller);
        self::assertStringContainsString("#[Route('/majpanel/admin/products', name: 'majpanel_admin_products'", $controller);
        self::assertStringContainsString("render('admin/majpanel/products/index.html.twig')", $controller);
        self::assertStringContainsString("react_component('majpanel/ProductAdmin'", $template);
    }

    public function testGeneratorExplainsRequiredAdminApiResourceConfiguration(): void
    {
        $reflection = new \ReflectionClass(AdminGenerator::class);
        $generator = $reflection->newInstanceWithoutConstructor();
        $message = $reflection->getMethod('adminResourceConfigurationMessage')->invoke(
            $generator,
            'App\\Entity\\Blog',
            'No protected route was found.',
        );

        self::assertStringContainsString('Cannot generate a Majpanel admin for "App\\Entity\\Blog"', $message);
        self::assertStringContainsString("routePrefix: '/admin'", $message);
        self::assertStringContainsString("security: \"is_granted('ROLE_ADMIN')\"", $message);
        self::assertStringContainsString('stateless: false', $message);
        self::assertStringContainsString('php bin/console cache:clear', $message);
    }

    public function testAdminCollectionReceivesSortingAndExactSearchFilters(): void
    {
        $resource = new ApiResource(
            routePrefix: '/admin',
            operations: [new GetCollection(uriTemplate: '/items')],
        );
        $decorated = new class($resource) implements ResourceMetadataCollectionFactoryInterface {
            public function __construct(private readonly ApiResource $resource)
            {
            }

            public function create(string $resourceClass): ResourceMetadataCollection
            {
                return new ResourceMetadataCollection($resourceClass, [$this->resource]);
            }
        };

        $collection = (new AdminFilterMetadataCollectionFactory($decorated))->create(\stdClass::class);
        $operation = $collection->getOperation(null, true, true);

        self::assertContains(AdminFilterMetadataCollectionFactory::ORDER_FILTER, $operation->getFilters());
        self::assertContains(AdminFilterMetadataCollectionFactory::SEARCH_FILTER, $operation->getFilters());
        self::assertContains(AdminFilterMetadataCollectionFactory::GRID_SEARCH_FILTER, $operation->getFilters());
    }

    public function testBundleExcludesWebpackSourcesFromAssetMapper(): void
    {
        if (!interface_exists(\Symfony\Component\AssetMapper\AssetMapperInterface::class)) {
            self::markTestSkipped('AssetMapper is not installed in this test environment.');
        }

        $container = new ContainerBuilder();
        $container->registerExtension(new FrameworkExtension());

        (new MajpanelExtension())->prepend($container);

        $patterns = $container->getExtensionConfig('framework')[0]['asset_mapper']['excluded_patterns'];
        self::assertContains('*/assets/majpanel*', $patterns);
        self::assertContains('*/assets/styles/majpanel.css', $patterns);
        self::assertContains('*/assets/react/**/*.tsx', $patterns);
    }

    public function testFrontendInstallerCreatesAnEncoreReactScaffoldWithoutOverwritingFiles(): void
    {
        $filesystem = new Filesystem();
        $projectDir = sys_get_temp_dir().'/majpanel-frontend-'.bin2hex(random_bytes(6));
        $filesystem->mkdir($projectDir.'/assets');
        $filesystem->dumpFile($projectDir.'/assets/react/components/EntityCrudGrid.tsx', "legacy grid\n");
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
            self::assertFileExists($projectDir.'/assets/react/components/majpanel/EntityCrudGrid.tsx');
            self::assertFileExists($projectDir.'/assets/react/components/majpanel/EntityGridSearch.tsx');
            self::assertFileExists($projectDir.'/assets/react/components/majpanel/RelationAutocomplete.tsx');
            self::assertFileExists($projectDir.'/assets/react/components/majpanel/RichTextEditor.tsx');
            self::assertFileExists($projectDir.'/assets/react/components/majpanel/entity-fields/EntityFieldInput.tsx');
            self::assertFileExists($projectDir.'/assets/react/components/majpanel/entity-fields/RelationEntityGridValue.tsx');
            self::assertFileExists($projectDir.'/assets/react/components/majpanel/entity-fields/types.ts');
            self::assertFileDoesNotExist($projectDir.'/assets/react/components/EntityCrudGrid.tsx');

            $styles = (string) file_get_contents($projectDir.'/assets/styles/majpanel.css');
            self::assertStringContainsString('@source "../../templates";', $styles);
            self::assertStringContainsString('@source "../../vendor/majpanel/majpanel-bundle/templates";', $styles);

            $grid = (string) file_get_contents($projectDir.'/assets/react/components/majpanel/EntityCrudGrid.tsx');
            self::assertStringContainsString("url.searchParams.set('page', String(page))", $grid);
            self::assertStringContainsString('`order[${sort.fieldName}]`', $grid);
            self::assertStringContainsString("object.totalItems ?? object['hydra:totalItems']", $grid);
            self::assertStringContainsString('hasNextPage', $grid);
            self::assertStringContainsString("typeof value !== 'string' || value.trim() === ''", $grid);
            self::assertStringContainsString('value = null', $grid);

            $fieldTypes = (string) file_get_contents($projectDir.'/assets/react/components/majpanel/entity-fields/types.ts');
            self::assertStringContainsString("type?: 'oneToOne' | 'manyToOne' | 'oneToMany' | 'manyToMany'", $fieldTypes);
            self::assertStringContainsString('targetApiUrl?: string', $fieldTypes);

            $relationAutocomplete = (string) file_get_contents($projectDir.'/assets/react/components/majpanel/RelationAutocomplete.tsx');
            self::assertStringContainsString('style: { zIndex: 1500 }', $relationAutocomplete);

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

    public function testBundleKeepsALowPriorityLegacyEntityRouteDuringMigration(): void
    {
        $controller = new \ReflectionClass(AdminController::class);
        $route = $controller->getMethod('entity')->getAttributes(Route::class)[0]->newInstance();

        self::assertSame('majpanel_admin_entity', $route->name);
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
