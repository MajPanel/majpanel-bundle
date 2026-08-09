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
            $securityConfig = [
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
            ];

            $hostDefinesAccessControl = false;
            foreach ($container->getExtensionConfig('security') as $config) {
                if (array_key_exists('access_control', $config)) {
                    $hostDefinesAccessControl = true;
                    break;
                }
            }

            if (!$hostDefinesAccessControl) {
                $securityConfig['access_control'] = [
                    ['path' => '^/majpanel/admin/login$', 'roles' => 'PUBLIC_ACCESS'],
                    ['path' => '^/majpanel/admin(?:/|$)', 'roles' => 'ROLE_ADMIN'],
                ];
            }

            $container->prependExtensionConfig('security', $securityConfig);
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
