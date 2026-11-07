<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261107110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Signalements d\'erreur sur une route, avec instantané de la tournée';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE route_report (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(20) NOT NULL, resolved_at DATETIME DEFAULT NULL, guest_token VARCHAR(64) DEFAULT NULL, category VARCHAR(20) NOT NULL, message LONGTEXT NOT NULL, snapshot JSON NOT NULL, created_at DATETIME NOT NULL, tour_id INT DEFAULT NULL, author_id INT DEFAULT NULL, INDEX IDX_F5DC86597B00651C (status), INDEX IDX_F5DC865915ED8D43 (tour_id), INDEX IDX_F5DC8659F675F31B (author_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE route_report ADD CONSTRAINT FK_F5DC865915ED8D43 FOREIGN KEY (tour_id) REFERENCES tour (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE route_report ADD CONSTRAINT FK_F5DC8659F675F31B FOREIGN KEY (author_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE route_report');
    }
}
