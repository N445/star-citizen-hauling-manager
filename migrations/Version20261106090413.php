<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261106090413 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Point de départ de la tournée';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tour ADD start_location VARCHAR(120) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tour DROP start_location');
    }
}
