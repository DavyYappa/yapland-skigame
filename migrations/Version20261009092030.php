<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261009092030 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE app_invitations (id INT AUTO_INCREMENT NOT NULL, company VARCHAR(120) NOT NULL, email VARCHAR(180) NOT NULL, token VARCHAR(24) NOT NULL, status VARCHAR(16) NOT NULL, created_at DATETIME NOT NULL, sent_at DATETIME DEFAULT NULL, claimed_at DATETIME DEFAULT NULL, client_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_7AC23612E7927C74 (email), UNIQUE INDEX UNIQ_7AC236125F37A13B (token), UNIQUE INDEX UNIQ_7AC2361219EB6921 (client_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE app_mailing (id INT AUTO_INCREMENT NOT NULL, subject VARCHAR(150) NOT NULL, body LONGTEXT NOT NULL, button_label VARCHAR(60) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE app_invitations ADD CONSTRAINT FK_7AC2361219EB6921 FOREIGN KEY (client_id) REFERENCES app_clients (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE app_invitations DROP FOREIGN KEY FK_7AC2361219EB6921');
        $this->addSql('DROP TABLE app_invitations');
        $this->addSql('DROP TABLE app_mailing');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
