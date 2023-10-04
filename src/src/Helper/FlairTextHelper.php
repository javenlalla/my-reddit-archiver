<?php
declare(strict_types=1);

namespace App\Helper;

use App\Entity\Comment;
use App\Entity\FlairText;
use App\Entity\Post;
use App\Entity\Subreddit;
use App\Repository\FlairTextRepository;
use Doctrine\ORM\EntityManagerInterface;

class FlairTextHelper
{
    public function __construct(
        private readonly FlairTextRepository $flairTextRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Generate the Reference ID for this Flair Text using a combination of
     * the text's Subreddit and its text value.
     *
     * @param  string  $flairTextValue
     * @param  Subreddit  $subreddit
     *
     * @return string
     */
    public function generateReferenceId(string $flairTextValue, Subreddit $subreddit): string
    {
        $textHash = md5($subreddit->getRedditId().strtolower($flairTextValue));

        return substr($textHash, 0, 10);
    }

    /**
     * Analyze the Post's Flair Text and persist as necessary.
     *
     * @param  Post  $post
     * @param  array  $postData
     *
     * @return Post
     */
    public function processPostFlairText(Post $post, array $postData): Post
    {
        $flairTextValue = $postData['link_flair_text'] ?? null;
        if (!empty($flairTextValue)) {
            $flairText = $this->initFlairText($flairTextValue, $post->getSubreddit());

            $post->setFlairText($flairText);
        }

        return $post;
    }

    /**
     * Analyze the Comment's Flair Text and persist as necessary.
     *
     * @param  Comment  $comment
     * @param  array  $commentData
     *
     * @return Comment
     */
    public function processCommentFlairText(Comment $comment, array $commentData): Comment
    {
        $flairTextValue = $commentData['author_flair_text'] ?? null;
        if (!empty($flairTextValue)) {
            $flairText = $this->initFlairText($flairTextValue, $comment->getParentPost()->getSubreddit());

            $comment->setFlairText($flairText);
        }

        return $comment;
    }

    /**
     * Attempt to retrieve a Flair Text Entity or create a new one if it does
     * not exist.
     *
     * @param  string  $textValue
     * @param  Subreddit  $subreddit
     *
     * @return FlairText
     */
    private function initFlairText(string $textValue, Subreddit $subreddit): FlairText
    {
        $referenceId = $this->generateReferenceId($textValue, $subreddit);
        $flairText = $this->flairTextRepository->findOneBy(['referenceId' => $referenceId]);

        if (empty($flairText)) {
            $flairText = new FlairText();
            $flairText->setPlainText($textValue);
            $flairText->setDisplayText($textValue);
            $flairText->setReferenceId($referenceId);

            $this->entityManager->persist($flairText);
            $this->entityManager->flush();
        }

        return $flairText;
    }
}
