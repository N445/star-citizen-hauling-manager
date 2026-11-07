<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261107163856 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Messages de la page de contact';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE contact_message (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(20) NOT NULL, resolved_at DATETIME DEFAULT NULL, guest_token VARCHAR(64) DEFAULT NULL, reply_email VARCHAR(180) DEFAULT NULL, category VARCHAR(20) NOT NULL, message LONGTEXT NOT NULL, locale VARCHAR(5) NOT NULL, created_at DATETIME NOT NULL, author_id INT DEFAULT NULL, INDEX IDX_2C9211FE7B00651C (status), INDEX IDX_2C9211FEF675F31B (author_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE contact_message ADD CONSTRAINT FK_2C9211FEF675F31B FOREIGN KEY (author_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contact_message DROP FOREIGN KEY FK_2C9211FEF675F31B');
        $this->addSql('DROP TABLE contact_message');
    }
}
