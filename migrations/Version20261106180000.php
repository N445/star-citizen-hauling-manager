<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261106180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Porte principale du vaisseau et portes choisies par tournée';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ship ADD main_door VARCHAR(10) DEFAULT NULL');
        $this->addSql("UPDATE ship SET main_door = COALESCE(JSON_UNQUOTE(JSON_EXTRACT(door_sides, '$[0]')), 'rear')");
        $this->addSql('ALTER TABLE ship MODIFY main_door VARCHAR(10) NOT NULL');
        $this->addSql('ALTER TABLE tour ADD door_sides JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ship DROP main_door');
        $this->addSql('ALTER TABLE tour DROP door_sides');
    }
}
