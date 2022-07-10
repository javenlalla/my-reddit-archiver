<?php

namespace App\DataFixtures;

use App\Entity\ApiUser;
use App\Entity\ContentType;
use App\Entity\Type;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

class ApiUserFixture extends Fixture
{
    public function __construct(private readonly ContainerBagInterface $params)
    {}

    public function load(ObjectManager $manager): void
    {
        $this->loadTypes($manager);
        $this->loadContentTypes($manager);

        $username = $this->params->get('app.reddit.username');
        $apiUser = new ApiUser();
        $apiUser->setUsername($username);
        $manager->persist($apiUser);

        $manager->flush();
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
        ];

        foreach ($contentTypes as $contentType) {
            $contentTypeEntity = new ContentType();
            $contentTypeEntity->setName($contentType['name']);
            $contentTypeEntity->setDisplayName($contentType['displayName']);

            $manager->persist($contentTypeEntity);
        }
    }
}
