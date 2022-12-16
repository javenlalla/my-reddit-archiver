<?php

namespace App\DataFixtures;

use App\Entity\AuthorText;
use App\Entity\Comment;
use App\Entity\CommentAuthorText;
use App\Entity\Kind;
use App\Entity\Post;
use App\Entity\Content;
use App\Entity\PostAuthorText;
use App\Entity\Type;
use App\Helper\SanitizeHtmlHelper;
use App\Repository\CommentRepository;
use App\Repository\KindRepository;
use App\Repository\PostRepository;
use App\Repository\TypeRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class PostFixtures extends Fixture
{
    public function __construct(
        private readonly PostRepository $postRepository,
        private readonly CommentRepository $commentRepository,
        private readonly KindRepository $kindRepository,
        private readonly TypeRepository $typeRepository,
        private readonly SanitizeHtmlHelper $sanitizeHtmlHelper,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $manager->flush();

        // Create Contents.
        $contentsListing = [];
        $contentsDataFile = fopen('/var/www/mra/resources/data-fixtures-source-files/contents.csv', 'r');
        while (($contentRow = fgetcsv($contentsDataFile)) !== FALSE) {
            // Skip header row (first row).
            if ($contentRow[0] !== 'redditKindId') {
                $content = $this->hydrateContentFromCsvRow($contentRow);
                $manager->persist($content);

                $contentsListing[$contentRow[1]] = $content;
            }
        }
        fclose($contentsDataFile);

        // Create Posts.
        $postsDataFile = fopen('/var/www/mra/resources/data-fixtures-source-files/posts.csv', 'r');
        while (($postRow = fgetcsv($postsDataFile)) !== FALSE) {
            // Skip header row (first row).
            if ($postRow[0] !== 'redditKindId') {
                $post = $this->hydratePostFromCsvRow($postRow);
                $contentsListing[$post->getRedditId()]->setPost($post);

                $manager->persist($post);
                $manager->persist($contentsListing[$post->getRedditId()]);
            }
        }
        fclose($postsDataFile);

        $manager->flush();

        // Create Comments.
        $commentsDataFile = fopen('/var/www/mra/resources/data-fixtures-source-files/comments.csv', 'r');
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
        $post->setRedditPostUrl($postRow[8]);
        $post->setCreatedAt(new \DateTimeImmutable());

        $type = $this->typeRepository->findOneBy(['name' => $postRow[1]]);
        $post->setType($type);

        if (!empty($postRow[9])) {
            $authorText = new AuthorText();
            $authorText->setText($postRow[9]);
            $authorText->setTextRawHtml($postRow[10]);
            $authorText->setTextHtml($this->sanitizeHtmlHelper->sanitizeHtml($postRow[10]));
            $postAuthorText = new PostAuthorText();
            $postAuthorText->setAuthorText($authorText);
            $postAuthorText->setCreatedAt(new \DateTimeImmutable());

            $post->addPostAuthorText($postAuthorText);
        }

        return $post;
    }

    private function hydrateContentFromCsvRow(array $contentRow): Content
    {
        $content = new Content();

        $kind = $this->kindRepository->findOneBy(['redditKindId' => $contentRow[0]]);
        $content->setKind($kind);

        return $content;
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
        $comment->setAuthor($commentRow[3]);
        $comment->setScore((int) $commentRow[4]);
        $comment->setRedditId($commentRow[5]);
        $comment->setDepth((int) $commentRow[6]);

        $authorText = new AuthorText();
        $authorText->setText($commentRow[2]);
        $authorText->setTextRawHtml($commentRow[2]);
        $authorText->setTextHtml($commentRow[2]);

        $commentAuthorText = new CommentAuthorText();
        $commentAuthorText->setAuthorText($authorText);
        // @TODO: `createdAt` should be derived from the actual Comment's creation date; not the Post's creation date.
        $commentAuthorText->setCreatedAt($post->getCreatedAt());

        $comment->addCommentAuthorText($commentAuthorText);

        if (!empty($commentRow[1])) {
            $parentComment = $this->commentRepository->findOneBy(['redditId' => $commentRow[1]]);
            $comment->setParentComment($parentComment);
        }

        return $comment;
    }
}
