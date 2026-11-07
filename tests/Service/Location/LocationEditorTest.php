<?php

declare(strict_types=1);

namespace App\Tests\Service\Location;

use App\Catalog\LocationCatalog;
use App\Entity\Location;
use App\Service\Location\LocationException;
use App\Tests\Admin\LocationTestCase;

final class LocationEditorTest extends LocationTestCase
{
    public function testAddsLocationUnderParent(): void
    {
        $stanton = $this->system('Stanton');
        $hurston = $this->child($stanton, 'Hurston');

        self::assertSame($stanton, $hurston->parent());
        self::assertSame([$hurston], $stanton->children());
        self::assertSame('Stanton › Hurston', (string) $hurston);
    }

    public function testBatchAddTrimsIgnoresBlankLinesAndCreatesEveryName(): void
    {
        $stanton = $this->system('Stanton');

        $created = $this->editor->addMany($stanton, "  Hurston \r\n\n microTech\n   \nArcCorp");

        self::assertSame(['Hurston', 'microTech', 'ArcCorp'], $this->names($created));
        self::assertSame(['ArcCorp', 'Hurston', 'microTech'], $this->names($stanton->children()));
    }

    public function testBatchAddIsAllOrNothing(): void
    {
        $stanton = $this->system('Stanton');
        $this->child($stanton, 'Hurston');

        try {
            $this->editor->addMany($stanton, "ArcCorp\nhurston\nCrusader");
            self::fail('Duplicate expected');
        } catch (LocationException $exception) {
            self::assertSame('location.error.duplicate', $exception->getMessage());
        }

        $this->entityManager->clear();
        self::assertCount(2, $this->entityManager->getRepository(Location::class)->findAll());
    }

    public function testBatchAddRejectsDuplicateInsideTheBatch(): void
    {
        $this->expectException(LocationException::class);

        $this->editor->addMany(null, "Pyro\nPYRO");
    }

    public function testSystemNamesAreUniqueAmongSystemsToo(): void
    {
        $this->system('Stanton');

        $this->expectException(LocationException::class);
        $this->system('stanton');
    }

    public function testSameNameIsAllowedUnderDifferentParents(): void
    {
        $stanton = $this->system('Stanton');
        $pyro = $this->system('Pyro');

        $this->child($stanton, 'Station');
        $this->child($pyro, 'Station');

        self::assertCount(1, $pyro->children());
    }

    public function testEmptyAndTooLongNamesAreRejected(): void
    {
        foreach (["  \n ", str_repeat('x', 121)] as $input) {
            try {
                $this->editor->addMany(null, $input);
                self::fail('Rejection expected');
            } catch (LocationException) {
                self::assertCount(0, $this->entityManager->getRepository(Location::class)->findAll());
            }
        }
    }

    public function testRenameKeepsSiblingUniqueness(): void
    {
        $stanton = $this->system('Stanton');
        $hurston = $this->child($stanton, 'Hurston');
        $this->child($stanton, 'ArcCorp');

        $this->editor->rename($hurston, 'HURSTON');
        self::assertSame('HURSTON', $hurston->name());

        $this->expectException(LocationException::class);
        $this->editor->rename($hurston, 'arccorp');
    }

    public function testMoveChangesParentOnBothSides(): void
    {
        $stanton = $this->system('Stanton');
        $pyro = $this->system('Pyro');
        $moon = $this->child($stanton, 'Aberdeen');

        $this->editor->move($moon, $pyro);

        self::assertSame($pyro, $moon->parent());
        self::assertSame([], $stanton->children());
        self::assertSame([$moon], $pyro->children());
    }

    public function testMoveToNullMakesASystem(): void
    {
        $stanton = $this->system('Stanton');
        $hurston = $this->child($stanton, 'Hurston');

        $this->editor->move($hurston, null);

        self::assertTrue($hurston->isSystem());
    }

    public function testMoveIntoItselfOrDescendantIsRefused(): void
    {
        $stanton = $this->system('Stanton');
        $hurston = $this->child($stanton, 'Hurston');
        $aberdeen = $this->child($hurston, 'Aberdeen');

        foreach ([$stanton, $hurston, $aberdeen] as $target) {
            try {
                $this->editor->move($stanton, $target);
                self::fail('Cycle expected');
            } catch (LocationException $exception) {
                self::assertSame('location.error.cycle', $exception->getMessage());
            }
        }
        self::assertTrue($stanton->isSystem());
    }

    public function testMoveRefusesSiblingNameClash(): void
    {
        $stanton = $this->system('Stanton');
        $pyro = $this->system('Pyro');
        $this->child($pyro, 'Station');
        $station = $this->child($stanton, 'Station');

        try {
            $this->editor->move($station, $pyro);
            self::fail('Duplicate expected');
        } catch (LocationException $exception) {
            self::assertSame('location.error.duplicate', $exception->getMessage());
            self::assertSame($stanton, $station->parent());
        }
    }

    public function testDeleteRemovesDescendantsAndReturnsTheirCount(): void
    {
        $stanton = $this->system('Stanton');
        $hurston = $this->child($stanton, 'Hurston');
        $this->child($hurston, 'Aberdeen');
        $this->child($hurston, 'Arial');
        $this->system('Pyro');

        self::assertSame(3, $this->editor->delete($stanton));

        $this->entityManager->clear();
        self::assertSame(['Pyro'], $this->names($this->entityManager->getRepository(Location::class)->findAll()));
    }

    public function testDeletingALeafLeavesTheParentIntact(): void
    {
        $stanton = $this->system('Stanton');
        $hurston = $this->child($stanton, 'Hurston');

        self::assertSame(0, $this->editor->delete($hurston));
        self::assertSame([], $stanton->children());
    }

    public function testPublicSuggestionsFollowTheChanges(): void
    {
        $catalog = new LocationCatalog($this->entityManager->getRepository(Location::class));
        $stanton = $this->system('Stanton');
        $hurston = $this->child($stanton, 'Hurston');
        $lorville = $this->child($hurston, 'Lorville');

        self::assertSame(['Lorville'], array_column($catalog->suggestions(), 'value'));

        $this->editor->add($hurston, 'Everus Harbor');
        $catalog->reset();
        self::assertSame(['Everus Harbor', 'Lorville'], array_column($catalog->suggestions(), 'value'));

        $this->editor->delete($lorville);
        $catalog->reset();
        self::assertSame(['Everus Harbor'], array_column($catalog->suggestions(), 'value'));
    }

    /**
     * @param list<Location> $locations
     *
     * @return list<string>
     */
    private function names(array $locations): array
    {
        return array_map(static fn (Location $location): string => $location->name(), $locations);
    }
}
