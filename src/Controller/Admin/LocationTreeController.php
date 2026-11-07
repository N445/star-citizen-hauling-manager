<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Location;
use App\Entity\User;
use App\Repository\LocationRepository;
use App\Service\Location\LocationEditor;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Explorateur des lieux : l'arbre des systèmes, et une page par lieu (fil d'Ariane, arbre des descendants, ajout, renommage,
 * déplacement, suppression). Toutes les écritures sont des POST protégés par un jeton CSRF.
 */
#[IsGranted(User::ADMIN_ROLE)]
#[AdminRoute('/locations', name: 'locations')]
final class LocationTreeController extends AbstractController
{
    public const string TOKEN = 'admin_location';

    private const string DRAFT = 'location_draft';

    public function __construct(
        private readonly LocationRepository $locations,
        private readonly LocationEditor $editor,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[AdminRoute('/', name: 'index')]
    public function index(Request $request): Response
    {
        $all = $this->locations->findAllSorted();

        return $this->render('admin/location/index.html.twig', [
            'systems' => array_values(array_filter($all, static fn (Location $location): bool => $location->isSystem())),
            'total' => \count($all),
            'draft' => $this->draft($request),
        ]);
    }

    #[AdminRoute('/{id}', name: 'show', options: ['requirements' => ['id' => '\d+']])]
    public function show(Request $request, #[MapEntity(id: 'id')] Location $location): Response
    {
        return $this->render('admin/location/show.html.twig', [
            'location' => $location,
            'path' => $location->path(),
            'descendantCount' => \count($location->descendants()),
            'parents' => $this->parentChoices($location),
            'draft' => $this->draft($request),
        ]);
    }

    #[AdminRoute('/add', name: 'add', options: ['methods' => ['POST']])]
    public function add(Request $request): Response
    {
        $parent = $this->parameterLocation($request, 'parent');
        $names = (string) $request->request->get('names', '');

        return $this->write($request, function () use ($parent, $names): string {
            $created = $this->editor->addMany($parent, $names);

            return $this->translator->trans('location.flash.added', ['count' => \count($created)], 'admin');
        }, $parent, $parent, $names);
    }

    #[AdminRoute('/{id}/rename', name: 'rename', options: ['requirements' => ['id' => '\d+'], 'methods' => ['POST']])]
    public function rename(Request $request, #[MapEntity(id: 'id')] Location $location): Response
    {
        $name = (string) $request->request->get('name', '');

        return $this->write($request, function () use ($location, $name): string {
            $this->editor->rename($location, $name);

            return $this->translator->trans('location.flash.renamed', ['name' => self::escape($location->name())], 'admin');
        }, $location, $location);
    }

    #[AdminRoute('/{id}/move', name: 'move', options: ['requirements' => ['id' => '\d+'], 'methods' => ['POST']])]
    public function move(Request $request, #[MapEntity(id: 'id')] Location $location): Response
    {
        $parent = $this->parameterLocation($request, 'parent');

        return $this->write($request, function () use ($location, $parent): string {
            $this->editor->move($location, $parent);

            return $this->translator->trans('location.flash.moved', ['name' => self::escape($location->name()), 'path' => self::escape((string) $location)], 'admin');
        }, $location, $location);
    }

    #[AdminRoute('/{id}/delete', name: 'delete', options: ['requirements' => ['id' => '\d+'], 'methods' => ['POST']])]
    public function delete(Request $request, #[MapEntity(id: 'id')] Location $location): Response
    {
        $parent = $location->parent();
        $name = $location->name();

        return $this->write($request, function () use ($location, $name): string {
            $count = $this->editor->delete($location);

            return $this->translator->trans('location.flash.deleted', ['name' => self::escape($name), 'count' => $count], 'admin');
        }, $parent, $location);
    }

    /**
     * Vérifie le jeton, exécute l'écriture et affiche son message (succès ou erreur du domaine), puis redirige vers la page
     * du lieu `$onSuccess` (null = page racine) ou, en cas d'erreur, `$onFailure`.
     *
     * @param callable(): string $action
     */
    private function write(Request $request, callable $action, ?Location $onSuccess, ?Location $onFailure, ?string $draft = null): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(self::TOKEN, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        try {
            $this->addFlash('success', $action());
            $target = $onSuccess;
        } catch (\InvalidArgumentException|\DomainException $exception) {
            $this->addFlash('danger', $exception instanceof TranslatableInterface ? $exception->trans($this->translator) : self::escape($exception->getMessage()));
            if (null !== $draft) {
                $request->getSession()->set(self::DRAFT, $draft);
            }
            $target = $onFailure;
        }

        return null === $target
            ? $this->redirectToRoute('admin_locations_index')
            : $this->redirectToRoute('admin_locations_show', ['id' => $target->id()]);
    }

    // Les flashs d'EasyAdmin sont rendus en `|raw` : tout ce qui vient d'un nom de lieu est échappé avant.
    private static function escape(string $text): string
    {
        return htmlspecialchars($text);
    }

    private function parameterLocation(Request $request, string $key): ?Location
    {
        $id = (int) $request->request->get($key, 0);

        return $id > 0 ? $this->locations->find($id) : null;
    }

    private function draft(Request $request): string
    {
        return (string) $request->getSession()->remove(self::DRAFT);
    }

    /**
     * Nouveaux parents possibles : tout sauf le lieu et ses descendants.
     *
     * @return list<array{id: int, label: string}>
     */
    private function parentChoices(Location $location): array
    {
        $choices = [];
        foreach ($this->locations->findAllSorted() as $candidate) {
            if (!$candidate->isSelfOrDescendantOf($location)) {
                $choices[] = ['id' => (int) $candidate->id(), 'label' => (string) $candidate];
            }
        }
        usort($choices, static fn (array $a, array $b): int => strnatcasecmp($a['label'], $b['label']));

        return $choices;
    }
}
