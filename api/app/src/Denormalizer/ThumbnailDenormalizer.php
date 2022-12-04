<?php
declare(strict_types=1);

namespace App\Denormalizer;

use App\Entity\Post;
use App\Entity\Thumbnail;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class ThumbnailDenormalizer implements DenormalizerInterface
{
    /**
     * Array of default image references within the `thumbnail` property on
     * Reddit's side to indicate a default image be used.
     */
    public const THUMBNAIL_DEFAULT_IMAGE_NAMES = [
        'image',
        'default',
        'nsfw',
        'self',
    ];

    private const THUMBNAIL_FILENAME_FORMAT = '%s_thumb.jpg';

    /**
     * Analyze the provided Post and denormalize its associated data into a
     * Thumbnail Entity if one is available.
     *
     * @param  Post  $data
     * @param  string  $type
     * @param  string|null  $format
     * @param  array{
     *              sourceUrl: string,
     *          }  $context
     *
     * @return Thumbnail
     */
    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): Thumbnail
    {
        $post = $data;
        $sourceUrl = $context['sourceUrl'];
        $idHash = md5($post->getRedditId() . $sourceUrl);
        $filename = sprintf(self::THUMBNAIL_FILENAME_FORMAT, $idHash);

        $thumbnail = new Thumbnail();
        $thumbnail->setFilename($filename);
        $thumbnail->setSourceUrl($sourceUrl);

        $thumbnail->setDirOne(substr($idHash, 0, 1));
        $thumbnail->setDirTwo(substr($idHash, 1, 2));

        return $thumbnail;
    }

    /**
     * @inheritDoc
     */
    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool
    {
        return $data instanceof Post;
    }
}
