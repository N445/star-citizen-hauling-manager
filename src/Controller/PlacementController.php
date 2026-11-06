<?php

declare(strict_types=1);

namespace App\Controller;

use App\Twig\Components\Plan\PlanFactory;
use App\Twig\Components\Plan\PlannerState;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** Read-only helpers for the drag and drop of crates: the client fetches the valid positions once per drag. */
final class PlacementController extends AbstractController
{
    public function __construct(private readonly PlanFactory $factory)
    {
    }

    /** Valid origins of one crate, grouped by orientation to keep the payload small. */
    #[Route('/placement/positions', name: 'placement_positions', methods: ['POST'])]
    public function positions(Request $request): JsonResponse
    {
        ['placement' => $placement, 'crate' => $crateId] = $this->load($request);

        $orientations = [];
        foreach ($placement->validPositions($crateId) as $position) {
            $o = $position['orientation'];
            if (!isset($orientations[$o])) {
                $footprint = $placement->footprint($crateId, $o);
                $orientations[$o] = ['o' => $o, 'w' => $footprint['w'], 'l' => $footprint['l'], 'h' => $footprint['h'], 'cells' => []];
            }
            $orientations[$o]['cells'][] = [$position['x'], $position['y'], $position['z']];
        }

        $crate = $placement->forCrate($crateId);

        return new JsonResponse([
            'crate' => $crateId,
            'current' => ['x' => $crate->x, 'y' => $crate->y, 'z' => $crate->z, 'o' => $crate->orientation],
            'count' => \count($crate->crate->orientations()),
            'footprints' => array_map(
                static fn (int $o): array => $placement->footprint($crateId, $o),
                array_keys($crate->crate->orientations()),
            ),
            'orientations' => array_values($orientations),
        ]);
    }

    /** Refusal message (French) of one candidate position, null when it is valid. */
    #[Route('/placement/check', name: 'placement_check', methods: ['POST'])]
    public function check(Request $request): JsonResponse
    {
        ['placement' => $placement, 'crate' => $crateId, 'body' => $body] = $this->load($request);

        return new JsonResponse(['reason' => $placement->canPlace(
            $crateId,
            (int) ($body['x'] ?? 0),
            (int) ($body['y'] ?? 0),
            (int) ($body['z'] ?? 0),
            (int) ($body['o'] ?? 0),
        )]);
    }

    /** @return array{placement: \App\Domain\Placement, crate: string, body: array<string, mixed>} */
    private function load(Request $request): array
    {
        $body = json_decode($request->getContent(), true);
        if (!\is_array($body) || !\is_string($body['crate'] ?? null)) {
            throw $this->createNotFoundException();
        }

        try {
            $plan = $this->factory->build(PlannerState::normalizePlanInput($body['input'] ?? null));
            $plan->placement->forCrate($body['crate']);
        } catch (\InvalidArgumentException|\DomainException) {
            throw $this->createNotFoundException();
        }

        return ['placement' => $plan->placement, 'crate' => $body['crate'], 'body' => $body];
    }
}
