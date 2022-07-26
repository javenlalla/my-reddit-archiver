<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220726132318 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE comment CHANGE parent_post_id parent_post_id INT NOT NULL');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C39C1776A FOREIGN KEY (parent_post_id) REFERENCES post (id)');
        $this->addSql('CREATE INDEX IDX_9474526C39C1776A ON comment (parent_post_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C39C1776A');
        $this->addSql('DROP INDEX IDX_9474526C39C1776A ON comment');
        $this->addSql('ALTER TABLE comment CHANGE parent_post_id parent_post_id VARCHAR(10) NOT NULL');
    }
}
