<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261106094514 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Côté de la porte cargo de la tournée';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tour ADD door_side VARCHAR(10) DEFAULT \'rear\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tour DROP door_side');
    }
}
