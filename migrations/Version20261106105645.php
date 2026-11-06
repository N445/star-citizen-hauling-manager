<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261106105645 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tournée unique des visiteurs sans compte, retrouvée par le jeton de leur navigateur';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tour ADD guest_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6AD1F9694AC9362F ON tour (guest_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_6AD1F9694AC9362F ON tour');
        $this->addSql('ALTER TABLE tour DROP guest_token');
    }
}
