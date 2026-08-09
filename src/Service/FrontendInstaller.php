<?php

declare(strict_types=1);

namespace Majpanel\MajpanelBundle\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

final class FrontendInstaller
{
    public function __construct(
        private readonly Filesystem $filesystem,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    /** @return list<string> */
    public function install(): array
    {
        $bundleDir = \dirname(__DIR__, 2);
        $files = [
            $bundleDir.'/resources/frontend/webpack.config.js' => $this->projectDir.'/webpack.config.js',
            $bundleDir.'/postcss.config.js' => $this->projectDir.'/postcss.config.js',
            $bundleDir.'/tsconfig.json' => $this->projectDir.'/tsconfig.json',
            $bundleDir.'/assets/app.ts' => $this->projectDir.'/assets/majpanel.ts',
            $bundleDir.'/assets/majpanel_stimulus_bootstrap.js' => $this->projectDir.'/assets/majpanel_stimulus_bootstrap.js',
            $bundleDir.'/assets/majpanel.d.ts' => $this->projectDir.'/assets/majpanel.d.ts',
            $bundleDir.'/assets/styles/majpanel.css' => $this->projectDir.'/assets/styles/majpanel.css',
            $bundleDir.'/assets/react/components/EntityCrudGrid.tsx' => $this->projectDir.'/assets/react/components/EntityCrudGrid.tsx',
        ];

        $installed = [];
        foreach ($files as $source => $target) {
            if (is_file($target)) {
                continue;
            }

            $this->filesystem->copy($source, $target);
            $installed[] = $target;
        }

        foreach ([
            $bundleDir.'/resources/frontend/package.json' => $this->projectDir.'/package.json',
            $bundleDir.'/assets/controllers.json' => $this->projectDir.'/assets/controllers.json',
        ] as $source => $target) {
            if ($this->mergeJsonFile($source, $target)) {
                $installed[] = $target;
            }
        }

        return array_values(array_unique($installed));
    }

    private function mergeJsonFile(string $source, string $target): bool
    {
        $defaults = json_decode((string) file_get_contents($source), true, 512, JSON_THROW_ON_ERROR);
        $existing = is_file($target)
            ? json_decode((string) file_get_contents($target), true, 512, JSON_THROW_ON_ERROR)
            : [];
        $merged = array_replace_recursive($defaults, $existing);

        if ($merged === $existing) {
            return false;
        }

        $this->filesystem->dumpFile(
            $target,
            json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n",
        );

        return true;
    }
}
