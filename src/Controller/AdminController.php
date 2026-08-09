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
}
