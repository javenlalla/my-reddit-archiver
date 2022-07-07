<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220707213740 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE content_type (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(10) NOT NULL, display_name VARCHAR(10) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE type (id INT AUTO_INCREMENT NOT NULL, reddit_type_id VARCHAR(2) NOT NULL, name VARCHAR(20) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE post ADD type_id_id INT NOT NULL, ADD content_type_id_id INT NOT NULL');
        $this->addSql('ALTER TABLE post ADD CONSTRAINT FK_5A8A6C8D714819A0 FOREIGN KEY (type_id_id) REFERENCES type (id)');
        $this->addSql('ALTER TABLE post ADD CONSTRAINT FK_5A8A6C8DE871AF52 FOREIGN KEY (content_type_id_id) REFERENCES content_type (id)');
        $this->addSql('CREATE INDEX IDX_5A8A6C8D714819A0 ON post (type_id_id)');
        $this->addSql('CREATE INDEX IDX_5A8A6C8DE871AF52 ON post (content_type_id_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE post DROP FOREIGN KEY FK_5A8A6C8DE871AF52');
        $this->addSql('ALTER TABLE post DROP FOREIGN KEY FK_5A8A6C8D714819A0');
        $this->addSql('DROP TABLE content_type');
        $this->addSql('DROP TABLE type');
        $this->addSql('DROP INDEX IDX_5A8A6C8D714819A0 ON post');
        $this->addSql('DROP INDEX IDX_5A8A6C8DE871AF52 ON post');
        $this->addSql('ALTER TABLE post DROP type_id_id, DROP content_type_id_id');
    }
}
