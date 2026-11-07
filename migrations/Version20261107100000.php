<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261107100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Quantités réelles saisies en route (livraisons et ramassages)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE delivery ADD actual_quantity INT DEFAULT NULL');
        $this->addSql('ALTER TABLE mission ADD pickup_quantities JSON DEFAULT NULL');
        $this->addSql("UPDATE mission SET pickup_quantities = '[]'");
        $this->addSql('ALTER TABLE mission MODIFY pickup_quantities JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE delivery DROP actual_quantity');
        $this->addSql('ALTER TABLE mission DROP pickup_quantities');
    }
}
