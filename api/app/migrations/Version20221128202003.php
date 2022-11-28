<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221128202003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE api_user (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(100) NOT NULL, access_token VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE author_text (id INT AUTO_INCREMENT NOT NULL, text LONGTEXT NOT NULL, text_raw_html LONGTEXT NOT NULL, text_html LONGTEXT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE award (id INT AUTO_INCREMENT NOT NULL, reddit_id VARCHAR(50) NOT NULL, name VARCHAR(30) NOT NULL, description VARCHAR(200) DEFAULT NULL, reference_id VARCHAR(10) NOT NULL, reddit_url VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE comment (id INT AUTO_INCREMENT NOT NULL, parent_comment_id INT DEFAULT NULL, parent_post_id INT NOT NULL, author VARCHAR(25) NOT NULL, score INT DEFAULT 0 NOT NULL, reddit_id VARCHAR(10) NOT NULL, depth INT DEFAULT 0 NOT NULL, UNIQUE INDEX UNIQ_9474526CA5B44A4D (reddit_id), INDEX IDX_9474526CBF2AF943 (parent_comment_id), INDEX IDX_9474526C39C1776A (parent_post_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE comment_author_text (id INT AUTO_INCREMENT NOT NULL, comment_id INT NOT NULL, author_text_id INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_E488EE5AF8697D13 (comment_id), UNIQUE INDEX UNIQ_E488EE5A2CB7AA0B (author_text_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE comment_award (id INT AUTO_INCREMENT NOT NULL, comment_id INT NOT NULL, award_id INT NOT NULL, count INT DEFAULT 0 NOT NULL, INDEX IDX_23C1B616F8697D13 (comment_id), INDEX IDX_23C1B6163D5282CF (award_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE content (id INT AUTO_INCREMENT NOT NULL, post_id INT NOT NULL, comment_id INT DEFAULT NULL, kind_id INT NOT NULL, sync_date DATETIME NOT NULL, INDEX IDX_FEC530A94B89032C (post_id), UNIQUE INDEX UNIQ_FEC530A9F8697D13 (comment_id), INDEX IDX_FEC530A930602CA9 (kind_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE kind (id INT AUTO_INCREMENT NOT NULL, reddit_kind_id VARCHAR(2) NOT NULL, name VARCHAR(20) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE media_asset (id INT AUTO_INCREMENT NOT NULL, parent_post_id INT NOT NULL, filename VARCHAR(40) NOT NULL, dir_one VARCHAR(5) NOT NULL, dir_two VARCHAR(5) NOT NULL, source_url VARCHAR(255) NOT NULL, audio_source_url VARCHAR(255) DEFAULT NULL, audio_filename VARCHAR(55) DEFAULT NULL, INDEX IDX_1DB69EED39C1776A (parent_post_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE post (id INT AUTO_INCREMENT NOT NULL, type_id INT NOT NULL, thumbnail_id INT DEFAULT NULL, reddit_id VARCHAR(10) NOT NULL, title LONGTEXT NOT NULL, score INT DEFAULT 0 NOT NULL, url VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', author VARCHAR(25) NOT NULL, subreddit VARCHAR(25) NOT NULL, reddit_post_url VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_5A8A6C8DA5B44A4D (reddit_id), INDEX IDX_5A8A6C8DC54C8C93 (type_id), UNIQUE INDEX UNIQ_5A8A6C8DFDFF2E92 (thumbnail_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE post_author_text (id INT AUTO_INCREMENT NOT NULL, post_id INT NOT NULL, author_text_id INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_3324A5374B89032C (post_id), UNIQUE INDEX UNIQ_3324A5372CB7AA0B (author_text_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE post_award (id INT AUTO_INCREMENT NOT NULL, post_id INT NOT NULL, award_id INT NOT NULL, count INT DEFAULT 0 NOT NULL, INDEX IDX_1D40A2084B89032C (post_id), INDEX IDX_1D40A2083D5282CF (award_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE thumbnail (id INT AUTO_INCREMENT NOT NULL, filename VARCHAR(75) NOT NULL, dir_one VARCHAR(5) NOT NULL, dir_two VARCHAR(5) NOT NULL, source_url VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE type (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(20) NOT NULL, display_name VARCHAR(20) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526CBF2AF943 FOREIGN KEY (parent_comment_id) REFERENCES comment (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C39C1776A FOREIGN KEY (parent_post_id) REFERENCES post (id)');
        $this->addSql('ALTER TABLE comment_author_text ADD CONSTRAINT FK_E488EE5AF8697D13 FOREIGN KEY (comment_id) REFERENCES comment (id)');
        $this->addSql('ALTER TABLE comment_author_text ADD CONSTRAINT FK_E488EE5A2CB7AA0B FOREIGN KEY (author_text_id) REFERENCES author_text (id)');
        $this->addSql('ALTER TABLE comment_award ADD CONSTRAINT FK_23C1B616F8697D13 FOREIGN KEY (comment_id) REFERENCES comment (id)');
        $this->addSql('ALTER TABLE comment_award ADD CONSTRAINT FK_23C1B6163D5282CF FOREIGN KEY (award_id) REFERENCES award (id)');
        $this->addSql('ALTER TABLE content ADD CONSTRAINT FK_FEC530A94B89032C FOREIGN KEY (post_id) REFERENCES post (id)');
        $this->addSql('ALTER TABLE content ADD CONSTRAINT FK_FEC530A9F8697D13 FOREIGN KEY (comment_id) REFERENCES comment (id)');
        $this->addSql('ALTER TABLE content ADD CONSTRAINT FK_FEC530A930602CA9 FOREIGN KEY (kind_id) REFERENCES kind (id)');
        $this->addSql('ALTER TABLE media_asset ADD CONSTRAINT FK_1DB69EED39C1776A FOREIGN KEY (parent_post_id) REFERENCES post (id)');
        $this->addSql('ALTER TABLE post ADD CONSTRAINT FK_5A8A6C8DC54C8C93 FOREIGN KEY (type_id) REFERENCES type (id)');
        $this->addSql('ALTER TABLE post ADD CONSTRAINT FK_5A8A6C8DFDFF2E92 FOREIGN KEY (thumbnail_id) REFERENCES thumbnail (id)');
        $this->addSql('ALTER TABLE post_author_text ADD CONSTRAINT FK_3324A5374B89032C FOREIGN KEY (post_id) REFERENCES post (id)');
        $this->addSql('ALTER TABLE post_author_text ADD CONSTRAINT FK_3324A5372CB7AA0B FOREIGN KEY (author_text_id) REFERENCES author_text (id)');
        $this->addSql('ALTER TABLE post_award ADD CONSTRAINT FK_1D40A2084B89032C FOREIGN KEY (post_id) REFERENCES post (id)');
        $this->addSql('ALTER TABLE post_award ADD CONSTRAINT FK_1D40A2083D5282CF FOREIGN KEY (award_id) REFERENCES award (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE comment_author_text DROP FOREIGN KEY FK_E488EE5A2CB7AA0B');
        $this->addSql('ALTER TABLE post_author_text DROP FOREIGN KEY FK_3324A5372CB7AA0B');
        $this->addSql('ALTER TABLE comment_award DROP FOREIGN KEY FK_23C1B6163D5282CF');
        $this->addSql('ALTER TABLE post_award DROP FOREIGN KEY FK_1D40A2083D5282CF');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526CBF2AF943');
        $this->addSql('ALTER TABLE comment_author_text DROP FOREIGN KEY FK_E488EE5AF8697D13');
        $this->addSql('ALTER TABLE comment_award DROP FOREIGN KEY FK_23C1B616F8697D13');
        $this->addSql('ALTER TABLE content DROP FOREIGN KEY FK_FEC530A9F8697D13');
        $this->addSql('ALTER TABLE content DROP FOREIGN KEY FK_FEC530A930602CA9');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C39C1776A');
        $this->addSql('ALTER TABLE content DROP FOREIGN KEY FK_FEC530A94B89032C');
        $this->addSql('ALTER TABLE media_asset DROP FOREIGN KEY FK_1DB69EED39C1776A');
        $this->addSql('ALTER TABLE post_author_text DROP FOREIGN KEY FK_3324A5374B89032C');
        $this->addSql('ALTER TABLE post_award DROP FOREIGN KEY FK_1D40A2084B89032C');
        $this->addSql('ALTER TABLE post DROP FOREIGN KEY FK_5A8A6C8DFDFF2E92');
        $this->addSql('ALTER TABLE post DROP FOREIGN KEY FK_5A8A6C8DC54C8C93');
        $this->addSql('DROP TABLE api_user');
        $this->addSql('DROP TABLE author_text');
        $this->addSql('DROP TABLE award');
        $this->addSql('DROP TABLE comment');
        $this->addSql('DROP TABLE comment_author_text');
        $this->addSql('DROP TABLE comment_award');
        $this->addSql('DROP TABLE content');
        $this->addSql('DROP TABLE kind');
        $this->addSql('DROP TABLE media_asset');
        $this->addSql('DROP TABLE post');
        $this->addSql('DROP TABLE post_author_text');
        $this->addSql('DROP TABLE post_award');
        $this->addSql('DROP TABLE thumbnail');
        $this->addSql('DROP TABLE type');
    }
}
