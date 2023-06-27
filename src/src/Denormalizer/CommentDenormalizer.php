<?php
declare(strict_types=1);

namespace App\Denormalizer;

use App\Entity\AuthorText;
use App\Entity\Award;
use App\Entity\Comment;
use App\Entity\CommentAuthorText;
use App\Entity\CommentAward;
use App\Entity\Post;
use App\Helper\SanitizeHtmlHelper;
use App\Repository\CommentAwardRepository;
use App\Repository\CommentRepository;
use App\Trait\CommentUrlTrait;
use DateTimeImmutable;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class CommentDenormalizer implements DenormalizerInterface
{
    use CommentUrlTrait;

    public function __construct(
        private readonly CommentRepository $commentRepository,
        private readonly CommentAwardRepository $commentAwardRepository,
        private readonly SanitizeHtmlHelper $sanitizeHtmlHelper,
        private readonly AwardDenormalizer $awardDenormalizer,
    ) {
    }

    /**
     * @param  Post  $data
     * @param  string  $type
     * @param  string|null  $format
     * @param  array{
     *              commentData: array
     *          } $context  `commentData` Original Response data for this Comment.
     *
     * @return Comment
     */
    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): Comment
    {
        $post = $data;
        $commentData = $context['commentData'];
        if (isset($commentData['kind']) && isset($commentData['data'])) {
            $commentData = $commentData['data'];
        }

        $redditId = $commentData['id'];
        $comment = $this->commentRepository->findOneBy(['redditId' => $redditId]);
        if (empty($comment)) {
            $comment = $this->initNewComment($post, $commentData, $context);
        }

        return $this->updateComment($comment, $commentData);
    }

    /**
     * @inheritDoc
     */
    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool
    {
        return $data instanceof Post && $type === Comment::class;
    }

    /**
     * Initialize a new Comment Entity and set associated properties that are
     * not expected to change on subsequent syncs.
     *
     * @param  Post  $post
     * @param  array  $commentData
     * @param  array  $context
     *
     * @return Comment
     */
    private function initNewComment(Post $post, array $commentData, array $context): Comment
    {
        $comment = new Comment();
        $comment->setRedditId($commentData['id']);
        $comment->setAuthor($commentData['author']);
        $comment->setParentPost($post);

        $commentUrl = $this->generateRedditUrl($post, $comment->getRedditId());
        $comment->setRedditUrl($commentUrl);

        $depth = $commentData['depth'] ?? 0;
        $comment->setDepth((int) $depth);

        if (isset($context['parentComment']) && $context['parentComment'] instanceof Comment) {
            $comment->setParentComment($context['parentComment']);
        }

        return $comment;
    }

    /**
     * Update the properties of the provided Comment that are expected to or
     * have the potential to change on subsequent syncs.
     *
     * @param  Comment  $comment
     * @param  array  $commentData
     *
     * @return Comment
     */
    private function updateComment(Comment $comment, array $commentData): Comment
    {
        $comment->setScore((int) $commentData['score']);
        $comment->setFlairText($commentData['author_flair_text'] ?? null);

        $text = $commentData['body'];
        $commentAuthorText = $comment->getCommentAuthorTextByText($text);
        if (empty($commentAuthorText)) {
            // To prevent duplicate text records for the same Comment, create
            // a new Comment Author Text only if one does not already exist with
            // target text.
            $authorText = new AuthorText();
            $authorText->setText($text);
            $authorText->setTextRawHtml($commentData['body_html']);
            $authorText->setTextHtml($this->sanitizeHtmlHelper->sanitizeHtml($commentData['body_html']));

            $commentAuthorText = new CommentAuthorText();
            $commentAuthorText->setAuthorText($authorText);

            // Prioritize the updated date over the created date when the
            // Comment has been edited.
            $createdDate = DateTimeImmutable::createFromFormat('U', (string) $commentData['created_utc']);
            if ($commentData['edited'] !== false && is_numeric($commentData['edited'])) {
                $createdDate = DateTimeImmutable::createFromFormat('U', (string) $commentData['edited']);
            }
            $commentAuthorText->setCreatedAt($createdDate);

            $comment->addCommentAuthorText($commentAuthorText);
        }

        if (!empty($commentData['all_awardings'])) {
            foreach ($commentData['all_awardings'] as $awarding) {
                $award = $this->awardDenormalizer->denormalize($awarding, Award::class);
                if ($award instanceof Award) {
                    $commentAward = $this->commentAwardRepository->findOneBy(['comment' => $comment, 'award' => $award]);
                    if (empty($commentAward)) {
                        $commentAward = new CommentAward();
                        $commentAward->setAward($award);
                    }

                    $commentAward->setCount((int) $awarding['count']);
                    $comment->addCommentAward($commentAward);
                }
            }
        }

        return $comment;
    }
}
