<?php
declare(strict_types=1);

namespace App\Denormalizer;

use App\Entity\MoreComment;
use App\Entity\Post;
use App\Repository\MoreCommentRepository;
use App\Trait\CommentUrlTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class MoreCommentDenormalizer implements DenormalizerInterface
{
    use CommentUrlTrait;

    public function __construct(
        private readonly MoreCommentRepository $moreCommentRepository,
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
        return $type === MoreComment::class
            && is_string($data)
            && $context['post'] instanceof Post;
    }

    /**
     * @param  string  $data
     * @param  string  $type
     * @param  string|null  $format
     * @param  array{
     *          post: Post,
     *      }  $context
     *
     * @return MoreComment
     */
    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): MoreComment
    {
        $moreCommentRedditId = $data;
        $post = $context['post'];

        $moreComment = $this->moreCommentRepository->findOneBy(['redditId' => $moreCommentRedditId]);
        if (!empty($moreComment)) {
            return $moreComment;
        }

        $moreComment = new MoreComment();
        $moreComment->setRedditId($moreCommentRedditId);

        $commentUrl = $this->generateRedditUrl($post, $moreCommentRedditId);
        $moreComment->setUrl($commentUrl);

        return $moreComment;
    }
}
