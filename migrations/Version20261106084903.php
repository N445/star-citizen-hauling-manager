<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261106084903 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ressources (commodities) et leur couleur d\'affichage';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE commodity (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(80) NOT NULL, color VARCHAR(7) NOT NULL, UNIQUE INDEX UNIQ_5E8D2F745E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql(<<<'SQL'
            INSERT INTO commodity (name, color) VALUES
            ('Agricium', '#16a085'),
            ('Agricultural Supplies', '#25b14e'),
            ('Aluminum', '#7725b1'),
            ('Aphorite', '#b19f25'),
            ('Astatine', '#2599b1'),
            ('Beryl', '#b12571'),
            ('Bexalite', '#48b125'),
            ('Borase', '#2b25b1'),
            ('Chlorine', '#b15425'),
            ('Construction Materials', '#25b17d'),
            ('Copper', '#b87333'),
            ('Corundum', '#94b125'),
            ('Diamond', '#1f9fbf'),
            ('Distilled Spirits', '#b12542'),
            ('Dolivine', '#25b131'),
            ('Fluorine', '#5a25b1'),
            ('Gold', '#b8860b'),
            ('Hadanite', '#25b1ab'),
            ('Hephaestanite', '#b1258e'),
            ('Hydrogen', '#2e86de'),
            ('Iodine', '#253cb1'),
            ('Janalite', '#b13725'),
            ('Laranite', '#e67e22'),
            ('Medical Supplies', '#d63031'),
            ('Pressurized Ice', '#48a9c5'),
            ('Processed Food', '#27ae60'),
            ('Quantanium', '#8e44ad'),
            ('Quartz', '#36b125'),
            ('Recycled Material Composite', '#3d25b1'),
            ('Revealer', '#b16625'),
            ('Savrilium', '#25b18e'),
            ('Scrap', '#8d6e63'),
            ('Ship Ammunition', '#82b125'),
            ('Stileron', '#2559b1'),
            ('Stims', '#b12530'),
            ('Taranite', '#25b143'),
            ('Titanium', '#6c25b1'),
            ('Tungsten', '#b19425'),
            ('Vehicle Components', '#25a4b1'),
            ('Waste', '#7a5c2e')
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE commodity');
    }
}
