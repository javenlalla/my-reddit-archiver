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
        $username = $this->params->get('app.reddit.username');
        $apiUser = new ApiUser();
        $apiUser->setUsername($username);
        $manager->persist($apiUser);

        $manager->flush();
    }
}
