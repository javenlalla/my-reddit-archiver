<?php
declare(strict_types=1);

namespace App\Service\Search;

use App\Entity\Comment;
use App\Entity\Content;
use App\Entity\FlairText;
use App\Entity\Post;
use App\Entity\PostAuthorText;
use App\Entity\SearchContent;
use App\Repository\SearchContentRepository;
use Doctrine\ORM\EntityManagerInterface;

class Indexer
{
    public function __construct(
        private readonly SearchContentRepository $searchContentRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Create a Search Entity based on the provided Content.
     *
     * @param  Content  $content
     *
     * @return void
     */
    public function indexContent(Content $content): void
    {
        $searchContent = $this->searchContentRepository->findOneBy(['content' => $content]);
        if (empty($searchContent)) {
            $searchContent = new SearchContent();
            $searchContent->setContent($content);
        }

        $post = $content->getPost();
        $comment = $content->getComment();

        $searchContent->setTitle($post->getTitle());
        $searchContent->setSubreddit($post->getSubreddit()->getName());
        $searchContent->setCreatedAt($post->getCreatedAt());
        if ($comment instanceof Comment) {
            $searchContent->setCreatedAt($comment->getLatestCommentAuthorText()->getCreatedAt());
        }

        $flairText = $this->getSearchFlairTextFromContent($post, $comment);
        if (!empty($flairText)) {
            $searchContent->setFlairText($flairText);
        }

        $contentText = $this->getSearchTextFromContent($post, $comment);
        $searchContent->setContentText($contentText);

        $this->entityManager->persist($searchContent);
        $this->entityManager->flush();
    }

    /**
     * Check the provided Post or Comment Flair Text and return
     * if any is found, prioritizing the Comment Flair Text first.
     *
     * @param  Post  $post
     * @param  Comment|null  $comment
     *
     * @return string|null
     */
    private function getSearchFlairTextFromContent(Post $post, ?Comment $comment): ?string
    {
        $flairText = null;
        if ($comment instanceof Comment && !empty($comment->getFlairText())) {
            $flairText = $comment->getFlairText();
        }

        if (!empty($post->getFlairText())) {
            $flairText = $post->getFlairText();
        }

        if ($flairText instanceof FlairText) {
            return $flairText->getPlainText();
        }

        return null;
    }

    /**
     * Analyze the provided Post and Comment and return the target text that
     * should be used for searching, prioritizing the Comment text.
     *
     * Fallback to Post Title if no other text found.
     *
     * @param  Post|null  $post
     * @param  Comment|null  $comment
     *
     * @return string
     */
    private function getSearchTextFromContent(?Post $post, ?Comment $comment): string
    {
        if ($comment instanceof Comment) {
            $latestCommentAuthorText = $comment->getLatestCommentAuthorText();
            return $latestCommentAuthorText->getAuthorText()->getText();
        }

        $latestPostAuthorText = $post->getLatestPostAuthorText();
        if ($latestPostAuthorText instanceof PostAuthorText) {
            return $latestPostAuthorText->getAuthorText()->getText();
        }

        return $post->getTitle();
    }
}
