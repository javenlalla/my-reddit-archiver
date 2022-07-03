<?php

namespace App\Controller;

use App\Service\RedditApi;
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
}