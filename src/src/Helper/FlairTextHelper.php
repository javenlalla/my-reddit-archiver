<?php
declare(strict_types=1);

namespace App\Helper;

use App\Entity\Subreddit;

class FlairTextHelper
{
    /**
     * Generate the Reference ID for this Flair Text using a combination of
     * the text's Subreddit and its text value.
     *
     * @param  string  $flairTextValue
     * @param  Subreddit  $subreddit
     *
     * @return string
     */
    public function generateReferenceId(string $flairTextValue, Subreddit $subreddit): string
    {
        $textHash = md5($subreddit->getRedditId().strtolower($flairTextValue));

        return substr($textHash, 0, 10);
    }
}
