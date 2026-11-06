<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261106104653 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Comptes utilisateurs ; chaque tournée reçoit un propriétaire (null pour les tournées existantes, à réclamer avec app:tours:claim)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, registered_at DATETIME NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, email VARCHAR(180) NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE tour ADD owner_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE tour ADD CONSTRAINT FK_6AD1F9697E3C61F9 FOREIGN KEY (owner_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_6AD1F9697E3C61F9 ON tour (owner_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tour DROP FOREIGN KEY FK_6AD1F9697E3C61F9');
        $this->addSql('DROP INDEX IDX_6AD1F9697E3C61F9 ON tour');
        $this->addSql('ALTER TABLE tour DROP owner_id');
        $this->addSql('DROP TABLE `user`');
    }
}
