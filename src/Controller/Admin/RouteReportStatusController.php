<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\RouteReport;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Marque un signalement résolu ou le rouvre : deux POST protégés par un jeton CSRF.
 */
#[IsGranted(User::ADMIN_ROLE)]
#[AdminRoute('/reports', name: 'reports')]
final class RouteReportStatusController extends AbstractController
{
    public const string TOKEN = 'admin_report_status';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[AdminRoute('/{id}/resolve', name: 'resolve', options: ['requirements' => ['id' => '\d+'], 'methods' => ['POST']])]
    public function resolve(Request $request, #[MapEntity(id: 'id')] RouteReport $report): RedirectResponse
    {
        $this->assertToken($request);
        $report->resolve($this->clock->now());

        return $this->saved($report, 'report.flash.resolved');
    }

    #[AdminRoute('/{id}/reopen', name: 'reopen', options: ['requirements' => ['id' => '\d+'], 'methods' => ['POST']])]
    public function reopen(Request $request, #[MapEntity(id: 'id')] RouteReport $report): RedirectResponse
    {
        $this->assertToken($request);
        $report->reopen();

        return $this->saved($report, 'report.flash.reopened');
    }

    private function assertToken(Request $request): void
    {
        if (!$this->isCsrfTokenValid(self::TOKEN, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
    }

    private function saved(RouteReport $report, string $flash): RedirectResponse
    {
        $this->entityManager->flush();
        $this->addFlash('success', $this->translator->trans($flash, [], 'admin'));

        return $this->redirectToRoute('admin_route_report_detail', ['entityId' => $report->id()]);
    }
}
