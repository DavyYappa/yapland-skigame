<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261007142226 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE app_clients (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, token VARCHAR(16) NOT NULL, logo_filename VARCHAR(64) DEFAULT NULL, primary_color VARCHAR(7) NOT NULL, secondary_color VARCHAR(7) NOT NULL, accent_color VARCHAR(7) NOT NULL, message VARCHAR(140) NOT NULL, active TINYINT NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_ED95AE395F37A13B (token), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE app_scores (id INT AUTO_INCREMENT NOT NULL, player_name VARCHAR(60) NOT NULL, points INT NOT NULL, created_at DATETIME NOT NULL, client_id INT NOT NULL, INDEX IDX_E2535ADE19EB692127BA8E29 (client_id, points), INDEX IDX_E2535ADE19EB6921 (client_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE app_users (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_C2502824E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE app_scores ADD CONSTRAINT FK_E2535ADE19EB6921 FOREIGN KEY (client_id) REFERENCES app_clients (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE app_scores DROP FOREIGN KEY FK_E2535ADE19EB6921');
        $this->addSql('DROP TABLE app_clients');
        $this->addSql('DROP TABLE app_scores');
        $this->addSql('DROP TABLE app_users');
    }
}
