<?php

namespace App\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;

/**
 * Common look of both back offices: 3 languages (Arabic switches to right-to-left by itself),
 * our translation file, and the client's colours.
 */
trait PenderieDashboardTrait
{
    private function penderieDashboard(string $title): Dashboard
    {
        return Dashboard::new()
            ->setTitle($title)
            ->setTranslationDomain('messages')
            ->setLocales(['fr' => 'Français', 'en' => 'English', 'ar' => 'العربية'])
            ->setFaviconPath('data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 128 128%22><text y=%221.2em%22 font-size=%2296%22>🧥</text></svg>');
    }

    public function configureAssets(): Assets
    {
        return Assets::new()->addHtmlContentToHead(<<<'HTML'
            <style>
                :root { --color-primary: #5E0A28; --ea-primary-text: #5E0A28; --ea-primary-on-surface: #5E0A28; --link-color: #5E0A28; }
                .btn-primary { --bs-btn-bg: #5E0A28; --bs-btn-border-color: #5E0A28; --bs-btn-hover-bg: #9A5B2E; --bs-btn-hover-border-color: #9A5B2E; background-color: #5E0A28; border-color: #5E0A28; }
                .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr)); gap: 1rem; margin-bottom: 2rem; }
                .kpi { background: var(--body-bg, #fff); border: 1px solid var(--border-color, #e3e3e3); border-radius: .75rem; padding: 1.25rem; }
                .kpi-value { font-size: 2rem; font-weight: 700; color: #5E0A28; }
                .kpi-label { color: var(--text-muted, #6c757d); }
                .kpi-alert .kpi-value { color: #B3261E; }
            </style>
            HTML);
    }
}
