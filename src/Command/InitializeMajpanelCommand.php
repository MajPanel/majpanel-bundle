<?php

declare(strict_types=1);

namespace Majpanel\MajpanelBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Majpanel\MajpanelBundle\Entity\AdminUser;
use Majpanel\MajpanelBundle\Service\FrontendInstaller;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'majpanel:init',
    description: 'Create the first Majpanel administrator and install the frontend scaffold.',
    aliases: ['majpanel:create-admin'],
)]
final class InitializeMajpanelCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly FrontendInstaller $frontendInstaller,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::OPTIONAL, 'Administrator username', 'admin')
            ->addArgument('password', InputArgument::OPTIONAL, 'Administrator password', '123456')
            ->addOption('reset-password', null, InputOption::VALUE_NONE, 'Replace the password when the administrator already exists')
            ->setHelp(<<<'HELP'
Initializes a development Majpanel installation after migrations have run.

Examples:
  php bin/console majpanel:init
  php bin/console majpanel:create-admin editor 'a-strong-password'
  php bin/console majpanel:create-admin admin 'a-new-password' --reset-password
HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $username = trim((string) $input->getArgument('username'));
        $password = (string) $input->getArgument('password');

        if ($username === '' || $password === '') {
            $io->error('Username and password cannot be empty.');

            return Command::INVALID;
        }

        if ($this->environment === 'prod' && $password === '123456') {
            $io->error('The default password is disabled in production. Pass a strong password explicitly.');

            return Command::INVALID;
        }

        $installedFrontendFiles = $this->frontendInstaller->install();
        if ($installedFrontendFiles !== []) {
            $io->success(sprintf('Installed %d Majpanel frontend file(s).', count($installedFrontendFiles)));
            $io->note('Run `npm install` and `npm run dev` to compile the Majpanel CSS and React controllers.');
        }

        $repository = $this->entityManager->getRepository(AdminUser::class);
        $admin = $repository->findOneBy(['username' => $username]);

        if (!$admin instanceof AdminUser) {
            $admin = new AdminUser($username);
            $admin->setRoles(['ROLE_ADMIN']);
            $admin->setPassword($this->passwordHasher->hashPassword($admin, $password));
            $this->entityManager->persist($admin);
            $io->success(sprintf('Created Majpanel administrator "%s".', $username));
        } elseif ($input->getOption('reset-password')) {
            $admin->setPassword($this->passwordHasher->hashPassword($admin, $password));
            $io->success(sprintf('Updated the password for Majpanel administrator "%s".', $username));
        } else {
            $io->note(sprintf('Majpanel administrator "%s" already exists; its password was not changed.', $username));
        }

        $this->entityManager->flush();

        $io->warning('Change the default development password after your first login.');

        return Command::SUCCESS;
    }
}
