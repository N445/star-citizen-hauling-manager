<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261106102235 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Portes cargo portées par le vaisseau (plusieurs possibles) ; le Caterpillar s\'ouvre sur les côtés';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ship ADD door_sides JSON DEFAULT NULL');
        $this->addSql('UPDATE ship SET door_sides = \'["rear"]\'');
        $this->addSql('UPDATE ship SET door_sides = \'["left","right"]\' WHERE slug = \'drake-caterpillar\'');
        $this->addSql('ALTER TABLE ship MODIFY door_sides JSON NOT NULL');
        $this->addSql('ALTER TABLE tour DROP door_side');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ship DROP door_sides');
        $this->addSql('ALTER TABLE tour ADD door_side VARCHAR(10) DEFAULT \'rear\' NOT NULL');
    }
}
