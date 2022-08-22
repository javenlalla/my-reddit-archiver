<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220821104859 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE media_asset (id INT AUTO_INCREMENT NOT NULL, parent_post_id INT NOT NULL, filename VARCHAR(40) NOT NULL, dir_one VARCHAR(5) NOT NULL, dir_two VARCHAR(5) NOT NULL, INDEX IDX_1DB69EED39C1776A (parent_post_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE media_asset ADD CONSTRAINT FK_1DB69EED39C1776A FOREIGN KEY (parent_post_id) REFERENCES post (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE media_asset');
    }
}
