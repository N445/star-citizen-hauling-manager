<?php

declare(strict_types=1);

namespace App\Service\ScCargo;

use App\Entity\Ship;

/**
 * sc-cargo.space n'a pas d'API : chaque vaisseau est un module ES minifié du bundle,
 * dont on lit les littéraux comme des données, sans jamais exécuter le JS.
 */
final class ScCargoShipParser
{
    private const string MODULE_EXPORTS = '/Object\.freeze\(Object\.defineProperty\(\{__proto__:null,((?:\w+:[\w$]+,?)+)\}/';
    private const string LITERAL = '/\G("(?:[^"\\\\]|\\\\.)*"|\{(?:[^{}\[\]"]++|"(?:[^"\\\\]|\\\\.)*"|(?1))*\}|\[(?:[^{}\[\]"]++|"(?:[^"\\\\]|\\\\.)*"|(?1))*\])/';

    /**
     * @return list<Ship>
     */
    public function parse(string $bundle): array
    {
        preg_match_all(self::MODULE_EXPORTS, $bundle, $modules, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        $shipModules = array_filter($modules, static fn (array $module): bool => str_contains($module[1][0], 'official:'));

        if ([] === $shipModules) {
            throw new \RuntimeException('Aucun vaisseau trouvé dans le bundle de sc-cargo.space : son format a changé.');
        }

        return array_values(array_map(fn (array $module): Ship => $this->parseModule($bundle, $module[1][0], $module[0][1]), $shipModules));
    }

    private function parseModule(string $bundle, string $exports, int $moduleOffset): Ship
    {
        $identifiers = $this->exportedIdentifiers($exports);
        $official = $this->resolve($bundle, $identifiers['official'], $moduleOffset);

        return new Ship(
            $this->resolve($bundle, $identifiers['manufacturer'], $moduleOffset),
            $this->resolve($bundle, $identifiers['name'], $moduleOffset),
            (int) $official['capacity'],
            $official['groups'],
        );
    }

    /**
     * @return array<string, string>
     */
    private function exportedIdentifiers(string $exports): array
    {
        preg_match_all('/(\w+):([\w$]+)/', $exports, $pairs);

        return array_combine($pairs[1], $pairs[2]);
    }

    private function resolve(string $bundle, string $identifier, int $moduleOffset): mixed
    {
        $literalStart = $this->lastDeclarationBefore($bundle, $identifier, $moduleOffset);

        return $this->decode($this->literalAt($bundle, $literalStart));
    }

    private function lastDeclarationBefore(string $bundle, string $identifier, int $moduleOffset): int
    {
        $pattern = '/[,;{\s]'.preg_quote($identifier, '/').'=(?!=)/';
        preg_match_all($pattern, substr($bundle, 0, $moduleOffset), $declarations, PREG_OFFSET_CAPTURE);
        $last = end($declarations[0]);

        if (false === $last) {
            throw new \RuntimeException(sprintf('Déclaration de « %s » introuvable dans le bundle de sc-cargo.space.', $identifier));
        }

        return $last[1] + \strlen($last[0]);
    }

    private function literalAt(string $bundle, int $offset): string
    {
        if (!preg_match(self::LITERAL, $bundle, $match, 0, $offset)) {
            throw new \RuntimeException(sprintf('Littéral illisible à l\'offset %d du bundle de sc-cargo.space.', $offset));
        }

        return $match[1];
    }

    private function decode(string $literal): mixed
    {
        $json = preg_replace(
            ['/([{,])([A-Za-z_$][\w$]*):/', '/:!0\b/', '/:!1\b/', '/([:,\[])(-?)\.(\d)/'],
            ['$1"$2":', ':true', ':false', '${1}${2}0.$3'],
            $literal,
        );

        return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    }
}
