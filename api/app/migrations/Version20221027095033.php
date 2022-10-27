<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221027095033 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE author_text (id INT AUTO_INCREMENT NOT NULL, text LONGTEXT NOT NULL, text_raw_html LONGTEXT NOT NULL, text_html LONGTEXT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE post_author_text (id INT AUTO_INCREMENT NOT NULL, post_id INT NOT NULL, author_text_id INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_3324A5374B89032C (post_id), UNIQUE INDEX UNIQ_3324A5372CB7AA0B (author_text_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE post_author_text ADD CONSTRAINT FK_3324A5374B89032C FOREIGN KEY (post_id) REFERENCES post (id)');
        $this->addSql('ALTER TABLE post_author_text ADD CONSTRAINT FK_3324A5372CB7AA0B FOREIGN KEY (author_text_id) REFERENCES author_text (id)');
        $this->addSql('DROP TABLE content_type');
        $this->addSql('ALTER TABLE post DROP author_text, DROP author_text_html, DROP author_text_raw_html');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE post_author_text DROP FOREIGN KEY FK_3324A5372CB7AA0B');
        $this->addSql('CREATE TABLE content_type (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, display_name VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('DROP TABLE author_text');
        $this->addSql('DROP TABLE post_author_text');
        $this->addSql('ALTER TABLE post ADD author_text LONGTEXT DEFAULT NULL, ADD author_text_html LONGTEXT DEFAULT NULL, ADD author_text_raw_html LONGTEXT DEFAULT NULL');
    }
}
