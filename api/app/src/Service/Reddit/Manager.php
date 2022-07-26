<?php

namespace App\Service\Reddit;

use App\Entity\Post;
use App\Entity\Type;
use App\Repository\CommentRepository;
use App\Repository\PostRepository;
use Exception;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class Manager
{
    public function __construct(
        private readonly Api $api,
        private readonly PostRepository $postRepository,
        private readonly CommentRepository $commentRepository,
        private readonly Hydrator $hydrator
    ) {
    }

    /**
     * Retrieve a Post from the API hydrated with the response data.
     *
     * @param  string  $type
     * @param  string  $redditId
     *
     * @return Post
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws Exception
     */
    public function getPostFromApiByRedditId(string $type, string $redditId): Post
    {
        $response = $this->api->getPostByRedditId($type, $redditId);
        $parentPostResponse = [];

        if ($type === Type::TYPE_COMMENT) {
            $parentPostResponse = $this->api->getPostByFullRedditId($response['data']['children'][0]['data']['parent_id']);
        }

        return $this->hydrator->hydratePostFromResponse($response, $parentPostResponse);
    }

    public function getPostByRedditId(string $redditId): ?Post
    {
        return $this->postRepository->findOneBy(['redditId' => $redditId]);
    }

    public function savePost(Post $post)
    {
        $existingPost = $this->getPostByRedditId($post->getRedditId());

        if ($existingPost instanceof Post) {
            return;
        }

        $this->postRepository->save($post);
    }

    public function getCommentsFromApiByPost(Post $post)
    {
        $commentsRawResponse = $this->api->getPostCommentsByRedditId($post->getRedditId());
        $commentsRawData = $commentsRawResponse[1]['data']['children'];

        $comments = $this->hydrateComments($post, $commentsRawData);

        $this->commentRepository->saveComments($comments);

        return $comments;
    }

    private function hydrateComments(Post $post, array $commentsRawData, \App\Entity\Comment $parentComment = null)
    {
        $comments = [];
        foreach ($commentsRawData as $commentRawData) {
            $commentData = $commentRawData;
            if (!empty($commentRawData['data'])) {
                $commentData = $commentRawData['data'];
            }

            $comment = new \App\Entity\Comment();
            $comment->setRedditId($commentData['id']);
            $comment->setScore((int) $commentData['score']);
            $comment->setText($commentData['body']);
            $comment->setAuthor($commentData['author']);
            $comment->setParentPostId($post->getRedditId());
            if ($parentComment instanceof \App\Entity\Comment) {
                $comment->setParentComment($parentComment);
            }

            if (!empty($commentData['replies'])) {
                $replies = $this->hydrateComments($post, $commentData['replies']['data']['children'], $comment);

                foreach ($replies as $reply) {
                    $comment->addReply($reply);
                }
                // $replies = new Comments($commentData['replies']);
                // $this->replies = $replies->getComments();
            }

            $comments[] = $comment;

            // foreach ($targetChildren as $parentComment) {
            //     $currentCommentData = $parentComment['data'];
            //
            //     $comment = new \App\Entity\Comment();
            //     $comment->setRedditId($currentCommentData['id']);
            //     $comment->setScore((int) $currentCommentData['score']);
            //     $comment->setText($currentCommentData['body']);
            //
            //     $comments[] = $comment;
            //     // $comments[] = new Comment($parentComment['data']);
            //     //
            //     // $this->id = $this->rawCommentData['id'];
            //     // $this->score = (int) $this->rawCommentData['score'];
            //     // $this->text = $this->rawCommentData['body'];
            //     // if (!empty($this->rawCommentData['replies'])) {
            //     //     $replies = new Comments($this->rawCommentData['replies']);
            //     //     $this->replies = $replies->getComments();
            //     // }
            // }
        }

        return $comments;
    }

    /**
     * Retrieve a Comment locally by its Reddit ID.
     *
     * @param  string  $redditId
     *
     * @return \App\Entity\Comment|null
     */
    public function getCommentByRedditId(string $redditId): ?\App\Entity\Comment
    {
        return $this->commentRepository->findOneBy(['redditId' => $redditId]);
    }

    public function saveComments(){}
}