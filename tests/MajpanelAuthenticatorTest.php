<?php

declare(strict_types=1);

namespace Majpanel\MajpanelBundle\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Majpanel\MajpanelBundle\Entity\AdminUser;
use Majpanel\MajpanelBundle\Security\MajpanelAuthenticator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

final class MajpanelAuthenticatorTest extends TestCase
{
    public function testItBuildsAPasswordPassportForTheAdminUser(): void
    {
        $admin = new AdminUser('admin');
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(self::once())
            ->method('findOneBy')
            ->with(['username' => 'admin'])
            ->willReturn($admin);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')
            ->with(AdminUser::class)
            ->willReturn($repository);

        $request = Request::create('/majpanel/admin/login', 'POST', [
            '_username' => ' admin ',
            '_password' => 'secret-password',
            '_csrf_token' => 'valid-token',
        ]);
        $request->attributes->set('_route', MajpanelAuthenticator::LOGIN_ROUTE);
        $request->setSession(new Session(new MockArraySessionStorage()));

        $passport = $this->authenticator($entityManager)->authenticate($request);

        self::assertTrue($this->authenticator($entityManager)->supports($request));
        self::assertSame('admin', $request->getSession()->get(SecurityRequestAttributes::LAST_USERNAME));
        self::assertSame($admin, $passport->getBadge(UserBadge::class)->getUser());
        self::assertSame('secret-password', $passport->getBadge(PasswordCredentials::class)->getPassword());
        self::assertSame('valid-token', $passport->getBadge(CsrfTokenBadge::class)->getCsrfToken());
    }

    public function testSuccessfulAuthenticationRedirectsToTheDashboard(): void
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $token = $this->createStub(TokenInterface::class);
        $request = Request::create('/majpanel/admin/login', 'POST');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $response = $this->authenticator($entityManager)->onAuthenticationSuccess($request, $token, 'majpanel');

        self::assertSame('/majpanel/admin', $response->getTargetUrl());
    }

    private function authenticator(EntityManagerInterface $entityManager): MajpanelAuthenticator
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static fn (string $route): string => match ($route) {
                MajpanelAuthenticator::LOGIN_ROUTE => '/majpanel/admin/login',
                MajpanelAuthenticator::DASHBOARD_ROUTE => '/majpanel/admin',
            },
        );

        return new MajpanelAuthenticator($entityManager, $urlGenerator);
    }
}
