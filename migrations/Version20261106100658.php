<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261106100658 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Position du joueur abandonnée : le parcours est figé au premier arrêt coché';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tour DROP current_location');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tour ADD current_location VARCHAR(120) DEFAULT NULL');
    }
}
