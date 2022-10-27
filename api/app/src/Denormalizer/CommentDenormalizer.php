<?php

namespace App\Denormalizer;

use App\Entity\AuthorText;
use App\Entity\Comment;
use App\Entity\CommentAuthorText;
use App\Entity\Content;
use App\Entity\Post;
use App\Helper\SanitizeHtmlHelper;
use DateTimeImmutable;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class CommentDenormalizer implements DenormalizerInterface
{
    public function __construct(
        private readonly SanitizeHtmlHelper $sanitizeHtmlHelper
    ) {
    }

    /**
     * @param  Content  $data
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
        $content = $data;
        $post = $content->getPost();
        $commentData = $context['commentData'];

        $comment = new Comment();
        $comment->setRedditId($commentData['id']);
        $comment->setScore((int) $commentData['score']);
        $comment->setAuthor($commentData['author']);
        $comment->setParentPost($post);

        $depth = $commentData['depth'] ?? 0;
        $comment->setDepth((int) $depth);

        $authorText = new AuthorText();
        $authorText->setText($commentData['body']);
        $authorText->setTextRawHtml($commentData['body_html']);
        $authorText->setTextHtml($this->sanitizeHtmlHelper->sanitizeHtml($commentData['body_html']));

        $commentAuthorText = new CommentAuthorText();
        $commentAuthorText->setAuthorText($authorText);
        $commentAuthorText->setCreatedAt(DateTimeImmutable::createFromFormat('U', $commentData['created_utc']));

        $comment->addAuthorText($commentAuthorText);

        if (isset($context['parentComment']) && $context['parentComment'] instanceof Comment) {
            $comment->setParentComment($context['parentComment']);
        }

        return $comment;
    }

    /**
     * @inheritDoc
     */
    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool
    {
        return $data instanceof Post && $type === Comment::class;
    }
}
