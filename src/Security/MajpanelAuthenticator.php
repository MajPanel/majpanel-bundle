<?php

declare(strict_types=1);

namespace Majpanel\MajpanelBundle\Security;

use Doctrine\ORM\EntityManagerInterface;
use Majpanel\MajpanelBundle\Entity\AdminUser;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

final class MajpanelAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'majpanel_login';
    public const DASHBOARD_ROUTE = 'majpanel_admin_dashboard';
    public const CSRF_TOKEN_ID = 'majpanel_authenticate';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function supports(Request $request): bool
    {
        return $request->isMethod('POST')
            && self::LOGIN_ROUTE === $request->attributes->get('_route');
    }

    public function authenticate(Request $request): Passport
    {
        $username = $this->stringParameter($request, '_username');
        $password = $this->stringParameter($request, '_password', trim: false);
        $csrfToken = $this->stringParameter($request, '_csrf_token', allowEmpty: true, trim: false);

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $username);

        return new Passport(
            new UserBadge(
                $username,
                fn (string $identifier): ?AdminUser => $this->entityManager
                    ->getRepository(AdminUser::class)
                    ->findOneBy(['username' => $identifier]),
            ),
            new PasswordCredentials($password),
            [new CsrfTokenBadge(self::CSRF_TOKEN_ID, $csrfToken)],
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): Response
    {
        if ($request->hasSession()) {
            $targetPath = $this->getTargetPath($request->getSession(), $firewallName);
            if ($targetPath !== null) {
                return new RedirectResponse($targetPath);
            }
        }

        return new RedirectResponse($this->urlGenerator->generate(self::DASHBOARD_ROUTE));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }

    private function stringParameter(
        Request $request,
        string $name,
        bool $allowEmpty = false,
        bool $trim = true,
    ): string {
        $value = $request->request->get($name, '');
        if (!\is_string($value) && !$value instanceof \Stringable) {
            throw new BadRequestException(sprintf('The key "%s" must be a string.', $name));
        }

        $value = (string) $value;
        if ($trim) {
            $value = trim($value);
        }

        if (!$allowEmpty && $value === '') {
            throw new BadCredentialsException(sprintf('The key "%s" must not be empty.', $name));
        }

        return $value;
    }
}
