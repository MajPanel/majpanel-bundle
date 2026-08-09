<?php

declare(strict_types=1);

namespace Majpanel\MajpanelBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Majpanel\MajpanelBundle\Entity\AdminUser;
use Majpanel\MajpanelBundle\Entity\Blog;
use Majpanel\MajpanelBundle\Service\AdminGenerator;
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
    description: 'Create the first Majpanel administrator and optional demo blog data.',
    aliases: ['majpanel:create-admin'],
)]
final class InitializeMajpanelCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly AdminGenerator $adminGenerator,
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
            ->addOption('no-demo', null, InputOption::VALUE_NONE, 'Do not create sample blog posts')
            ->addOption('reset-password', null, InputOption::VALUE_NONE, 'Replace the password when the administrator already exists')
            ->setHelp(<<<'HELP'
Initializes a development Majpanel installation after migrations have run.

Examples:
  php bin/console majpanel:init
  php bin/console majpanel:create-admin editor 'a-strong-password' --no-demo
  php bin/console majpanel:create-admin admin 'a-new-password' --reset-password --no-demo
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

        $withDemo = !$input->getOption('no-demo');
        if ($withDemo && $this->entityManager->getRepository(Blog::class)->count([]) === 0) {
            $this->entityManager->persist(new Blog('Welcome to Majpanel', 'Your Majpanel administration is ready.'));
            $this->entityManager->persist(new Blog('First steps', 'Create an API Platform entity and run the Majpanel generator.'));
            $io->success('Created sample blog data.');
        }

        $this->entityManager->flush();

        if ($withDemo) {
            $result = $this->adminGenerator->generate(Blog::class);
            $io->success(sprintf('Generated the sample Blog admin at %s.', $result['template']));
        }

        $io->warning('Change the default development password after your first login.');

        return Command::SUCCESS;
    }
}
