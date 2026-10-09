<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261009094923 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE app_invitations ADD unsubscribed_at DATETIME DEFAULT NULL');
        // Opt-outs from before this column existed keep showing in the admin
        $this->addSql("UPDATE app_invitations SET unsubscribed_at = NOW() WHERE status = 'unsubscribed'");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE app_invitations DROP unsubscribed_at');
    }
}
