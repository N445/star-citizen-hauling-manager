<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261106091145 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Avancement de la tournée : livraisons faites et lieux de ramassage visités';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE delivery ADD delivered TINYINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE mission ADD picked_up_at JSON DEFAULT NULL');
        $this->addSql("UPDATE mission SET picked_up_at = '[]'");
        $this->addSql('ALTER TABLE mission MODIFY picked_up_at JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE delivery DROP delivered');
        $this->addSql('ALTER TABLE mission DROP picked_up_at');
    }
}
