<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231007122422 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE api_call_log (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, endpoint CLOB NOT NULL, method VARCHAR(10) NOT NULL, call_data CLOB DEFAULT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , response CLOB NOT NULL, context VARCHAR(255) NOT NULL)');
        $this->addSql('CREATE TABLE api_user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, username VARCHAR(100) NOT NULL, access_token CLOB DEFAULT NULL)');
        $this->addSql('CREATE TABLE asset (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, post_id INTEGER DEFAULT NULL, filename VARCHAR(75) NOT NULL, dir_one VARCHAR(5) NOT NULL, dir_two VARCHAR(5) NOT NULL, source_url CLOB NOT NULL, audio_filename VARCHAR(75) DEFAULT NULL, audio_source_url CLOB DEFAULT NULL, is_downloaded BOOLEAN DEFAULT 0 NOT NULL, CONSTRAINT FK_2AF5A5C4B89032C FOREIGN KEY (post_id) REFERENCES post (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_2AF5A5C4B89032C ON asset (post_id)');
        $this->addSql('CREATE TABLE asset_error_log (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, asset_id INTEGER NOT NULL, error CLOB DEFAULT NULL, error_trace CLOB DEFAULT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_11E5CF935DA1941 FOREIGN KEY (asset_id) REFERENCES asset (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_11E5CF935DA1941 ON asset_error_log (asset_id)');
        $this->addSql('CREATE TABLE author_text (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, text CLOB NOT NULL, text_raw_html CLOB NOT NULL, text_html CLOB NOT NULL)');
        $this->addSql('CREATE TABLE award (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, icon_asset_id INTEGER NOT NULL, reddit_id VARCHAR(50) NOT NULL, name VARCHAR(30) NOT NULL, description VARCHAR(255) DEFAULT NULL, reference_id VARCHAR(10) NOT NULL, CONSTRAINT FK_8A5B2EE718CF367E FOREIGN KEY (icon_asset_id) REFERENCES asset (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8A5B2EE718CF367E ON award (icon_asset_id)');
        $this->addSql('CREATE TABLE comment (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_comment_id INTEGER DEFAULT NULL, parent_post_id INTEGER NOT NULL, flair_text_id INTEGER DEFAULT NULL, author VARCHAR(25) NOT NULL, score INTEGER DEFAULT 0 NOT NULL, reddit_id VARCHAR(10) NOT NULL, depth INTEGER DEFAULT 0 NOT NULL, reddit_url CLOB NOT NULL, json_data CLOB NOT NULL, has_replies BOOLEAN DEFAULT NULL, parent_comment_reddit_id VARCHAR(15) DEFAULT NULL, CONSTRAINT FK_9474526CBF2AF943 FOREIGN KEY (parent_comment_id) REFERENCES comment (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_9474526C39C1776A FOREIGN KEY (parent_post_id) REFERENCES post (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_9474526C9620E4C5 FOREIGN KEY (flair_text_id) REFERENCES flair_text (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9474526CA5B44A4D ON comment (reddit_id)');
        $this->addSql('CREATE INDEX IDX_9474526CBF2AF943 ON comment (parent_comment_id)');
        $this->addSql('CREATE INDEX IDX_9474526C39C1776A ON comment (parent_post_id)');
        $this->addSql('CREATE INDEX IDX_9474526C9620E4C5 ON comment (flair_text_id)');
        $this->addSql('CREATE TABLE comment_author_text (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, comment_id INTEGER NOT NULL, author_text_id INTEGER NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_E488EE5AF8697D13 FOREIGN KEY (comment_id) REFERENCES comment (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_E488EE5A2CB7AA0B FOREIGN KEY (author_text_id) REFERENCES author_text (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_E488EE5AF8697D13 ON comment_author_text (comment_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E488EE5A2CB7AA0B ON comment_author_text (author_text_id)');
        $this->addSql('CREATE TABLE comment_award (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, comment_id INTEGER NOT NULL, award_id INTEGER NOT NULL, count INTEGER DEFAULT 0 NOT NULL, CONSTRAINT FK_23C1B616F8697D13 FOREIGN KEY (comment_id) REFERENCES comment (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_23C1B6163D5282CF FOREIGN KEY (award_id) REFERENCES award (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_23C1B616F8697D13 ON comment_award (comment_id)');
        $this->addSql('CREATE INDEX IDX_23C1B6163D5282CF ON comment_award (award_id)');
        $this->addSql('CREATE TABLE content (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, post_id INTEGER NOT NULL, comment_id INTEGER DEFAULT NULL, kind_id INTEGER NOT NULL, next_sync_date DATETIME DEFAULT NULL, full_reddit_id VARCHAR(15) NOT NULL, CONSTRAINT FK_FEC530A94B89032C FOREIGN KEY (post_id) REFERENCES post (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_FEC530A9F8697D13 FOREIGN KEY (comment_id) REFERENCES comment (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_FEC530A930602CA9 FOREIGN KEY (kind_id) REFERENCES kind (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_FEC530A94B89032C ON content (post_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FEC530A9F8697D13 ON content (comment_id)');
        $this->addSql('CREATE INDEX IDX_FEC530A930602CA9 ON content (kind_id)');
        $this->addSql('CREATE TABLE content_tag (content_id INTEGER NOT NULL, tag_id INTEGER NOT NULL, PRIMARY KEY(content_id, tag_id), CONSTRAINT FK_B662E17684A0A3ED FOREIGN KEY (content_id) REFERENCES content (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_B662E176BAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_B662E17684A0A3ED ON content_tag (content_id)');
        $this->addSql('CREATE INDEX IDX_B662E176BAD26311 ON content_tag (tag_id)');
        $this->addSql('CREATE TABLE content_pending_sync (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, profile_content_group_id INTEGER NOT NULL, json_data CLOB NOT NULL, full_reddit_id VARCHAR(15) NOT NULL, parent_json_data CLOB DEFAULT NULL, CONSTRAINT FK_2C554837735BCD0 FOREIGN KEY (profile_content_group_id) REFERENCES profile_content_group (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_2C554837735BCD0 ON content_pending_sync (profile_content_group_id)');
        $this->addSql('CREATE TABLE flair_text (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, plain_text VARCHAR(255) NOT NULL, display_text VARCHAR(255) NOT NULL, reference_id VARCHAR(10) NOT NULL)');
        $this->addSql('CREATE TABLE item_json (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, reddit_id VARCHAR(15) NOT NULL, json_body CLOB NOT NULL)');
        $this->addSql('CREATE TABLE kind (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, reddit_kind_id VARCHAR(2) NOT NULL, name VARCHAR(20) NOT NULL)');
        $this->addSql('CREATE TABLE more_comment (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_comment_id INTEGER DEFAULT NULL, parent_post_id INTEGER DEFAULT NULL, reddit_id VARCHAR(10) NOT NULL, url CLOB NOT NULL, CONSTRAINT FK_6F523441BF2AF943 FOREIGN KEY (parent_comment_id) REFERENCES comment (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6F52344139C1776A FOREIGN KEY (parent_post_id) REFERENCES post (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_6F523441BF2AF943 ON more_comment (parent_comment_id)');
        $this->addSql('CREATE INDEX IDX_6F52344139C1776A ON more_comment (parent_post_id)');
        $this->addSql('CREATE TABLE post (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, type_id INTEGER NOT NULL, subreddit_id INTEGER NOT NULL, thumbnail_asset_id INTEGER DEFAULT NULL, flair_text_id INTEGER DEFAULT NULL, reddit_id VARCHAR(10) NOT NULL, title CLOB NOT NULL, score INTEGER DEFAULT 0 NOT NULL, url CLOB NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , author VARCHAR(25) NOT NULL, reddit_post_url VARCHAR(255) NOT NULL, is_archived BOOLEAN DEFAULT 0 NOT NULL, CONSTRAINT FK_5A8A6C8DC54C8C93 FOREIGN KEY (type_id) REFERENCES type (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_5A8A6C8D31DBE174 FOREIGN KEY (subreddit_id) REFERENCES subreddit (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_5A8A6C8D2C2174B2 FOREIGN KEY (thumbnail_asset_id) REFERENCES asset (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_5A8A6C8D9620E4C5 FOREIGN KEY (flair_text_id) REFERENCES flair_text (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5A8A6C8DA5B44A4D ON post (reddit_id)');
        $this->addSql('CREATE INDEX IDX_5A8A6C8DC54C8C93 ON post (type_id)');
        $this->addSql('CREATE INDEX IDX_5A8A6C8D31DBE174 ON post (subreddit_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5A8A6C8D2C2174B2 ON post (thumbnail_asset_id)');
        $this->addSql('CREATE INDEX IDX_5A8A6C8D9620E4C5 ON post (flair_text_id)');
        $this->addSql('CREATE TABLE post_author_text (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, post_id INTEGER NOT NULL, author_text_id INTEGER NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_3324A5374B89032C FOREIGN KEY (post_id) REFERENCES post (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_3324A5372CB7AA0B FOREIGN KEY (author_text_id) REFERENCES author_text (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_3324A5374B89032C ON post_author_text (post_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3324A5372CB7AA0B ON post_author_text (author_text_id)');
        $this->addSql('CREATE TABLE post_award (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, post_id INTEGER NOT NULL, award_id INTEGER NOT NULL, count INTEGER DEFAULT 0 NOT NULL, CONSTRAINT FK_1D40A2084B89032C FOREIGN KEY (post_id) REFERENCES post (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1D40A2083D5282CF FOREIGN KEY (award_id) REFERENCES award (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_1D40A2084B89032C ON post_award (post_id)');
        $this->addSql('CREATE INDEX IDX_1D40A2083D5282CF ON post_award (award_id)');
        $this->addSql('CREATE TABLE profile_content_group (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, group_name VARCHAR(50) NOT NULL, display_name VARCHAR(100) NOT NULL)');
        $this->addSql('CREATE TABLE subreddit (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, icon_image_asset_id INTEGER DEFAULT NULL, banner_background_image_asset_id INTEGER DEFAULT NULL, banner_image_asset_id INTEGER DEFAULT NULL, reddit_id VARCHAR(15) NOT NULL, name VARCHAR(50) NOT NULL, title CLOB DEFAULT NULL, description CLOB DEFAULT NULL, description_raw_html CLOB DEFAULT NULL, description_html CLOB DEFAULT NULL, public_description CLOB DEFAULT NULL, public_description_raw_html CLOB DEFAULT NULL, public_description_html CLOB DEFAULT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_D84B1B124FAE0DCA FOREIGN KEY (icon_image_asset_id) REFERENCES asset (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_D84B1B12B854E187 FOREIGN KEY (banner_background_image_asset_id) REFERENCES asset (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_D84B1B12D390D58E FOREIGN KEY (banner_image_asset_id) REFERENCES asset (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D84B1B124FAE0DCA ON subreddit (icon_image_asset_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D84B1B12B854E187 ON subreddit (banner_background_image_asset_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D84B1B12D390D58E ON subreddit (banner_image_asset_id)');
        $this->addSql('CREATE TABLE sync_error_log (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, url CLOB DEFAULT NULL, content_json CLOB DEFAULT NULL, error CLOB DEFAULT NULL, error_trace CLOB DEFAULT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        )');
        $this->addSql('CREATE TABLE tag (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(100) NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_389B7835E237E06 ON tag (name)');
        $this->addSql('CREATE TABLE type (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(20) NOT NULL, display_name VARCHAR(20) NOT NULL)');
        $this->addSql('CREATE TABLE messenger_messages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, body CLOB NOT NULL, headers CLOB NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , available_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , delivered_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        )');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0 ON messenger_messages (queue_name)');
        $this->addSql('CREATE INDEX IDX_75EA56E0E3BD61CE ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX IDX_75EA56E016BA31DB ON messenger_messages (delivered_at)');

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
        $this->addSql('DROP TABLE api_call_log');
        $this->addSql('DROP TABLE api_user');
        $this->addSql('DROP TABLE asset');
        $this->addSql('DROP TABLE asset_error_log');
        $this->addSql('DROP TABLE author_text');
        $this->addSql('DROP TABLE award');
        $this->addSql('DROP TABLE comment');
        $this->addSql('DROP TABLE comment_author_text');
        $this->addSql('DROP TABLE comment_award');
        $this->addSql('DROP TABLE content');
        $this->addSql('DROP TABLE content_tag');
        $this->addSql('DROP TABLE content_pending_sync');
        $this->addSql('DROP TABLE flair_text');
        $this->addSql('DROP TABLE item_json');
        $this->addSql('DROP TABLE kind');
        $this->addSql('DROP TABLE more_comment');
        $this->addSql('DROP TABLE post');
        $this->addSql('DROP TABLE post_author_text');
        $this->addSql('DROP TABLE post_award');
        $this->addSql('DROP TABLE profile_content_group');
        $this->addSql('DROP TABLE subreddit');
        $this->addSql('DROP TABLE sync_error_log');
        $this->addSql('DROP TABLE tag');
        $this->addSql('DROP TABLE type');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
