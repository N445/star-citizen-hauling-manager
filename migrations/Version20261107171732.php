<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261107171732 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Messages de contact gardés (anonymes) à la suppression du compte';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contact_message DROP FOREIGN KEY `FK_2C9211FEF675F31B`');
        $this->addSql('ALTER TABLE contact_message ADD CONSTRAINT FK_2C9211FEF675F31B FOREIGN KEY (author_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contact_message DROP FOREIGN KEY FK_2C9211FEF675F31B');
        $this->addSql('ALTER TABLE contact_message ADD CONSTRAINT `FK_2C9211FEF675F31B` FOREIGN KEY (author_id) REFERENCES user (id) ON DELETE CASCADE');
    }
}
