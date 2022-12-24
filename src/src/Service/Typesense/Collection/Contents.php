<?php
declare(strict_types=1);

namespace App\Service\Typesense\Collection;

class Contents
{
    /**
     * Define schema array here for this Collection.
     * @var array
     */
    public const SCHEMA = [
        'name'      => 'contents',
        'fields'    => [
            [
                'name'  => 'subreddit',
                'type'  => 'string',
                'facet' => true,
            ],
            [
                'name'  => 'title',
                'type'  => 'string',
            ],
            [
                'name'  => 'postRedditId',
                'type'  => 'string',
            ],
            [
                'name'  => 'postText',
                'type'  => 'string',
            ],
        ],
    ];
}
