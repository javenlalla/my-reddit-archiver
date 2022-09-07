<?php

namespace App\DataFixtures;

use App\Entity\ContentType;
use App\Entity\Post;
use App\Entity\Type;
use App\Repository\ContentTypeRepository;
use App\Repository\TypeRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class PostFixtures extends Fixture
{
    public function __construct(private readonly TypeRepository $typeRepository, private readonly ContentTypeRepository $contentTypeRepository)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $this->loadTypes($manager);
        $this->loadContentTypes($manager);
        $manager->flush();

        // Create Posts.
        $posts = [];
        $postsDataFile = fopen('/var/www/mra-api/resources/data-fixtures-source-files/posts.csv', 'r');
        while (($postRow = fgetcsv($postsDataFile)) !== FALSE) {
            // Skip header row (first row).
            if ($postRow[0] !== 'typeId') {
                $post = $this->hydratePostFromCsvRow($postRow);
                $manager->persist($post);

                $posts[] = $post;
            }
        }
        fclose($postsDataFile);

        // Create Comments.

        // Create Media Assets.


        $manager->flush();
    }

    /**
     * Sanitize the provided raw CSV line containing a Post record and return
     * a new, hydrated Post entity.
     *
     * @param  array  $postRow
     *
     * @return Post
     */
    private function hydratePostFromCsvRow(array $postRow): Post
    {
        $post = new Post();

        $type = $this->typeRepository->findOneBy(['redditTypeId' => $postRow[0]]);
        $post->setType($type);

        $contentType = $this->contentTypeRepository->findOneBy(['name' => $postRow[1]]);
        $post->setContentType($contentType);

        $post->setRedditId($postRow[2]);
        $post->setTitle($postRow[3]);
        $post->setScore((int) $postRow[4]);
        $post->setUrl($postRow[5]);
        $post->setAuthor($postRow[6]);
        $post->setSubreddit($postRow[7]);
        $post->setRedditPostId($postRow[8]);
        $post->setRedditPostUrl($postRow[9]);
        $post->setAuthorText(!empty($postRow[10]) ? $postRow[10]: null);
        $post->setAuthorTextHtml(!empty($postRow[11]) ? $postRow[11]: null);
        $post->setAuthorTextRawHtml(!empty($postRow[12]) ? $postRow[12]: null);
        $post->setCreatedAt(new \DateTimeImmutable());

        return $post;
    }

    private function loadTypes(ObjectManager $manager): void
    {
        $types = [
            [
                'redditTypeId' => 't1',
                'name' => 'Comment',
            ],
            [
                'redditTypeId' => 't3',
                'name' => 'Link',
            ],
        ];

        foreach ($types as $type) {
            $typeEntity = new Type();
            $typeEntity->setRedditTypeId($type['redditTypeId']);
            $typeEntity->setName($type['name']);

            $manager->persist($typeEntity);
        }
    }

    private function loadContentTypes(ObjectManager $manager)
    {
        $contentTypes = [
            [
                'name' => 'image',
                'displayName' => 'Image',
            ],
            [
                'name' => 'video',
                'displayName' => 'Video',
            ],
            [
                'name' => 'text',
                'displayName' => 'Text',
            ],
            [
                'name' => 'image_gallery',
                'displayName' => 'Image Gallery',
            ],
            [
                'name' => 'gif',
                'displayName' => 'GIF',
            ],
        ];

        foreach ($contentTypes as $contentType) {
            $contentTypeEntity = new ContentType();
            $contentTypeEntity->setName($contentType['name']);
            $contentTypeEntity->setDisplayName($contentType['displayName']);

            $manager->persist($contentTypeEntity);
        }
    }
}
