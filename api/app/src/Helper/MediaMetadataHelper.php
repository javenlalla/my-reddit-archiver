<?php

namespace App\Helper;

use Exception;

class MediaMetadataHelper
{
    /**
     * Read the 'm' property of the provided Media Metadata and return the
     * expected extension.
     *
     * @param  array{
     *          m: string,
     *     } $mediaMetadata
     *
     * @return string
     * @throws Exception
     */
    public function extractExtensionFromMediaMetadata(array $mediaMetadata): string
    {
        switch ($mediaMetadata['m']) {
            case 'image/jpg':
                return 'jpg';

            case 'image/jpeg':
                return 'jpeg';

            case 'image/png':
                return 'png';

            case 'image/webp':
                return 'webp';

            case 'image/gif':
                return 'mp4';
        }

        throw new Exception(sprintf('Unexpected media type in Media Metadata: %s', $mediaMetadata['m']));
    }
}
