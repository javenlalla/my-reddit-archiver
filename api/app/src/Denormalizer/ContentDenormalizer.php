<?php

namespace App\Denormalizer;

use App\Entity\Comment;
use App\Entity\Kind;
use App\Entity\Post;
use App\Entity\Content;
use App\Repository\CommentRepository;
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

        $content = new Content();

        if ($data['kind'] === Kind::KIND_COMMENT) {
            $kind = $this->kindRepository->getCommentType();
        } else {
            $kind = $this->kindRepository->getLinkType();
        }
        $content->setKind($kind);

        $context['content'] = $content;
        if ($data['kind'] === Kind::KIND_LINK) {
            $post = $this->linkPostDenormalizer->denormalize($data['data'], Post::class, null, $context);
        } elseif ($data['kind'] === Kind::KIND_COMMENT) {
            $post = $this->linkPostDenormalizer->denormalize($context['parentPostData']['data']['children'][0]['data'], Post::class, null, $context);
        } else {
            throw new Exception(sprintf('Unexpected Post type %s: %s', $data['kind'], var_export($data, true)));
        }

        $existingPost = $this->postRepository->findOneBy(['redditId' => $post->getRedditId()]);
        if (!empty($existingPost)) {
            $content->setPost($existingPost);
        } else {
            $content->setPost($post);
        }

        if (!empty($context['commentData'])) {
            $kind = $this->kindRepository->getCommentType();
            $content->setKind($kind);;

            $comment = $this->commentDenormalizer->denormalize($content, Comment::class, null, $context);

            $existingComment = $this->commentRepository->findOneBy(['redditId' => $comment->getRedditId()]);
            if (!empty($existingComment)) {
                $content->setComment($existingComment);
            } else {
                $content->setComment($comment);
            }
        }

        return $content;
    }
}
