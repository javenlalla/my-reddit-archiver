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

        /********Tables********/

        /****api_user****/
        $this->addSql('CREATE TABLE api_user (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(100) NOT NULL, access_token VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        /****author_text****/
        $this->addSql('CREATE TABLE author_text (id INT AUTO_INCREMENT NOT NULL, text LONGTEXT NOT NULL, text_raw_html LONGTEXT NOT NULL, text_html LONGTEXT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        /****award****/
        $this->addSql('CREATE TABLE award (id INT AUTO_INCREMENT NOT NULL, reddit_id VARCHAR(50) NOT NULL, name VARCHAR(30) NOT NULL, description VARCHAR(255) DEFAULT NULL, reference_id VARCHAR(10) NOT NULL, icon_asset_id INT NOT NULL, UNIQUE INDEX UNIQ_8A5B2EE718CF367E (icon_asset_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        /****comment****/
        $this->addSql('CREATE TABLE comment (id INT AUTO_INCREMENT NOT NULL, parent_comment_id INT DEFAULT NULL, parent_post_id INT NOT NULL, author VARCHAR(25) NOT NULL, score INT DEFAULT 0 NOT NULL, reddit_id VARCHAR(10) NOT NULL, reddit_url LONGTEXT NOT NULL, flair_text VARCHAR(150) DEFAULT NULL, depth INT DEFAULT 0 NOT NULL, UNIQUE INDEX UNIQ_9474526CA5B44A4D (reddit_id), INDEX IDX_9474526CBF2AF943 (parent_comment_id), INDEX IDX_9474526C39C1776A (parent_post_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        /****comment_author_text****/
        $this->addSql('CREATE TABLE comment_author_text (id INT AUTO_INCREMENT NOT NULL, comment_id INT NOT NULL, author_text_id INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_E488EE5AF8697D13 (comment_id), UNIQUE INDEX UNIQ_E488EE5A2CB7AA0B (author_text_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        /****comment_award****/
        $this->addSql('CREATE TABLE comment_award (id INT AUTO_INCREMENT NOT NULL, comment_id INT NOT NULL, award_id INT NOT NULL, count INT DEFAULT 0 NOT NULL, INDEX IDX_23C1B616F8697D13 (comment_id), INDEX IDX_23C1B6163D5282CF (award_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        /****content****/
        $this->addSql('CREATE TABLE content (id INT AUTO_INCREMENT NOT NULL, full_reddit_id VARCHAR(15) NOT NULL, post_id INT NOT NULL, comment_id INT DEFAULT NULL, kind_id INT NOT NULL, next_sync_date DATETIME DEFAULT NULL, INDEX IDX_FEC530A94B89032C (post_id), UNIQUE INDEX UNIQ_FEC530A9F8697D13 (comment_id), INDEX IDX_FEC530A930602CA9 (kind_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        /****kind****/
        $this->addSql('CREATE TABLE kind (id INT AUTO_INCREMENT NOT NULL, reddit_kind_id VARCHAR(2) NOT NULL, name VARCHAR(20) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        /****post****/
        $this->addSql('CREATE TABLE post (id INT AUTO_INCREMENT NOT NULL, type_id INT NOT NULL, reddit_id VARCHAR(10) NOT NULL, subreddit_id INT NOT NULL, title LONGTEXT NOT NULL, score INT DEFAULT 0 NOT NULL, url LONGTEXT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', author VARCHAR(25) NOT NULL, reddit_post_url VARCHAR(255) NOT NULL, flair_text VARCHAR(150) DEFAULT NULL, thumbnail_asset_id INT DEFAULT NULL, is_archived TINYINT(1) DEFAULT 0 NOT NULL, UNIQUE INDEX UNIQ_5A8A6C8DA5B44A4D (reddit_id), UNIQUE INDEX UNIQ_5A8A6C8D2C2174B2 (thumbnail_asset_id), INDEX IDX_5A8A6C8DC54C8C93 (type_id), INDEX IDX_5A8A6C8D31DBE174 (subreddit_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        /****post_author_text****/
        $this->addSql('CREATE TABLE post_author_text (id INT AUTO_INCREMENT NOT NULL, post_id INT NOT NULL, author_text_id INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_3324A5374B89032C (post_id), UNIQUE INDEX UNIQ_3324A5372CB7AA0B (author_text_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        /****post_award****/
        $this->addSql('CREATE TABLE post_award (id INT AUTO_INCREMENT NOT NULL, post_id INT NOT NULL, award_id INT NOT NULL, count INT DEFAULT 0 NOT NULL, INDEX IDX_1D40A2084B89032C (post_id), INDEX IDX_1D40A2083D5282CF (award_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        /****type****/
        $this->addSql('CREATE TABLE type (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(20) NOT NULL, display_name VARCHAR(20) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        /****content_tag****/
        $this->addSql('CREATE TABLE content_tag (content_id INT NOT NULL, tag_id INT NOT NULL, INDEX IDX_B662E17684A0A3ED (content_id), INDEX IDX_B662E176BAD26311 (tag_id), PRIMARY KEY(content_id, tag_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        /****tag****/
        $this->addSql('CREATE TABLE tag (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, UNIQUE INDEX UNIQ_389B7835E237E06 (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        /****subreddit****/
        $this->addSql('CREATE TABLE subreddit (id INT AUTO_INCREMENT NOT NULL, reddit_id VARCHAR(15) NOT NULL, name VARCHAR(50) NOT NULL, title LONGTEXT DEFAULT NULL, description LONGTEXT DEFAULT NULL, description_raw_html LONGTEXT DEFAULT NULL, description_html LONGTEXT DEFAULT NULL, public_description LONGTEXT DEFAULT NULL, public_description_raw_html LONGTEXT DEFAULT NULL, public_description_html LONGTEXT DEFAULT NULL, icon_image_asset_id INT DEFAULT NULL, banner_background_image_asset_id INT DEFAULT NULL, banner_image_asset_id INT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_D84B1B124FAE0DCA (icon_image_asset_id), UNIQUE INDEX UNIQ_D84B1B12B854E187 (banner_background_image_asset_id), UNIQUE INDEX UNIQ_D84B1B12D390D58E (banner_image_asset_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        /****asset****/
        $this->addSql('CREATE TABLE asset (id INT AUTO_INCREMENT NOT NULL, filename VARCHAR(75) NOT NULL, dir_one VARCHAR(5) NOT NULL, dir_two VARCHAR(5) NOT NULL, source_url LONGTEXT NOT NULL, audio_filename VARCHAR(75) DEFAULT NULL, audio_source_url LONGTEXT DEFAULT NULL, post_id INT DEFAULT NULL, is_downloaded TINYINT(1) DEFAULT 0 NOT NULL, INDEX IDX_2AF5A5C4B89032C (post_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        /****more_comment****/
        $this->addSql('CREATE TABLE more_comment (id INT AUTO_INCREMENT NOT NULL, parent_comment_id INT DEFAULT NULL, parent_post_id INT DEFAULT NULL, reddit_id VARCHAR(10) NOT NULL, url LONGTEXT NOT NULL, INDEX IDX_6F523441BF2AF943 (parent_comment_id), INDEX IDX_6F52344139C1776A (parent_post_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        /****sync_error_log****/
        $this->addSql('CREATE TABLE sync_error_log (id INT AUTO_INCREMENT NOT NULL, url LONGTEXT DEFAULT NULL, content_json LONGTEXT DEFAULT NULL, error LONGTEXT DEFAULT NULL, error_trace LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        /****asset_error_log****/
        $this->addSql('CREATE TABLE asset_error_log (id INT AUTO_INCREMENT NOT NULL, asset_id INT NOT NULL, error LONGTEXT DEFAULT NULL, error_trace LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_11E5CF935DA1941 (asset_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        /****content_pending_sync****/
        $this->addSql('CREATE TABLE content_pending_sync (id INT AUTO_INCREMENT NOT NULL, profile_content_group_id INT NOT NULL, full_reddit_id VARCHAR(15) NOT NULL, json_data LONGTEXT NOT NULL, parent_json_data LONGTEXT DEFAULT NULL, INDEX IDX_2C554837735BCD0 (profile_content_group_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        /****profile_content_group****/
        $this->addSql('CREATE TABLE profile_content_group (id INT AUTO_INCREMENT NOT NULL, group_name VARCHAR(50) NOT NULL, display_name VARCHAR(100) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        /********Foreign Keys********/
        $this->addSql('ALTER TABLE content_tag ADD CONSTRAINT FK_B662E17684A0A3ED FOREIGN KEY (content_id) REFERENCES content (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE content_tag ADD CONSTRAINT FK_B662E176BAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526CBF2AF943 FOREIGN KEY (parent_comment_id) REFERENCES comment (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C39C1776A FOREIGN KEY (parent_post_id) REFERENCES post (id)');
        $this->addSql('ALTER TABLE comment_author_text ADD CONSTRAINT FK_E488EE5AF8697D13 FOREIGN KEY (comment_id) REFERENCES comment (id)');
        $this->addSql('ALTER TABLE comment_author_text ADD CONSTRAINT FK_E488EE5A2CB7AA0B FOREIGN KEY (author_text_id) REFERENCES author_text (id)');
        $this->addSql('ALTER TABLE comment_award ADD CONSTRAINT FK_23C1B616F8697D13 FOREIGN KEY (comment_id) REFERENCES comment (id)');
        $this->addSql('ALTER TABLE comment_award ADD CONSTRAINT FK_23C1B6163D5282CF FOREIGN KEY (award_id) REFERENCES award (id)');
        $this->addSql('ALTER TABLE content ADD CONSTRAINT FK_FEC530A94B89032C FOREIGN KEY (post_id) REFERENCES post (id)');
        $this->addSql('ALTER TABLE content ADD CONSTRAINT FK_FEC530A9F8697D13 FOREIGN KEY (comment_id) REFERENCES comment (id)');
        $this->addSql('ALTER TABLE content ADD CONSTRAINT FK_FEC530A930602CA9 FOREIGN KEY (kind_id) REFERENCES kind (id)');
        $this->addSql('ALTER TABLE post ADD CONSTRAINT FK_5A8A6C8DC54C8C93 FOREIGN KEY (type_id) REFERENCES type (id)');
        $this->addSql('ALTER TABLE post ADD CONSTRAINT FK_5A8A6C8D31DBE174 FOREIGN KEY (subreddit_id) REFERENCES subreddit (id)');
        $this->addSql('ALTER TABLE post ADD CONSTRAINT FK_5A8A6C8D2C2174B2 FOREIGN KEY (thumbnail_asset_id) REFERENCES asset (id)');
        $this->addSql('ALTER TABLE post_author_text ADD CONSTRAINT FK_3324A5374B89032C FOREIGN KEY (post_id) REFERENCES post (id)');
        $this->addSql('ALTER TABLE post_author_text ADD CONSTRAINT FK_3324A5372CB7AA0B FOREIGN KEY (author_text_id) REFERENCES author_text (id)');
        $this->addSql('ALTER TABLE post_award ADD CONSTRAINT FK_1D40A2084B89032C FOREIGN KEY (post_id) REFERENCES post (id)');
        $this->addSql('ALTER TABLE post_award ADD CONSTRAINT FK_1D40A2083D5282CF FOREIGN KEY (award_id) REFERENCES award (id)');
        $this->addSql('ALTER TABLE subreddit ADD CONSTRAINT FK_D84B1B124FAE0DCA FOREIGN KEY (icon_image_asset_id) REFERENCES asset (id)');
        $this->addSql('ALTER TABLE subreddit ADD CONSTRAINT FK_D84B1B12B854E187 FOREIGN KEY (banner_background_image_asset_id) REFERENCES asset (id)');
        $this->addSql('ALTER TABLE subreddit ADD CONSTRAINT FK_D84B1B12D390D58E FOREIGN KEY (banner_image_asset_id) REFERENCES asset (id)');
        $this->addSql('ALTER TABLE asset ADD CONSTRAINT FK_2AF5A5C4B89032C FOREIGN KEY (post_id) REFERENCES post (id)');
        $this->addSql('ALTER TABLE award ADD CONSTRAINT FK_8A5B2EE718CF367E FOREIGN KEY (icon_asset_id) REFERENCES asset (id)');
        $this->addSql('ALTER TABLE more_comment ADD CONSTRAINT FK_6F523441BF2AF943 FOREIGN KEY (parent_comment_id) REFERENCES comment (id)');
        $this->addSql('ALTER TABLE more_comment ADD CONSTRAINT FK_6F52344139C1776A FOREIGN KEY (parent_post_id) REFERENCES post (id)');
        $this->addSql('ALTER TABLE asset_error_log ADD CONSTRAINT FK_11E5CF935DA1941 FOREIGN KEY (asset_id) REFERENCES asset (id)');
        $this->addSql('ALTER TABLE content_pending_sync ADD CONSTRAINT FK_2C554837735BCD0 FOREIGN KEY (profile_content_group_id) REFERENCES profile_content_group (id)');

        // Insert setup data.
        // Kinds.
        $this->addSql('INSERT INTO kind (reddit_kind_id, name) VALUES
            ("t1","Comment"),
            ("t3", "Link")
        ');

        // Types.
        $this->addSql('INSERT INTO type (name, display_name) VALUES
            ("image","Image"),
            ("video", "Video"),
            ("text", "Text"),
            ("image_gallery", "Image Gallery"),
            ("gif", "GIF"),
            ("external_link", "External Link")
        ');

        // Profile Content Groups.
        $this->addSql('INSERT INTO profile_content_group (group_name, display_name) VALUES
            ("saved","Saved"),
            ("submitted", "Submitted Posts"),
            ("comments", "Comments"),
            ("upvoted", "Upvoted Content"),
            ("downvoted", "Downvoted Content"),
            ("gilded", "Gilded")
        ');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE subreddit DROP FOREIGN KEY FK_D84B1B124FAE0DCA');
        $this->addSql('ALTER TABLE subreddit DROP FOREIGN KEY FK_D84B1B12B854E187');
        $this->addSql('ALTER TABLE subreddit DROP FOREIGN KEY FK_D84B1B12D390D58E');
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
        $this->addSql('ALTER TABLE post_author_text DROP FOREIGN KEY FK_3324A5374B89032C');
        $this->addSql('ALTER TABLE post_award DROP FOREIGN KEY FK_1D40A2084B89032C');
        $this->addSql('ALTER TABLE post DROP FOREIGN KEY FK_5A8A6C8DFDFF2E92');
        $this->addSql('ALTER TABLE post DROP FOREIGN KEY FK_5A8A6C8DC54C8C93');
        $this->addSql('ALTER TABLE post DROP FOREIGN KEY FK_5A8A6C8D31DBE174');
        $this->addSql('ALTER TABLE post DROP FOREIGN KEY FK_5A8A6C8D2C2174B2');
        $this->addSql('ALTER TABLE content_tag DROP FOREIGN KEY FK_B662E176BAD26311');
        $this->addSql('ALTER TABLE asset DROP FOREIGN KEY FK_2AF5A5C4B89032C');
        $this->addSql('ALTER TABLE award DROP FOREIGN KEY FK_8A5B2EE718CF367E');
        $this->addSql('ALTER TABLE asset_error_log DROP FOREIGN KEY FK_11E5CF935DA1941');
        $this->addSql('ALTER TABLE content_pending_sync DROP FOREIGN KEY FK_2C554837735BCD0');
        $this->addSql('DROP TABLE api_user');
        $this->addSql('DROP TABLE author_text');
        $this->addSql('DROP TABLE award');
        $this->addSql('DROP TABLE comment');
        $this->addSql('DROP TABLE comment_author_text');
        $this->addSql('DROP TABLE comment_award');
        $this->addSql('DROP TABLE content');
        $this->addSql('DROP TABLE kind');
        $this->addSql('DROP TABLE post');
        $this->addSql('DROP TABLE post_author_text');
        $this->addSql('DROP TABLE post_award');
        $this->addSql('DROP TABLE type');
        $this->addSql('DROP TABLE content_tag');
        $this->addSql('DROP TABLE tag');
        $this->addSql('DROP TABLE subreddit');
        $this->addSql('DROP TABLE asset');
        $this->addSql('DROP TABLE more_comment');
        $this->addSql('DROP TABLE sync_error_log');
        $this->addSql('DROP TABLE asset_error_log');
        $this->addSql('DROP TABLE content_pending_sync');
        $this->addSql('DROP TABLE profile_content_group');
    }
}
