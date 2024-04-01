<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240329111958 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE flair_text ADD COLUMN label_color VARCHAR(6) NOT NULL DEFAULT "000000"');
        $this->addSql('ALTER TABLE flair_text ADD COLUMN label_font_color VARCHAR(6) NOT NULL DEFAULT "FFFFFF"');
        $this->addSql('ALTER TABLE tag ADD COLUMN label_color VARCHAR(6) NOT NULL DEFAULT "000000"');
        $this->addSql('ALTER TABLE tag ADD COLUMN label_font_color VARCHAR(6) NOT NULL DEFAULT "FFFFFF"');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__flair_text AS SELECT id, plain_text, display_text, reference_id FROM flair_text');
        $this->addSql('DROP TABLE flair_text');
        $this->addSql('CREATE TABLE flair_text (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, plain_text VARCHAR(255) NOT NULL, display_text VARCHAR(255) NOT NULL, reference_id VARCHAR(10) NOT NULL)');
        $this->addSql('INSERT INTO flair_text (id, plain_text, display_text, reference_id) SELECT id, plain_text, display_text, reference_id FROM __temp__flair_text');
        $this->addSql('DROP TABLE __temp__flair_text');
        $this->addSql('CREATE TEMPORARY TABLE __temp__tag AS SELECT id, name FROM tag');
        $this->addSql('DROP TABLE tag');
        $this->addSql('CREATE TABLE tag (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(100) NOT NULL)');
        $this->addSql('INSERT INTO tag (id, name) SELECT id, name FROM __temp__tag');
        $this->addSql('DROP TABLE __temp__tag');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_389B7835E237E06 ON tag (name)');
    }
}
