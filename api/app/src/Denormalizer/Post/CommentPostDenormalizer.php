<?php

namespace App\Denormalizer\Post;

use App\Entity\Content;
use App\Entity\Post;
use App\Helper\SanitizeHtmlHelper;
use App\Repository\ContentTypeRepository;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class CommentPostDenormalizer implements DenormalizerInterface
{
    public function __construct(
        private readonly ContentTypeRepository $contentTypeRepository,
        private readonly SanitizeHtmlHelper $sanitizeHtmlHelper
    ) {
    }

    /**
     * @param  array  $data Original Comment Response data.
     * @param  string  $type
     * @param  string|null  $format
     * @param  array{
     *              parentPost: array,
     *              content: Content,
     *          } $context  `parentPost`: Post Response data.
     *
     * @return Post
     */
    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): Post
    {
        $commentData = $data;
        $postData = $context['parentPost'];

        $post = new Post();
        $post->setRedditId($commentData['id']);
        $post->setTitle($postData['title']);
        $post->setScore((int)$commentData['score']);
        $post->setAuthor($commentData['author']);
        $post->setSubreddit($commentData['subreddit']);
        $post->setUrl($postData['url']);
        $post->setCreatedAt(\DateTimeImmutable::createFromFormat('U', $commentData['created_utc']));

        $post->setAuthorText($commentData['body']);
        $post->setAuthorTextRawHtml($commentData['body_html']);
        $post->setAuthorTextHtml($this->sanitizeHtmlHelper->sanitizeHtml($commentData['body_html']));
        $post->setRedditPostId($postData['id']);
        $post->setRedditPostUrl('https://reddit.com' . $postData['permalink']);

        return $post;
    }

    /**
     * @inheritDoc
     */
    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool
    {
        return is_array($data) && $type === Post::class && isset($context['parentPost']);
    }
}
