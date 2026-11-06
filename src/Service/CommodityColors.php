<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Commodity;
use App\Repository\CommodityRepository;
use Symfony\Contracts\Service\ResetInterface;

final class CommodityColors implements ResetInterface
{
    public const string UNKNOWN = '#6c757d';

    /** @var array<string, string>|null */
    private ?array $colorsByName = null;

    public function __construct(private readonly CommodityRepository $commodityRepository)
    {
    }

    public function of(string $commodityName): string
    {
        return $this->colorsByName()[$commodityName] ?? self::UNKNOWN;
    }

    public function reset(): void
    {
        $this->colorsByName = null;
    }

    /**
     * @return array<string, string>
     */
    private function colorsByName(): array
    {
        return $this->colorsByName ??= array_column(
            array_map(static fn (Commodity $commodity): array => [$commodity->name(), $commodity->color()], $this->commodityRepository->findAll()),
            1,
            0,
        );
    }
}
