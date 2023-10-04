<?php
declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\AuthorText;
use App\Entity\Comment;
use App\Entity\CommentAuthorText;
use App\Entity\Post;
use App\Entity\Content;
use App\Entity\PostAuthorText;
use App\Entity\Subreddit;
use App\Helper\RedditIdHelper;
use App\Helper\SanitizeHtmlHelper;
use App\Repository\CommentRepository;
use App\Repository\KindRepository;
use App\Repository\PostRepository;
use App\Repository\SubredditRepository;
use App\Repository\TypeRepository;
use App\Service\Reddit\Manager;
use App\Service\Typesense\Collection\Contents;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\HttpClient\HttplugClient;
use Symfony\Component\HttpKernel\KernelInterface;
use Typesense\Client;

class PostFixtures extends Fixture implements ContainerAwareInterface
{
    private string $currentEnvironment;

    public function __construct(
        private readonly PostRepository $postRepository,
        private readonly CommentRepository $commentRepository,
        private readonly KindRepository $kindRepository,
        private readonly TypeRepository $typeRepository,
        private readonly SubredditRepository $subredditRepository,
        private readonly SanitizeHtmlHelper $sanitizeHtmlHelper,
        private readonly Manager $manager,
        private readonly RedditIdHelper $redditIdHelper,
        private readonly ContainerBagInterface $params,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    public function load(ObjectManager $manager): void
    {
        $this->setCurrentEnvironment();
        $this->clearGlobalCache();
        $this->clearSearchIndex();

        // Create Subreddits.
        $subredditsDataFile = fopen('/var/www/mra/resources/data-fixtures-source-files/subreddits.csv', 'r');
        while (($subredditRow = fgetcsv($subredditsDataFile)) !== FALSE) {
            // Skip header row (first row).
            if ($subredditRow[0] !== 'redditId') {
                $subreddit = new Subreddit();
                $subreddit->setRedditId($subredditRow[0]);
                $subreddit->setName($subredditRow[1]);
                $subreddit->setCreatedAt(new DateTimeImmutable());

                $manager->persist($subreddit);
            }
        }
        fclose($subredditsDataFile);
        $manager->flush();

        // Create Contents.
        $contentsListing = [];
        $contentsCommentsListing = [];
        $contentsDataFile = fopen('/var/www/mra/resources/data-fixtures-source-files/contents.csv', 'r');
        while (($contentRow = fgetcsv($contentsDataFile)) !== FALSE) {
            // Skip header row (first row).
            if ($contentRow[0] !== 'redditKindId') {
                $content = $this->hydrateContentFromCsvRow($contentRow);
                $manager->persist($content);

                $contentsListing[$contentRow[1]] = $content;
                if ($contentRow[0] === 't1') {
                    $contentsCommentsListing[$contentRow[2]] = $content;
                }
            }
        }
        fclose($contentsDataFile);

        // Create Posts.
        $postsDataFile = fopen('/var/www/mra/resources/data-fixtures-source-files/posts.csv', 'r');
        while (($postRow = fgetcsv($postsDataFile)) !== FALSE) {
            // Skip header row (first row).
            if ($postRow[0] !== 'redditKindId') {
                $post = $this->hydratePostFromCsvRow($postRow);
                $contentsListing[$post->getRedditId()]->setPost($post);

                $manager->persist($post);
                $manager->persist($contentsListing[$post->getRedditId()]);
            }
        }
        fclose($postsDataFile);

        $manager->flush();

        // Create Comments.
        $commentsDataFile = fopen('/var/www/mra/resources/data-fixtures-source-files/comments.csv', 'r');
        while (($commentRow = fgetcsv($commentsDataFile)) !== FALSE) {
            // Skip header row (first row).
            if ($commentRow[0] !== 'redditPostId') {
                $comment = $this->hydrateCommentFromCsvRow($commentRow);

                // If Comment is directly associated to the Content, persist
                // the relationship.
                if (!empty($contentsCommentsListing[$comment->getRedditId()])) {
                    $contentsCommentsListing[$comment->getRedditId()]->setComment($comment);
                    $manager->persist($contentsCommentsListing[$comment->getRedditId()]);
                }

                $manager->persist($comment);

                // Persist Comments immediately in order for fetching Parent Comments during hydration.
                $manager->flush();
            }
        }
        fclose($commentsDataFile);

        // Pre-populating test Contents causes conflicts and race conditions
        // when running unit tests. Thus, do not populate in the `test`
        // environment.
        if ($this->currentEnvironment === 'test') {
            $this->loadTestData();
        } else {
            $this->syncTestContents();
        }

        $this->clearGlobalCache();
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

        $post->setRedditId($postRow[2]);
        $post->setTitle($postRow[3]);
        $post->setScore((int) $postRow[4]);
        $post->setUrl($postRow[5]);
        $post->setAuthor($postRow[6]);
        $post->setRedditPostUrl($postRow[8]);
        // $post->setFlairText($postRow[11] ?? null);

        $type = $this->typeRepository->findOneBy(['name' => $postRow[1]]);
        $post->setType($type);

        $subreddit = $this->subredditRepository->findOneBy(['name' => $postRow[7]]);
        $post->setSubreddit($subreddit);

        if (!empty($postRow[9])) {
            $authorText = new AuthorText();
            $authorText->setText($postRow[9]);
            $authorText->setTextRawHtml($postRow[10]);
            $authorText->setTextHtml($this->sanitizeHtmlHelper->sanitizeHtml($postRow[10]));
            $postAuthorText = new PostAuthorText();
            $postAuthorText->setAuthorText($authorText);
            $postAuthorText->setCreatedAt(new DateTimeImmutable());

            $post->addPostAuthorText($postAuthorText);
        }

        if (!empty($postRow[12])) {
            $post->setCreatedAt(DateTimeImmutable::createFromFormat('Y-m-d H:i:s',$postRow[12]));
        } else {
            $post->setCreatedAt(new DateTimeImmutable());
        }

        return $post;
    }

    private function hydrateContentFromCsvRow(array $contentRow): Content
    {
        $content = new Content();

        $redditId = $contentRow[1];
        if (!empty($contentRow[2])) {
            $redditId = $contentRow[2];
        }

        $kind = $this->kindRepository->findOneBy(['redditKindId' => $contentRow[0]]);
        $content->setKind($kind);

        $fullRedditId = $this->redditIdHelper->formatRedditId($contentRow[0], $redditId);
        $content->setFullRedditId($fullRedditId);

        return $content;
    }

    /**
     * Sanitize the provided raw CSV line containing a Comment record and return
     * a new, hydrated Comment entity.
     *
     * Assign the Post Entity and parent Comment Entity as necessary.
     *
     * @param  array  $commentRow
     *
     * @return Comment
     */
    private function hydrateCommentFromCsvRow(array $commentRow): Comment
    {
        $post = $this->postRepository->findOneBy(['redditId' => $commentRow['0']]);

        $comment = new Comment();
        $comment->setParentPost($post);
        $comment->setAuthor($commentRow[3]);
        $comment->setScore((int) $commentRow[4]);
        $comment->setRedditId($commentRow[5]);
        $comment->setDepth((int) $commentRow[6]);
        $comment->setJsonData('');
        $comment->setRedditUrl(sprintf(Comment::REDDIT_URL_FORMAT,
            $post->getSubreddit()->getName(),
            $post->getRedditId(),
            $commentRow[5]
        ));

        $authorText = new AuthorText();
        $authorText->setText($commentRow[2]);
        $authorText->setTextRawHtml($commentRow[2]);
        $authorText->setTextHtml($commentRow[2]);

        $commentAuthorText = new CommentAuthorText();
        $commentAuthorText->setAuthorText($authorText);
        // @TODO: `createdAt` should be derived from the actual Comment's creation date; not the Post's creation date.
        $commentAuthorText->setCreatedAt($post->getCreatedAt());

        $comment->addCommentAuthorText($commentAuthorText);

        if (!empty($commentRow[1])) {
            $parentComment = $this->commentRepository->findOneBy(['redditId' => $commentRow[1]]);
            $comment->setParentComment($parentComment);
        }

        return $comment;
    }

    /**
     * Execute actual syncs against a set of Posts/Comments to populate test
     * data based on real Contents.
     *
     * @return void
     */
    private function syncTestContents()
    {
        $redditIds = [
            't3_vepbt0',
            't3_won0ky',
            't3_uk7ctt',
            't3_vdmg2f',
            't3_v443nh',
            't3_tl8qic',
            't3_wfylnl',
            't3_v27nr7',
            't1_j84z4vm',
            't3_8ung3q',
            't3_cs8urd',
            't3_utsmkw',
            't3_8vkdsq',
            't1_ip914eh',
        ];

        foreach ($redditIds as $redditId) {
            $this->manager->syncContentFromApiByFullRedditId($redditId);
        }
    }

    /**
     * Set the Environment (dev, test, etc.) the Fixture is currently
     * being executed in.
     *
     * @return void
     */
    private function setCurrentEnvironment()
    {
        /** @var KernelInterface $kernel */
        $kernel = $this->container->get('kernel');

        $this->currentEnvironment = $kernel->getEnvironment();
    }

    /**
     * Clear the Search index.
     *
     * @return void
     */
    private function clearSearchIndex()
    {
        $apiKey = $this->params->get('app.typesense.api_key');

        $client = new Client(
            [
                'api_key' => $apiKey,
                'nodes' => [
                    [
                        'host' => 'localhost',
                        'port' => '8108',
                        'protocol' => 'http',
                    ],
                ],
                'client' => new HttplugClient(),
            ]
        );

        $client->collections['contents']->delete();
        $client->collections->create(Contents::SCHEMA);
    }

    /**
     * Run the Symfony global cache clearer.
     *
     * @return void
     */
    private function clearGlobalCache()
    {
        exec("php /var/www/mra/bin/console cache:pool:clear cache.global_clearer");
    }

    /**
     * Load the data needed specifically for the `test` environment.
     *
     * @return void
     */
    private function loadTestData(): void
    {
        $sql = file_get_contents('/var/www/mra/resources/data-fixtures-source-files/test-item-jsons.sql');

        $stmt = $this->entityManager->getConnection()->prepare($sql);
        $stmt->executeStatement();
    }
}
