api_platform:
    title: MajPanel API
    version: 1.0.0
    defaults:
        # Every API resource is an authenticated admin API by default.
        # Public frontend operations must explicitly override these values.
        route_prefix: /admin
        security: "is_granted('ROLE_ADMIN')"
        stateless: false
        cache_headers:
            vary: ['Content-Type', 'Authorization', 'Origin']
<?php

declare(strict_types=1);

namespace Majpanel\MajpanelBundle\Command;

use Majpanel\MajpanelBundle\Service\AdminGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @author Majid Kazerooni <support@majpanel.com>
 */
#[AsCommand(
    name: 'majpanel',
    description: 'Generate a React and Twig CRUD admin page for an API Platform entity.',
)]
final class MajPanelCommand extends Command
{
    public function __construct(private readonly AdminGenerator $generator)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('EntityName', InputArgument::REQUIRED, 'Entity short name or fully-qualified class name')
            ->addArgument('Command', InputArgument::OPTIONAL, 'generate, reinstall, or delete', 'generate')
            ->setHelp(<<<'HELP'
The command reads Doctrine field and association metadata and API Platform
operations, then generates a typed Material UI DataGrid controller, searchable
relation inputs, a Twig entity page, and a shared menu entry.

Examples:
  php bin/console majpanel Product
  php bin/console majpanel Product reinstall
  php bin/console majpanel Product delete
HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $entityName = trim((string) $input->getArgument('EntityName'));
        $action = strtolower(trim((string) $input->getArgument('Command')));

        if (!in_array($action, ['generate', 'reinstall', 'delete'], true)) {
            $io->error(sprintf('Unknown command "%s". Use generate, reinstall, or delete.', $action));

            return Command::INVALID;
        }

        try {
            if ($action === 'delete') {
                $result = $this->generator->delete($entityName);
                if ($result['component'] === null) {
                    $io->warning(sprintf('%s has no generated admin files.', $result['entity']));
                } else {
                    $io->success(sprintf('Deleted generated admin files for %s.', $result['entity']));
                }

                return Command::SUCCESS;
            }

            $result = $this->generator->generate($entityName);
            $io->success(sprintf('Generated admin CRUD for %s.', $result['entity']));
            $io->definitionList(
                ['API collection' => $result['api_url']],
                ['Doctrine fields' => (string) $result['fields']],
                ['React component' => $result['component']],
                ['Twig template' => $result['template']],
            );

            if ($result['associations'] !== []) {
                $io->note('Generated searchable relation inputs: '.implode(', ', $result['associations']));
            }
            $io->note('Run `npm run dev` (or keep `npm run watch` running) to compile the generated React component.');

            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }
    }
}
