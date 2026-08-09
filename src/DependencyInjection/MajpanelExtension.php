<?php

declare(strict_types=1);

namespace Majpanel\MajpanelBundle\DependencyInjection;

use Majpanel\MajpanelBundle\Security\MajpanelAuthenticator;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Yaml\Yaml;

final class MajpanelExtension extends Extension implements PrependExtensionInterface
{
    private const FIREWALL_NAME = 'majpanel';

    public function prepend(ContainerBuilder $container): void
    {
        if ($container->hasExtension('framework') && interface_exists(\Symfony\Component\AssetMapper\AssetMapperInterface::class)) {
            $container->prependExtensionConfig('framework', [
                'asset_mapper' => [
                    'excluded_patterns' => [
                        '*/assets/majpanel*',
                        '*/assets/styles/majpanel.css',
                        '*/assets/react/*.tsx',
                        '*/assets/react/**/*.tsx',
                    ],
                ],
            ]);
        }

        if ($container->hasExtension('security')) {
            $hostSecurityConfigs = $container->getExtensionConfig('security');
            $securityConfig = $this->loadSecurityConfig();

            $hostDefinesAccessControl = false;
            foreach ($hostSecurityConfigs as $config) {
                if (array_key_exists('access_control', $config)) {
                    $hostDefinesAccessControl = true;
                    break;
                }
            }

            if (!$hostDefinesAccessControl) {
                $securityConfig['access_control'] = [
                    ['path' => '^/majpanel/admin/login$', 'roles' => 'PUBLIC_ACCESS'],
                    ['path' => '^/api/docs(?:[./]|$)', 'roles' => 'ROLE_ADMIN'],
                    ['path' => '^/api/admin(?:/|$)', 'roles' => 'ROLE_ADMIN'],
                    ['path' => '^/majpanel/admin(?:/|$)', 'roles' => 'ROLE_ADMIN'],
                ];
            }

            $container->prependExtensionConfig('security', $securityConfig);

            if ($this->hostDefinesMajpanelFirewall($hostSecurityConfigs)) {
                // Symfony requires firewall names and their order to be declared
                // by the host. The bundle can safely complete an existing one.
                $container->loadFromExtension('security', [
                    'firewalls' => [
                        self::FIREWALL_NAME => [
                            'pattern' => '^/(?:majpanel/admin(?:/|$)|api/admin(?:/|$)|api/docs(?:[./]|$))',
                            'lazy' => true,
                            'provider' => 'majpanel_admin_provider',
                            'custom_authenticators' => [MajpanelAuthenticator::class],
                            'entry_point' => MajpanelAuthenticator::class,
                            'logout' => [
                                'path' => 'majpanel_logout',
                                'target' => 'majpanel_login',
                                'enable_csrf' => true,
                            ],
                        ],
                    ],
                ]);
            }
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

    /** @return array<string, mixed> */
    private function loadSecurityConfig(): array
    {
        $configuration = Yaml::parseFile(\dirname(__DIR__, 2).'/config/packages/security.yaml');

        if (!isset($configuration['security']) || !\is_array($configuration['security'])) {
            throw new \LogicException('Majpanel security configuration must contain a "security" section.');
        }

        return $configuration['security'];
    }

    /** @param list<array<string, mixed>> $securityConfigs */
    private function hostDefinesMajpanelFirewall(array $securityConfigs): bool
    {
        foreach ($securityConfigs as $config) {
            if (isset($config['firewalls'][self::FIREWALL_NAME])) {
                return true;
            }
        }

        return false;
    }
}
