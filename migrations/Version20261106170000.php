<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261106170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Schéma complet, ressources et lieux semés (fusion des anciennes migrations)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE commodity (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(80) NOT NULL, color VARCHAR(7) NOT NULL, UNIQUE INDEX UNIQ_5E8D2F745E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE delivery (id INT AUTO_INCREMENT NOT NULL, delivered TINYINT DEFAULT 0 NOT NULL, dropoff VARCHAR(120) NOT NULL, resource VARCHAR(80) NOT NULL, quantity INT NOT NULL, mission_id INT NOT NULL, INDEX IDX_3781EC10BE6CAE90 (mission_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE location (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, parent_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_5E9E89CB727ACA705E237E06 (parent_id, name), INDEX IDX_5E9E89CB727ACA70 (parent_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE mission (id INT AUTO_INCREMENT NOT NULL, picked_up_at JSON NOT NULL, started_at DATETIME DEFAULT NULL, finished_at DATETIME DEFAULT NULL, cargo_placement JSON DEFAULT NULL, name VARCHAR(120) DEFAULT NULL, reward INT NOT NULL, type VARCHAR(20) NOT NULL, pickups JSON NOT NULL, tour_id INT NOT NULL, INDEX IDX_9067F23C15ED8D43 (tour_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ship (id INT AUTO_INCREMENT NOT NULL, slug VARCHAR(120) NOT NULL, door_sides JSON NOT NULL, manufacturer VARCHAR(60) NOT NULL, name VARCHAR(80) NOT NULL, capacity INT NOT NULL, cargo_groups JSON NOT NULL, UNIQUE INDEX UNIQ_FA30EB24989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE tour (id INT AUTO_INCREMENT NOT NULL, stop_order JSON DEFAULT NULL, guest_token VARCHAR(64) DEFAULT NULL, name VARCHAR(120) NOT NULL, ship_name VARCHAR(120) NOT NULL, capacity INT NOT NULL, start_location VARCHAR(120) DEFAULT NULL, owner_id INT DEFAULT NULL, ship_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_6AD1F9694AC9362F (guest_token), INDEX IDX_6AD1F9697E3C61F9 (owner_id), INDEX IDX_6AD1F969C256317D (ship_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, registered_at DATETIME NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, email VARCHAR(180) NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE delivery ADD CONSTRAINT FK_3781EC10BE6CAE90 FOREIGN KEY (mission_id) REFERENCES mission (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE location ADD CONSTRAINT FK_5E9E89CB727ACA70 FOREIGN KEY (parent_id) REFERENCES location (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE mission ADD CONSTRAINT FK_9067F23C15ED8D43 FOREIGN KEY (tour_id) REFERENCES tour (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tour ADD CONSTRAINT FK_6AD1F9697E3C61F9 FOREIGN KEY (owner_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE tour ADD CONSTRAINT FK_6AD1F969C256317D FOREIGN KEY (ship_id) REFERENCES ship (id) ON DELETE SET NULL');
        $this->addSql(<<<'SQL'
            INSERT INTO commodity (name, color) VALUES
            ('Agricium', '#16a085'),
            ('Agricultural Supplies', '#25b14e'),
            ('Aluminum', '#7725b1'),
            ('Aphorite', '#b19f25'),
            ('Astatine', '#2599b1'),
            ('Beryl', '#b12571'),
            ('Bexalite', '#48b125'),
            ('Borase', '#2b25b1'),
            ('Chlorine', '#b15425'),
            ('Construction Materials', '#25b17d'),
            ('Copper', '#b87333'),
            ('Corundum', '#94b125'),
            ('Diamond', '#1f9fbf'),
            ('Distilled Spirits', '#b12542'),
            ('Dolivine', '#25b131'),
            ('Fluorine', '#5a25b1'),
            ('Gold', '#b8860b'),
            ('Hadanite', '#25b1ab'),
            ('Hephaestanite', '#b1258e'),
            ('Hydrogen', '#2e86de'),
            ('Iodine', '#253cb1'),
            ('Janalite', '#b13725'),
            ('Laranite', '#e67e22'),
            ('Medical Supplies', '#d63031'),
            ('Pressurized Ice', '#48a9c5'),
            ('Processed Food', '#27ae60'),
            ('Quantanium', '#8e44ad'),
            ('Quartz', '#36b125'),
            ('Recycled Material Composite', '#3d25b1'),
            ('Revealer', '#b16625'),
            ('Savrilium', '#25b18e'),
            ('Scrap', '#8d6e63'),
            ('Ship Ammunition', '#82b125'),
            ('Stileron', '#2559b1'),
            ('Stims', '#b12530'),
            ('Taranite', '#25b143'),
            ('Titanium', '#6c25b1'),
            ('Tungsten', '#b19425'),
            ('Vehicle Components', '#25a4b1'),
            ('Waste', '#7a5c2e')
            SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO location (id, name, parent_id) VALUES
                (1, 'Stanton', NULL),
                (2, 'Hurston', 1),
                (3, 'Lorville', 2),
                (4, 'Everus Harbor', 2),
                (5, 'HUR-L1 Green Glade Station', 2),
                (6, 'HUR-L2 Faithful Dream Station', 2),
                (7, 'HUR-L3 Thundering Express Station', 2),
                (8, 'HUR-L4 Melodic Fields Station', 2),
                (9, 'HUR-L5 High Course Station', 2),
                (10, 'Arial', 2),
                (11, 'HDMS Bezdek', 10),
                (12, 'Aberdeen', 2),
                (13, 'HDMS Anderson', 12),
                (14, 'Magda', 2),
                (15, 'HDMS Hahn', 14),
                (16, 'Crusader', 1),
                (17, 'Orison', 16),
                (18, 'Seraphim Station', 16),
                (19, 'CRU-L1 Ambitious Dream Station', 16),
                (20, 'CRU-L4 Shallow Fields Station', 16),
                (21, 'CRU-L5 Beautiful Glen Station', 16),
                (22, 'Cellin', 16),
                (23, 'Gallete Family Farms', 22),
                (24, 'Hickes Research Outpost', 22),
                (25, 'Daymar', 16),
                (26, 'Kudre Ore', 25),
                (27, 'Bountiful Harvest Hydroponics', 25),
                (28, 'ArcCorp Mining Area 141', 25),
                (29, 'Yela', 16),
                (30, 'Benson Mining Outpost', 29),
                (31, 'Deakins Research Outpost', 29),
                (32, 'ArcCorp', 1),
                (33, 'Area 18', 32),
                (34, 'Baijini Point', 32),
                (35, 'ARC-L1 Wide Forest Station', 32),
                (36, 'ARC-L2 Lively Pathway Station', 32),
                (37, 'ARC-L3 Modern Express Station', 32),
                (38, 'ARC-L4 Faint Glen Station', 32),
                (39, 'ARC-L5 Yellow Core Station', 32),
                (40, 'Lyria', 32),
                (41, 'Humboldt Mines', 40),
                (42, 'Shubin Mining SAL-2', 40),
                (43, 'Shubin Mining SAL-5', 40),
                (44, 'microTech', 1),
                (45, 'New Babbage', 44),
                (46, 'Port Tressler', 44),
                (47, 'MIC-L1 Shallow Frontier Station', 44),
                (48, 'MIC-L2 Long Forest Station', 44),
                (49, 'MIC-L3 Endless Odyssey Station', 44),
                (50, 'MIC-L4 Red Crossroads Station', 44),
                (51, 'MIC-L5 Modern Icarus Station', 44),
                (52, 'Rayari Deltana Research Outpost', 44),
                (53, 'Calliope', 44),
                (54, 'Rayari Anvik Research Outpost', 53),
                (55, 'Euterpe', 44),
                (56, 'Devlin Scrapyard', 55),
                (57, 'Gateways', 1),
                (58, 'Pyro Gateway (Stanton)', 57),
                (59, 'Nyx Gateway (Stanton)', 57),
                (60, 'Pyro', NULL),
                (61, 'Pyro I', 60),
                (62, 'Gray Gardens Depot', 61),
                (63, 'Rustville', 61),
                (64, 'Stag''s Rut', 61),
                (65, 'Monox', 60),
                (66, 'Checkmate', 65),
                (67, 'Arid Reach', 65),
                (68, 'Jackson''s Swap', 65),
                (69, 'Last Ditch', 65),
                (70, 'Ostler''s Claim', 65),
                (71, 'Slowburn Depot', 65),
                (72, 'Sunset Mesa', 65),
                (73, 'Yang''s Place', 65),
                (74, 'Bloom', 60),
                (75, 'Orbituary', 74),
                (76, 'Starlight Service Station', 74),
                (77, 'Patch City', 74),
                (78, 'Shepherd''s Rest', 74),
                (79, 'The Yard', 74),
                (80, 'Narena''s Rest', 74),
                (81, 'Shadowfall', 74),
                (82, 'Bueno Ravine', 74),
                (83, 'Carver''s Ridge', 74),
                (84, 'Windfall', 74),
                (85, 'Prospect Depot', 74),
                (86, 'Frigid Knot', 74),
                (87, 'The Golden Riviera', 74),
                (88, 'Pyro IV', 60),
                (89, 'Chawla''s Beach', 88),
                (90, 'Dinger''s Depot', 88),
                (91, 'Fallow Field', 88),
                (92, 'Goner''s Deal', 88),
                (93, 'Sacren''s Plot', 88),
                (94, 'Pyro V', 60),
                (95, 'Gaslight', 94),
                (96, 'Rod''s Fuel ''N Supplies', 94),
                (97, 'Rat''s Nest', 94),
                (98, 'Ignis', 94),
                (99, 'Ashland', 98),
                (100, 'Kabir''s Post', 98),
                (101, 'Terminus', 60),
                (102, 'Ruin Station', 101),
                (103, 'Endgame', 101),
                (104, 'Dudley & Daughters', 101),
                (105, 'Megumi Refueling', 101),
                (106, 'Blackrock Exchange', 101),
                (107, 'Bullock''s Reach', 101),
                (108, 'Canard View', 101),
                (109, 'Kinder Plots', 101),
                (110, 'Last Landings', 101),
                (111, 'Rough Landing', 101),
                (112, 'Scarper''s Turn', 101),
                (113, 'Stonetree', 101),
                (114, 'Supply Gap', 101),
                (115, 'Watcher''s Depot', 101),
                (116, 'Gateways', 60),
                (117, 'Stanton Gateway (Pyro)', 116),
                (118, 'Nyx Gateway (Pyro)', 116),
                (119, 'Nyx', NULL),
                (120, 'Glaciem Ring', 119),
                (121, 'Delamar', 120),
                (122, 'Levski', 121),
                (123, 'Keeger Belt', 119),
                (124, 'People''s Service Station Alpha', 123),
                (125, 'People''s Service Station Delta', 123),
                (126, 'People''s Service Station Lambda', 123),
                (127, 'People''s Service Station Theta', 123),
                (128, 'Gateways', 119),
                (129, 'Stanton Gateway (Nyx)', 128),
                (130, 'Pyro Gateway (Nyx)', 128)
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE delivery DROP FOREIGN KEY FK_3781EC10BE6CAE90');
        $this->addSql('ALTER TABLE location DROP FOREIGN KEY FK_5E9E89CB727ACA70');
        $this->addSql('ALTER TABLE mission DROP FOREIGN KEY FK_9067F23C15ED8D43');
        $this->addSql('ALTER TABLE tour DROP FOREIGN KEY FK_6AD1F9697E3C61F9');
        $this->addSql('ALTER TABLE tour DROP FOREIGN KEY FK_6AD1F969C256317D');
        foreach (['commodity', 'delivery', 'location', 'mission', 'ship', 'tour', '`user`'] as $table) {
            $this->addSql('DROP TABLE '.$table);
        }
    }
}
