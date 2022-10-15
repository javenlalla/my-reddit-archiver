<?php

namespace App\DataFixtures;

use App\Entity\Comment;
use App\Entity\ContentType;
use App\Entity\Post;
use App\Entity\Content;
use App\Entity\Type;
use App\Repository\CommentRepository;
use App\Repository\ContentTypeRepository;
use App\Repository\PostRepository;
use App\Repository\TypeRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class PostFixtures extends Fixture
{
    public function __construct(
        private readonly PostRepository $postRepository,
        private readonly CommentRepository $commentRepository,
        private readonly TypeRepository $typeRepository,
        private readonly ContentTypeRepository $contentTypeRepository
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $this->loadTypes($manager);
        $this->loadContentTypes($manager);
        $manager->flush();

        // Create Contents.
        $contentsListing = [];
        $contentsDataFile = fopen('/var/www/mra-api/resources/data-fixtures-source-files/contents.csv', 'r');
        while (($contentRow = fgetcsv($contentsDataFile)) !== FALSE) {
            // Skip header row (first row).
            if ($contentRow[0] !== 'typeId') {
                $content = $this->hydrateContentFromCsvRow($contentRow);
                $manager->persist($content);

                $contentsListing[$contentRow[2]] = $content;
            }
        }
        fclose($contentsDataFile);

        // Create Posts.
        $postsDataFile = fopen('/var/www/mra-api/resources/data-fixtures-source-files/posts.csv', 'r');
        while (($postRow = fgetcsv($postsDataFile)) !== FALSE) {
            // Skip header row (first row).
            if ($postRow[0] !== 'typeId') {
                $post = $this->hydratePostFromCsvRow($postRow);
                $contentsListing[$post->getRedditId()]->setPost($post);

                $manager->persist($post);
                $manager->persist($contentsListing[$post->getRedditId()]);
            }
        }
        fclose($postsDataFile);

        $manager->flush();

        // Create Comments.
        $commentsDataFile = fopen('/var/www/mra-api/resources/data-fixtures-source-files/comments.csv', 'r');
        while (($commentRow = fgetcsv($commentsDataFile)) !== FALSE) {
            // Skip header row (first row).
            if ($commentRow[0] !== 'redditPostId') {
                $comment = $this->hydrateCommentFromCsvRow($commentRow);
                $manager->persist($comment);

                // Persist Comments immediately in order for fetching Parent Comments during hydration.
                $manager->flush();

            }
        }
        fclose($commentsDataFile);

        // Create Media Assets.
    }

    /**
     * Sanitize the provided raw CSV line containing a Post record and return
     * a new, hydrated Post entity.
     *
     * @param  array  $postRow
     *
     * @return Post
     */
    private function hydratePostFromCsvRow(array $postRow): Post
    {
        $post = new Post();

        $post->setRedditId($postRow[2]);
        $post->setTitle($postRow[3]);
        $post->setScore((int) $postRow[4]);
        $post->setUrl($postRow[5]);
        $post->setAuthor($postRow[6]);
        $post->setSubreddit($postRow[7]);
        $post->setRedditPostId($postRow[8]);
        $post->setRedditPostUrl($postRow[9]);
        $post->setAuthorText(!empty($postRow[10]) ? $postRow[10]: null);
        $post->setAuthorTextHtml(!empty($postRow[11]) ? $postRow[11]: null);
        $post->setAuthorTextRawHtml(!empty($postRow[12]) ? $postRow[12]: null);
        $post->setCreatedAt(new \DateTimeImmutable());

        return $post;
    }

    private function hydrateContentFromCsvRow(array $contentRow): Content
    {
        $content = new Content();

        $type = $this->typeRepository->findOneBy(['redditTypeId' => $contentRow[0]]);
        $content->setType($type);

        $contentType = $this->contentTypeRepository->findOneBy(['name' => $contentRow[1]]);
        $content->setContentType($contentType);

        $content->setSyncDate(new \DateTimeImmutable());

        return $content;
    }

    private function loadTypes(ObjectManager $manager): void
    {
        $types = [
            [
                'redditTypeId' => 't1',
                'name' => 'Comment',
            ],
            [
                'redditTypeId' => 't3',
                'name' => 'Link',
            ],
        ];

        foreach ($types as $type) {
            $typeEntity = new Type();
            $typeEntity->setRedditTypeId($type['redditTypeId']);
            $typeEntity->setName($type['name']);

            $manager->persist($typeEntity);
        }
    }

    private function loadContentTypes(ObjectManager $manager)
    {
        $contentTypes = [
            [
                'name' => 'image',
                'displayName' => 'Image',
            ],
            [
                'name' => 'video',
                'displayName' => 'Video',
            ],
            [
                'name' => 'text',
                'displayName' => 'Text',
            ],
            [
                'name' => 'image_gallery',
                'displayName' => 'Image Gallery',
            ],
            [
                'name' => 'gif',
                'displayName' => 'GIF',
            ],
            [
                'name' => 'external_link',
                'displayName' => 'External Link',
            ],
        ];

        foreach ($contentTypes as $contentType) {
            $contentTypeEntity = new ContentType();
            $contentTypeEntity->setName($contentType['name']);
            $contentTypeEntity->setDisplayName($contentType['displayName']);

            $manager->persist($contentTypeEntity);
        }
    }

    /**
     * Sanitize the provided raw CSV line containing a Comment record and return
     * a new, hydrated Comment entity.
     *
     * Assign the Post Entity and parent Comment Entity as necessary.
     *
     * @param  array  $commentRow
     *
     * @return Comment
     */
    private function hydrateCommentFromCsvRow(array $commentRow): Comment
    {
        $post = $this->postRepository->findOneBy(['redditId' => $commentRow['0']]);

        $comment = new Comment();
        $comment->setParentPost($post);
        $comment->setText($commentRow[2]);
        $comment->setAuthor($commentRow[3]);
        $comment->setScore((int) $commentRow[4]);
        $comment->setRedditId($commentRow[5]);
        $comment->setDepth((int) $commentRow[6]);

        if (!empty($commentRow[1])) {
            $parentComment = $this->commentRepository->findOneBy(['redditId' => $commentRow[1]]);
            $comment->setParentComment($parentComment);
        }

        return $comment;
    }
}
