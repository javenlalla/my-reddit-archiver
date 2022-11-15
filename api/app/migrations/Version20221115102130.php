<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221115102130 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE comment_award (id INT AUTO_INCREMENT NOT NULL, comment_id INT NOT NULL, award_id INT NOT NULL, count INT DEFAULT 0 NOT NULL, INDEX IDX_23C1B616F8697D13 (comment_id), INDEX IDX_23C1B6163D5282CF (award_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE comment_award ADD CONSTRAINT FK_23C1B616F8697D13 FOREIGN KEY (comment_id) REFERENCES comment (id)');
        $this->addSql('ALTER TABLE comment_award ADD CONSTRAINT FK_23C1B6163D5282CF FOREIGN KEY (award_id) REFERENCES award (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE comment_award');
    }
}
