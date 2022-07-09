<?php

namespace App\Controller;

use App\Service\Reddit;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/auth', name: 'auth_')]
class RedditApiController extends AbstractController
{
    #[Route('/access-token', name: 'access_token')]
    public function accessToken(RedditApi $api)
    {
        return $this->json(['test'=>'boo']);
    }

    #[Route('/saved-posts', name: 'saved_posts')]
    public function getSavedPosts(RedditApi $api)
    {
        return $this->json($api->getSavedPosts());
    }

    #[Route('/comments', name: 'comments')]
    public function getComments(RedditApi $api)
    {
        return $this->json($api->getPostComments());
    }
}