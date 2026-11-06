<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261106091741 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Position actuelle du joueur dans la tournée';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tour ADD current_location VARCHAR(120) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tour DROP current_location');
    }
}
