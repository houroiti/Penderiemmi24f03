<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    /**
     * "/" has no language yet: pick the browser's preferred one among those we support.
     */
    #[Route('/', name: 'app_root', methods: ['GET'])]
    public function root(Request $request): RedirectResponse
    {
        $locale = $request->getPreferredLanguage(['fr', 'en', 'ar']) ?? 'fr';

        return $this->redirectToRoute('app_home', ['_locale' => $locale]);
    }

    /**
     * Sends each visitor to the right space: back office, personal space or login page.
     */
    #[Route('/{_locale}', name: 'app_home', requirements: ['_locale' => '%app.locales%'], methods: ['GET'])]
    public function home(): RedirectResponse
    {
        if ($this->isGranted('ROLE_SUPER_ADMIN')) {
            return $this->redirectToRoute('admin');
        }

        if ($this->isGranted('ROLE_USER')) {
            return $this->redirectToRoute('user_space');
        }

        return $this->redirectToRoute('app_login');
    }
}
