<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261106095231 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Emplacements des caisses figés au ramassage';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mission ADD cargo_placement JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mission DROP cargo_placement');
    }
}
