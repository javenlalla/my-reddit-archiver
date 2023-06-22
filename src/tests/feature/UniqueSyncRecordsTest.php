<?php
declare(strict_types=1);

namespace App\Tests\feature;

use App\Repository\CommentRepository;
use App\Service\Reddit\Api\Context;
use App\Service\Reddit\Manager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class UniqueSyncRecordsTest extends KernelTestCase
{
    private Manager $manager;

    public function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();
        $this->manager = $container->get(Manager::class);
        $this->commentRepository = $container->get(CommentRepository::class);
    }

    /**
     * Ensure that if Manager::syncContentFromJsonUrl() is called more than once
     * for the same Kind and Post Link, the same Content record is retrieved
     * instead of inserting a new Content record for each execution.
     *
     * @return void
     */
    public function testUniqueContentPost()
    {
        $context = new Context('UniqueSyncRecordsTest:testUniqueContentPost');

        $redditId = 't3_vepbt0';
        $firstContent = $this->manager->syncContentFromApiByFullRedditId($context, $redditId);

        // Sync again.
        $secondContent = $this->manager->syncContentFromApiByFullRedditId($context, $redditId);

        // Verify the second Content return is the same as the first Content.
        // I.E: a second Content record was not created.
        $this->assertEquals($firstContent->getId(), $secondContent->getId());
    }

    /**
     * Ensure that if Manager::syncContentFromJsonUrl() is called more than once
     * for the same Text Post, duplicate Post Author Texts are *not* created.
     *
     * @return void
     */
    public function testUniqueContentTextPost()
    {
        $context = new Context('UniqueSyncRecordsTest:testUniqueContentTextPost');

        $redditId = 't3_uk7ctt';
        $firstContent = $this->manager->syncContentFromApiByFullRedditId($context, $redditId);

        // Sync again.
        $secondContent = $this->manager->syncContentFromApiByFullRedditId($context, $redditId);

        // Verify the second Content return is the same as the first Content.
        // I.E: a second Content record was not created.
        $this->assertEquals($firstContent->getId(), $secondContent->getId());

        // Verify the Post only has one Post Author Text.
        $this->assertCount(1, $secondContent->getPost()->getPostAuthorTexts());
    }

    /**
     * Ensure that if Manager::syncContentFromJsonUrl() is called more than once
     * for the same Kind and Comment Link, the same Content record is retrieved
     * instead of inserting a new Content record for each execution.
     *
     * Also verify no duplicate Comment Author Texts are created and associated
     * to the target Comment.
     *
     * @return void
     */
    public function testUniqueContentComment()
    {
        $context = new Context('UniqueSyncRecordsTest:testUniqueContentComment');

        $redditId = 't1_ia1smh6';
        $firstContent = $this->manager->syncContentFromApiByFullRedditId($context, $redditId);

        // Sync again.
        $secondContent = $this->manager->syncContentFromApiByFullRedditId($context, $redditId);

        // Verify the second Content return is the same as the first Content.
        // I.E: a second Content record was not created.
        $this->assertEquals($firstContent->getId(), $secondContent->getId());

        // Verify the Comment only has one Comment Author Text.
        $this->assertCount(1, $secondContent->getComment()->getCommentAuthorTexts());
    }
}
