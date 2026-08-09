<?php

declare(strict_types=1);

namespace Majpanel\MajpanelBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminController extends AbstractController
{
    #[Route('/majpanel/admin', name: 'majpanel_admin_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        return $this->render('@Majpanel/admin/index.html.twig');
    }

    #[Route(
        '/majpanel/admin/{entity}',
        name: 'majpanel_admin_entity',
        requirements: ['entity' => '[a-z0-9]+(?:[-_][a-z0-9]+)*'],
        methods: ['GET'],
    )]
    public function entity(string $entity): Response
    {
        return $this->render(sprintf('admin/%s/index.html.twig', $entity));
    }
}
