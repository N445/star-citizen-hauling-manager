<?php

declare(strict_types=1);

namespace App\Tests\Service\ScCargo;

use App\Service\ScCargo\ScCargoShipParser;
use PHPUnit\Framework\TestCase;

final class ScCargoShipParserTest extends TestCase
{
    private const string BUNDLE = <<<'JS'
        function decoy(){let N9="Leurre";return N9}
        const THREE=Object.freeze(Object.defineProperty({__proto__:null,Box3:Ab,Color:Cd},Symbol.toStringTag,{value:"Module"}));
        const L9="RSI",N9="Constellation Taurus",B9=["Connie"],q9={capacity:1e3,groups:[{name:"Aft Bay",x:0,z:0,minSize:1,grids:[{name:"Port",x:.5,y:0,z:0,width:1,height:2,length:3,maxSize:1,preferHorizontal:!0}]},{x:4,z:6,grids:[{x:0,y:0,z:0,width:1,height:1,length:1}]}],labels:[{value:"${scu}",x:1,z:-2,fontSize:5}]},Dte={manufacturer:L9,name:N9,official:q9},Pte=Object.freeze(Object.defineProperty({__proto__:null,default:Dte,manufacturer:L9,name:N9,official:q9,searchAliases:B9},Symbol.toStringTag,{value:"Module"}));
        JS;

    public function testReadsNameCapacityAndOfficialGridOfEachShipModule(): void
    {
        $ships = (new ScCargoShipParser())->parse(self::BUNDLE);

        self::assertCount(1, $ships);
        self::assertSame('rsi-constellation-taurus', $ships[0]->slug());
        self::assertSame('RSI Constellation Taurus', $ships[0]->name());
        self::assertSame(1000, $ships[0]->capacity());
        self::assertSame([
            ['name' => 'Aft Bay', 'x' => 0, 'z' => 0, 'minSize' => 1, 'grids' => [
                ['name' => 'Port', 'x' => 0.5, 'y' => 0, 'z' => 0, 'width' => 1, 'height' => 2, 'length' => 3, 'maxSize' => 1, 'preferHorizontal' => true],
            ]],
            ['x' => 4, 'z' => 6, 'grids' => [
                ['x' => 0, 'y' => 0, 'z' => 0, 'width' => 1, 'height' => 1, 'length' => 1],
            ]],
        ], $ships[0]->cargoGroups());
    }

    public function testFailsLoudlyWhenTheBundleHasNoShip(): void
    {
        $this->expectException(\RuntimeException::class);

        (new ScCargoShipParser())->parse('const a=1;');
    }
}
