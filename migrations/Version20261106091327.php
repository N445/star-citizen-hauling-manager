<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261106091327 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ordre des arrêts posé à la main';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tour ADD stop_order JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tour DROP stop_order');
    }
}
