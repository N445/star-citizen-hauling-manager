<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261106082838 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Vaisseaux (grille sc-cargo.space), tournées, missions et livraisons';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE delivery (id INT AUTO_INCREMENT NOT NULL, dropoff VARCHAR(120) NOT NULL, resource VARCHAR(80) NOT NULL, quantity INT NOT NULL, mission_id INT NOT NULL, INDEX IDX_3781EC10BE6CAE90 (mission_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE mission (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) DEFAULT NULL, reward INT NOT NULL, type VARCHAR(20) NOT NULL, pickups JSON NOT NULL, tour_id INT NOT NULL, INDEX IDX_9067F23C15ED8D43 (tour_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ship (id INT AUTO_INCREMENT NOT NULL, slug VARCHAR(120) NOT NULL, manufacturer VARCHAR(60) NOT NULL, name VARCHAR(80) NOT NULL, capacity INT NOT NULL, cargo_groups JSON NOT NULL, UNIQUE INDEX UNIQ_FA30EB24989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE tour (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, ship_name VARCHAR(120) NOT NULL, capacity INT NOT NULL, ship_id INT DEFAULT NULL, INDEX IDX_6AD1F969C256317D (ship_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE delivery ADD CONSTRAINT FK_3781EC10BE6CAE90 FOREIGN KEY (mission_id) REFERENCES mission (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE mission ADD CONSTRAINT FK_9067F23C15ED8D43 FOREIGN KEY (tour_id) REFERENCES tour (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tour ADD CONSTRAINT FK_6AD1F969C256317D FOREIGN KEY (ship_id) REFERENCES ship (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE delivery DROP FOREIGN KEY FK_3781EC10BE6CAE90');
        $this->addSql('ALTER TABLE mission DROP FOREIGN KEY FK_9067F23C15ED8D43');
        $this->addSql('ALTER TABLE tour DROP FOREIGN KEY FK_6AD1F969C256317D');
        $this->addSql('DROP TABLE delivery');
        $this->addSql('DROP TABLE mission');
        $this->addSql('DROP TABLE ship');
        $this->addSql('DROP TABLE tour');
    }
}
