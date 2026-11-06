<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261106104400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Chronomètre caché des missions : début et fin';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mission ADD started_at DATETIME DEFAULT NULL, ADD finished_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mission DROP started_at, DROP finished_at');
    }
}
