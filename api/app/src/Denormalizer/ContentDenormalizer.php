<?php

namespace App\Denormalizer;

use App\Denormalizer\Post\CommentPostDenormalizer;
use App\Denormalizer\Post\LinkPostDenormalizer;
use App\Entity\Kind;
use App\Entity\Post;
use App\Entity\Content;
use App\Helper\ContentTypeHelper;
use App\Repository\KindRepository;
use Exception;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class ContentDenormalizer implements DenormalizerInterface
{
    public function __construct(
        private readonly LinkPostDenormalizer $linkPostDenormalizer,
        private readonly CommentPostDenormalizer $commentPostDenormalizer,
        private readonly KindRepository $kindRepository,
        private readonly ContentTypeHelper $contentTypeHelper,
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

    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): Content
    {
        if ($data['kind'] === 'Listing') {
            $data = $data['data']['children'][0];
        }

        $content = new Content();

        $kind = $this->kindRepository->getLinkType();
        $content->setKind($kind);;

        $contentType = $this->contentTypeHelper->getContentTypeFromPostData($data['data']);
        $content->setContentType($contentType);

        $context['content'] = $content;

        if ($data['kind'] === Kind::TYPE_LINK) {
            $post = $this->linkPostDenormalizer->denormalize($data['data'], Post::class, null, $context);
        } elseif ($data['kind'] === Kind::TYPE_COMMENT) {
            $context['parentPost'] = $context['parentPostData']['data']['children'][0]['data'];

            $post = $this->commentPostDenormalizer->denormalize($data['data'], Post::class, null, $context);
        } else {
            throw new Exception(sprintf('Unexpected Post type %s: %s', $data['kind'], var_export($data, true)));
        }

        $content->setPost($post);

        return $content;

    }
}
