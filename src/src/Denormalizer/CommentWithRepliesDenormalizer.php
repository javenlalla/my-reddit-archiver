<?php
declare(strict_types=1);

namespace App\Denormalizer;

use App\Entity\Comment;
use App\Entity\MoreComment;
use App\Entity\Post;
use App\Repository\CommentRepository;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class CommentWithRepliesDenormalizer implements DenormalizerInterface
{
    public function __construct(
        private readonly CommentDenormalizer $commentDenormalizer,
        private readonly MoreCommentDenormalizer $moreCommentDenormalizer,
        private readonly CommentRepository $commentRepository,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool
    {
        return $data instanceof Post
            && $type === Comment::class
            && !empty($context['commentData']);
    }

    /**
     * Denormalize a Comment and its Replies using the provided Post and Response
     * Data.
     *
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
        $comment = $this->commentDenormalizer->denormalize($data, $type, $format, $context);
        $post = $data;
        $commentData = $context['commentData'];

        if (!empty($commentData['replies'])) {
            $context['parentComment'] = $comment;

            foreach ($commentData['replies']['data']['children'] as $replyCommentData) {
                if ($replyCommentData['kind'] !== 'more') {
                    $context['commentData'] = $replyCommentData['data'];

                    $reply = $this->denormalize($post, $type, $format, $context);
                    $comment->addReply($reply);
                } else if ($replyCommentData['kind'] === 'more' && !empty($replyCommentData['data']['children'])) {

                    foreach ($replyCommentData['data']['children'] as $moreCommentRedditId) {
                        // Only create a new More Comment Entity if it has not
                        // already been synced as a Comment.
                        if (empty($this->commentRepository->findOneBy(['redditId' => $moreCommentRedditId]))) {
                            $moreComment = $this->moreCommentDenormalizer->denormalize($moreCommentRedditId, MoreComment::class, null, ['post' => $post]);
                            $comment->addMoreComment($moreComment);
                        }
                    }
                }
            }
        }

        return $comment;
    }
}
