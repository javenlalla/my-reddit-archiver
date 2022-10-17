<?php

namespace App\Denormalizer;

use App\Denormalizer\Post\CommentPostDenormalizer;
use App\Denormalizer\Post\LinkPostDenormalizer;
use App\Entity\Post;
use App\Entity\Content;
use App\Entity\Type;
use App\Helper\ContentTypeHelper;
use App\Repository\TypeRepository;
use Exception;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class ContentDenormalizer implements DenormalizerInterface
{
    public function __construct(
        private readonly LinkPostDenormalizer $linkPostDenormalizer,
        private readonly CommentPostDenormalizer $commentPostDenormalizer,
        private readonly TypeRepository $typeRepository,
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

        $type = $this->typeRepository->getLinkType();
        $content->setType($type);;

        $contentType = $this->contentTypeHelper->getContentTypeFromPostData($data['data']);
        $content->setContentType($contentType);

        if ($data['kind'] === Type::TYPE_LINK) {
            $post = $this->linkPostDenormalizer->denormalize($data['data'], Post::class, null, ['content' => $content]);
        } elseif ($data['kind'] === Type::TYPE_COMMENT) {
            $post = $this->commentPostDenormalizer->denormalize($data['data'], Post::class, null, ['parentPost' => $context['parentPostData']['data']['children'][0]['data']]);
        } else {
            throw new Exception(sprintf('Unexpected Post type %s: %s', $data['kind'], var_export($data, true)));
        }

        $content->setPost($post);

        return $content;

    }
}
