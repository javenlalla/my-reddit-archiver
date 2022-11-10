<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221110113811 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE award (id INT AUTO_INCREMENT NOT NULL, reddit_id VARCHAR(50) NOT NULL, name VARCHAR(30) NOT NULL, description VARCHAR(100) DEFAULT NULL, reference_id VARCHAR(10) NOT NULL, reddit_url VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE post_award (id INT AUTO_INCREMENT NOT NULL, post_id INT NOT NULL, award_id INT NOT NULL, count INT DEFAULT 0 NOT NULL, INDEX IDX_1D40A2084B89032C (post_id), INDEX IDX_1D40A2083D5282CF (award_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE post_award ADD CONSTRAINT FK_1D40A2084B89032C FOREIGN KEY (post_id) REFERENCES post (id)');
        $this->addSql('ALTER TABLE post_award ADD CONSTRAINT FK_1D40A2083D5282CF FOREIGN KEY (award_id) REFERENCES award (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE post_award DROP FOREIGN KEY FK_1D40A2083D5282CF');
        $this->addSql('DROP TABLE award');
        $this->addSql('DROP TABLE post_award');
    }
}
