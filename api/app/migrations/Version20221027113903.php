<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221027113903 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE comment_author_text (id INT AUTO_INCREMENT NOT NULL, comment_id INT NOT NULL, author_text_id INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_E488EE5AF8697D13 (comment_id), UNIQUE INDEX UNIQ_E488EE5A2CB7AA0B (author_text_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE comment_author_text ADD CONSTRAINT FK_E488EE5AF8697D13 FOREIGN KEY (comment_id) REFERENCES comment (id)');
        $this->addSql('ALTER TABLE comment_author_text ADD CONSTRAINT FK_E488EE5A2CB7AA0B FOREIGN KEY (author_text_id) REFERENCES author_text (id)');
        $this->addSql('ALTER TABLE comment DROP text');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE comment_author_text');
        $this->addSql('ALTER TABLE comment ADD text LONGTEXT NOT NULL');
    }
}
