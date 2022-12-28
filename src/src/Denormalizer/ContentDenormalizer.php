<?php
declare(strict_types=1);

namespace App\Denormalizer;

use App\Entity\Comment;
use App\Entity\Kind;
use App\Entity\Post;
use App\Entity\Content;
use App\Repository\CommentRepository;
use App\Repository\ContentRepository;
use App\Repository\KindRepository;
use Exception;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class ContentDenormalizer implements DenormalizerInterface
{
    public function __construct(
        private readonly PostDenormalizer $linkPostDenormalizer,
        private readonly KindRepository $kindRepository,
        private readonly CommentDenormalizer $commentDenormalizer,
        private readonly CommentRepository $commentRepository,
        private readonly ContentRepository $contentRepository,
    ) {
    }

    /**
     * @param  array  $data
     * @param  string  $type
     * @param  string|null  $format
     * @param  array|null  $context
     *
     * @return bool
     */
    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = null): bool
    {
        return is_array($data) && $type === Content::class;
    }

    /**
     * @param  array  $data
     * @param  string  $type
     * @param  string|null  $format
     * @param  array{
     *          parentPostData: array,
     *          commentData: array,
     *          }  $context
     *
     * @return Content
     * @throws Exception
     */
    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): Content
    {
        if ($data['kind'] === 'Listing') {
            $data = $data['data']['children'][0];
        }

        $kindRedditId = $data['kind'];
        $kind = $this->getKindFromKindId($kindRedditId);
        $context['kind'] = $kind;

        $post = $this->getDenormalizedPost($kindRedditId, $data, $context);

        $comment = $this->getDenormalizedComment($post, $context);
        if ($comment instanceof Comment) {
            // This is currently needed to force the correct Kind on the Content
            // for Comment Contents.
            $kind = $this->kindRepository->getCommentType();
        }

        return $this->getNewOrExistingContent($kind, $post, $comment);
    }

    /**
     * Based on the provided Post and Comment Entities, initialize a new Content
     * Entity or retrieve an existing Content Entity and return.
     *
     * The look-up is performed against the Comment Entity first, then against
     * the Post Entity.
     *
     * @param  Kind  $kind
     * @param  Post  $post
     * @param  Comment|null  $comment
     *
     * @return Content
     */
    private function getNewOrExistingContent(Kind $kind, Post $post, ?Comment $comment): Content
    {
        if ($comment instanceof Comment) {
            $content = $this->contentRepository->findOneBy(['comment' => $comment]);
        } else {
            $content = $this->contentRepository->findOneBy(['post' => $post]);
        }

        if (empty($content)) {
            $content = new Content();
        }

        $content->setKind($kind);
        $content->setPost($post);
        if ($comment instanceof Comment) {
            $content->setComment($comment);
        }

        return $content;
    }

    /**
     * Retrieve the Kind Entity associated to the provided Kind Reddit ID.
     *
     * @param  string  $kindRedditId Kind Reddit ID `t1` or `t3`.
     *
     * @return Kind
     */
    private function getKindFromKindId(string $kindRedditId): Kind
    {
        if ($kindRedditId === Kind::KIND_COMMENT) {
            $kind = $this->kindRepository->getCommentType();
        } else {
            $kind = $this->kindRepository->getLinkType();
        }

        return $kind;
    }

    /**
     * Retrieve the denormalized Post associated to the provided Response Data
     * for this Content.
     *
     * @param  string  $kindRedditId
     * @param  array  $responseData
     * @param  array  $context
     *
     * @return Post
     * @throws Exception
     */
    private function getDenormalizedPost(string $kindRedditId, array $responseData, array $context): Post
    {
        if ($kindRedditId === Kind::KIND_LINK) {
            $post = $this->linkPostDenormalizer->denormalize($responseData['data'], Post::class, null, $context);
        } elseif ($kindRedditId === Kind::KIND_COMMENT) {
            $post = $this->linkPostDenormalizer->denormalize($context['parentPostData']['data']['children'][0]['data'], Post::class, null, $context);
        } else {
            throw new Exception(sprintf('Unexpected Post type %s: %s', $kindRedditId, var_export($responseData, true)));
        }

        return $post;
    }

    /**
     * Retrieve the denormalized Comment, if any, associated to the provided
     * `context`.
     *
     * @param  Post  $post
     * @param  array  $context
     *
     * @return Comment|null
     */
    private function getDenormalizedComment(Post $post, array $context): ?Comment
    {
        $comment = null;
        if (!empty($context['commentData'])) {
            $comment = $this->commentDenormalizer->denormalize($post, Comment::class, null, $context);

            $existingComment = $this->commentRepository->findOneBy(['redditId' => $comment->getRedditId()]);
            if (!empty($existingComment)) {
                $comment = $existingComment;
            }
        }

        return $comment;
    }
}
