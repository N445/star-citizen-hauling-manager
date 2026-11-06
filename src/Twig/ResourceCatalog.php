<?php

declare(strict_types=1);

namespace App\Twig;

final class ResourceCatalog
{
    /** @return list<string> */
    public static function all(): array
    {
        $names = [
            'Agricium', 'Agricultural Supplies', 'Aluminum', 'Aphorite', 'Astatine', 'Beryl', 'Bexalite', 'Borase',
            'Chlorine', 'Construction Materials', 'Copper', 'Corundum', 'Diamond', 'Distilled Spirits', 'Dolivine',
            'Fluorine', 'Gold', 'Hadanite', 'Hephaestanite', 'Hydrogen', 'Iodine', 'Janalite', 'Laranite',
            'Medical Supplies', 'Pressurized Ice', 'Processed Food', 'Quantanium', 'Quartz',
            'Recycled Material Composite', 'Revealer', 'Savrilium', 'Scrap', 'Ship Ammunition', 'Stileron', 'Stims',
            'Taranite', 'Titanium', 'Tungsten', 'Vehicle Components', 'Waste',
        ];
        sort($names);

        return $names;
    }
}
