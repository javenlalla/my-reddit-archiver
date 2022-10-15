<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221015122031 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE content (id INT AUTO_INCREMENT NOT NULL, type_id INT NOT NULL, content_type_id INT NOT NULL, post_id INT NOT NULL, comment_id INT DEFAULT NULL, sync_date DATETIME NOT NULL, INDEX IDX_FEC530A9C54C8C93 (type_id), INDEX IDX_FEC530A91A445520 (content_type_id), UNIQUE INDEX UNIQ_FEC530A94B89032C (post_id), UNIQUE INDEX UNIQ_FEC530A9F8697D13 (comment_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE content ADD CONSTRAINT FK_FEC530A9C54C8C93 FOREIGN KEY (type_id) REFERENCES type (id)');
        $this->addSql('ALTER TABLE content ADD CONSTRAINT FK_FEC530A91A445520 FOREIGN KEY (content_type_id) REFERENCES content_type (id)');
        $this->addSql('ALTER TABLE content ADD CONSTRAINT FK_FEC530A94B89032C FOREIGN KEY (post_id) REFERENCES post (id)');
        $this->addSql('ALTER TABLE content ADD CONSTRAINT FK_FEC530A9F8697D13 FOREIGN KEY (comment_id) REFERENCES comment (id)');
        $this->addSql('ALTER TABLE post DROP FOREIGN KEY FK_5A8A6C8DC54C8C93');
        $this->addSql('ALTER TABLE post DROP FOREIGN KEY FK_5A8A6C8D1A445520');
        $this->addSql('DROP INDEX IDX_5A8A6C8DC54C8C93 ON post');
        $this->addSql('DROP INDEX IDX_5A8A6C8D1A445520 ON post');
        $this->addSql('ALTER TABLE post DROP type_id, DROP content_type_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE content');
        $this->addSql('ALTER TABLE post ADD type_id INT NOT NULL, ADD content_type_id INT NOT NULL');
        $this->addSql('ALTER TABLE post ADD CONSTRAINT FK_5A8A6C8DC54C8C93 FOREIGN KEY (type_id) REFERENCES type (id)');
        $this->addSql('ALTER TABLE post ADD CONSTRAINT FK_5A8A6C8D1A445520 FOREIGN KEY (content_type_id) REFERENCES content_type (id)');
        $this->addSql('CREATE INDEX IDX_5A8A6C8DC54C8C93 ON post (type_id)');
        $this->addSql('CREATE INDEX IDX_5A8A6C8D1A445520 ON post (content_type_id)');
    }
}
