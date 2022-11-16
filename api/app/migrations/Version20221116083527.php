<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221116083527 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE thumbnail (id INT AUTO_INCREMENT NOT NULL, filename VARCHAR(75) NOT NULL, dir_one VARCHAR(5) NOT NULL, dir_two VARCHAR(5) NOT NULL, source_url VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE media_asset DROP thumbnail_source_url, DROP thumbnail_filename');
        $this->addSql('ALTER TABLE post ADD thumbnail_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE post ADD CONSTRAINT FK_5A8A6C8DFDFF2E92 FOREIGN KEY (thumbnail_id) REFERENCES thumbnail (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5A8A6C8DFDFF2E92 ON post (thumbnail_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE post DROP FOREIGN KEY FK_5A8A6C8DFDFF2E92');
        $this->addSql('DROP TABLE thumbnail');
        $this->addSql('ALTER TABLE media_asset ADD thumbnail_source_url VARCHAR(255) DEFAULT NULL, ADD thumbnail_filename VARCHAR(75) DEFAULT NULL');
        $this->addSql('DROP INDEX UNIQ_5A8A6C8DFDFF2E92 ON post');
        $this->addSql('ALTER TABLE post DROP thumbnail_id');
    }
}
