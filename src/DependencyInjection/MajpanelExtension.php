<?php

declare(strict_types=1);

namespace Majpanel\MajpanelBundle\DependencyInjection;

use Majpanel\MajpanelBundle\Entity\AdminUser;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class MajpanelExtension extends Extension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container): void
    {
        if ($container->hasExtension('security')) {
            $container->prependExtensionConfig('security', [
                'password_hashers' => [
                    AdminUser::class => 'auto',
                ],
                'providers' => [
                    'majpanel_admin_provider' => [
                        'entity' => [
                            'class' => AdminUser::class,
                            'property' => 'username',
                        ],
                    ],
                ],
                'firewalls' => [
                    'dev' => [
                        'pattern' => '^/(_profiler|_wdt|assets|build|bundles/majpanel)/',
                        'security' => false,
                    ],
                    'main' => [
                        'lazy' => true,
                        'provider' => 'majpanel_admin_provider',
                        'form_login' => [
                            'login_path' => 'majpanel_login',
                            'check_path' => 'majpanel_login',
                            'enable_csrf' => true,
                            'default_target_path' => 'majpanel_admin_dashboard',
                        ],
                        'logout' => [
                            'path' => 'majpanel_logout',
                            'target' => 'majpanel_login',
                            'enable_csrf' => true,
                        ],
                    ],
                ],
                'access_control' => [
                    ['path' => '^/majpanel/admin/login$', 'roles' => 'PUBLIC_ACCESS'],
                    ['path' => '^/majpanel/admin(?:/|$)', 'roles' => 'ROLE_ADMIN'],
                ],
            ]);
        }

        if ($container->hasExtension('api_platform')) {
            $container->prependExtensionConfig('api_platform', [
                'mapping' => ['paths' => [\dirname(__DIR__).'/Entity']],
            ]);
        }

        if ($container->hasExtension('doctrine_migrations')) {
            $container->prependExtensionConfig('doctrine_migrations', [
                'migrations_paths' => [
                    'Majpanel\\MajpanelBundle\\Migrations' => \dirname(__DIR__, 2).'/migrations',
                ],
            ]);
        }
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(\dirname(__DIR__, 2).'/config'),
        );

        $loader->load('services.yaml');
    }
}
