<?php

namespace App\Tests\Feature;

use App\Entity\AuthorText;
use App\Entity\CommentAuthorText;
use App\Entity\PostAuthorText;
use App\Repository\CommentRepository;
use App\Repository\PostRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AuthorTextsTest extends KernelTestCase
{
    private CommentRepository $commentRepository;

    private PostRepository $postRepository;

    private EntityManagerInterface $entityManager;

    public function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();
        $this->commentRepository = $container->get(CommentRepository::class);
        $this->postRepository = $container->get(PostRepository::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
    }

    /**
     * Verify that when a Comment has edited or modified revisions, the latest
     * version of the text can be retrieved.
     *
     * @return void
     */
    public function testRetrieveLatestCommentAuthorTextVersion()
    {
        $comment = $this->commentRepository->findOneBy(['redditId' => 'xc0006']);
        $this->assertCount(1, $comment->getCommentAuthorTexts());

        $commentAuthorText = $comment->getCommentAuthorTexts()->get(0);
        $authorText = $commentAuthorText->getAuthorText();
        $this->assertEquals("It’s called Ultra Ego", $authorText->getText());

        // Manually out-date the current Comment Author Text.
        $commentAuthorText->setCreatedAt(DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2015-01-01 00:00:00'));
        $this->entityManager->persist($commentAuthorText);

        $updatedRevisions = [
            [
                'text' => 'Updated revision text 1.',
                'createdAt' => DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2022-01-05 00:00:00'),
            ],
            [
                'text' => 'Updated revision text 2.',
                'createdAt' => DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2022-06-05 00:00:00'),
            ],
            [
                'text' => 'Updated revision text 3.',
                'createdAt' => DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2021-01-05 00:00:00'),
            ],
            [
                'text' => 'Updated revision text 4.',
                'createdAt' => DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2021-04-05 00:00:00'),
            ],
        ];

        // Create new revisions.
        foreach ($updatedRevisions as $updatedRevision) {
            $updatedText = $updatedRevision['text'];
            $authorText = new AuthorText();
            $authorText->setText($updatedText);
            $authorText->setTextRawHtml($updatedText);
            $authorText->setTextHtml($updatedText);
            $commentAuthorText = new CommentAuthorText();
            $commentAuthorText->setAuthorText($authorText);
            $commentAuthorText->setCreatedAt($updatedRevision['createdAt']);

            $comment->addCommentAuthorText($commentAuthorText);
            $this->entityManager->persist($comment);
        }
        $this->entityManager->flush();

        // Re-fetch Comment.
        $comment = $this->commentRepository->findOneBy(['redditId' => 'xc0006']);
        $this->assertCount(5, $comment->getCommentAuthorTexts());

        // Verify the latest Author Text was retrieved.
        $currentCommentAuthorTextRevision = $comment->getLatestCommentAuthorText();
        $authorText = $currentCommentAuthorTextRevision->getAuthorText();
        $this->assertEquals($updatedRevisions[1]['text'], $authorText->getText());
    }

    /**
     * Verify that when a Post has edited or modified revisions, the latest
     * version of the text can be retrieved.
     *
     * @return void
     */
    public function testRetrieveLatestPostAuthorTextVersion()
    {
        $post = $this->postRepository->findOneBy(['redditId' => 'x00002']);

        $postAuthorText = $post->getPostAuthorTexts()->get(0);
        $authorText = $postAuthorText->getAuthorText();

        $this->assertEquals('Just their standard naan disclosure agreement.', $authorText->getText());
        $this->assertEquals('&lt;!-- SC_OFF --&gt;&lt;div class=\"md\"&gt;&lt;p&gt;Just their standard naan disclosure agreement.&lt;/p&gt;\n&lt;/div&gt;&lt;!-- SC_ON --&gt;', $authorText->getTextRawHtml());
        $this->assertEquals('<div class=\"md\"><p>Just their standard naan disclosure agreement.</p></div>', $authorText->getTextHtml());

        // Manually out-date the current Post Author Text.
        $postAuthorText->setCreatedAt(DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2014-01-01 00:00:00'));
        $this->entityManager->persist($postAuthorText);

        $updatedRevisions = [
            [
                'text' => 'Updated Post revision text 1.',
                'createdAt' => DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2022-01-05 00:00:00'),
            ],
            [
                'text' => 'Updated Post revision text 2.',
                'createdAt' => DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2021-01-05 00:00:00'),
            ],
            [
                'text' => 'Updated Post revision text 3.',
                'createdAt' => DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2022-06-05 00:00:00'),
            ],
            [
                'text' => 'Updated Post revision text 4.',
                'createdAt' => DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2021-04-05 00:00:00'),
            ],
        ];

        // Create new revisions.
        foreach ($updatedRevisions as $updatedRevision) {
            $updatedText = $updatedRevision['text'];
            $authorText = new AuthorText();
            $authorText->setText($updatedText);
            $authorText->setTextRawHtml($updatedText);
            $authorText->setTextHtml($updatedText);
            $postAuthorText = new PostAuthorText();
            $postAuthorText->setAuthorText($authorText);
            $postAuthorText->setCreatedAt($updatedRevision['createdAt']);

            $post->addPostAuthorText($postAuthorText);
            $this->entityManager->persist($post);
        }
        $this->entityManager->flush();

        // Re-fetch Post.
        $post = $this->postRepository->findOneBy(['redditId' => 'x00002']);
        $this->assertCount(5, $post->getPostAuthorTexts());

        // Verify the latest Author Text was retrieved.
        $currentPostAuthorTextRevision = $post->getLatestPostAuthorText();
        $authorText = $currentPostAuthorTextRevision->getAuthorText();
        $this->assertEquals($updatedRevisions[2]['text'], $authorText->getText());
    }
}
