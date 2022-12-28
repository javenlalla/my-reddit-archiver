<?php

namespace App\Denormalizer;

use App\Entity\Comment;
use App\Entity\Kind;
use App\Entity\Post;
use App\Entity\Content;
use App\Repository\CommentRepository;
use App\Repository\ContentRepository;
use App\Repository\KindRepository;
use App\Repository\PostRepository;
use Exception;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class ContentDenormalizer implements DenormalizerInterface
{
    public function __construct(
        private readonly PostDenormalizer $linkPostDenormalizer,
        private readonly KindRepository $kindRepository,
        private readonly CommentDenormalizer $commentDenormalizer,
        private readonly PostRepository $postRepository,
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

        if ($data['kind'] === Kind::KIND_COMMENT) {
            $kind = $this->kindRepository->getCommentType();
        } else {
            $kind = $this->kindRepository->getLinkType();
        }
        $context['kind'] = $kind;

        if ($data['kind'] === Kind::KIND_LINK) {
            $post = $this->linkPostDenormalizer->denormalize($data['data'], Post::class, null, $context);
        } elseif ($data['kind'] === Kind::KIND_COMMENT) {
            $post = $this->linkPostDenormalizer->denormalize($context['parentPostData']['data']['children'][0]['data'], Post::class, null, $context);
        } else {
            throw new Exception(sprintf('Unexpected Post type %s: %s', $data['kind'], var_export($data, true)));
        }

        $existingPost = $this->postRepository->findOneBy(['redditId' => $post->getRedditId()]);
        if (!empty($existingPost)) {
            $post = $existingPost;
        }

        $comment = null;
        if (!empty($context['commentData'])) {
            // @TODO: Is this additional Kind check needed here?
            $kind = $this->kindRepository->getCommentType();
            $comment = $this->commentDenormalizer->denormalize($post, Comment::class, null, $context);

            $existingComment = $this->commentRepository->findOneBy(['redditId' => $comment->getRedditId()]);
            if (!empty($existingComment)) {
                $comment = $existingComment;
            }
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
}
