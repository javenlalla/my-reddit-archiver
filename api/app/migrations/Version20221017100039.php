<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221017100039 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE kind (id INT AUTO_INCREMENT NOT NULL, reddit_kind_id VARCHAR(2) NOT NULL, name VARCHAR(20) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE content DROP FOREIGN KEY FK_FEC530A9C54C8C93');
        $this->addSql('DROP INDEX IDX_FEC530A9C54C8C93 ON content');
        $this->addSql('ALTER TABLE content CHANGE type_id kind_id INT NOT NULL');
        $this->addSql('ALTER TABLE content ADD CONSTRAINT FK_FEC530A930602CA9 FOREIGN KEY (kind_id) REFERENCES kind (id)');
        $this->addSql('CREATE INDEX IDX_FEC530A930602CA9 ON content (kind_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE content DROP FOREIGN KEY FK_FEC530A930602CA9');
        $this->addSql('DROP TABLE kind');
        $this->addSql('DROP INDEX IDX_FEC530A930602CA9 ON content');
        $this->addSql('ALTER TABLE content CHANGE kind_id type_id INT NOT NULL');
        $this->addSql('ALTER TABLE content ADD CONSTRAINT FK_FEC530A9C54C8C93 FOREIGN KEY (type_id) REFERENCES type (id)');
        $this->addSql('CREATE INDEX IDX_FEC530A9C54C8C93 ON content (type_id)');
    }
}
