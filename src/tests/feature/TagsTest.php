<?php

namespace App\Tests\feature;

use App\Entity\Content;
use App\Entity\Tag;
use App\Repository\ContentRepository;
use App\Repository\PostRepository;
use App\Repository\TagRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @group ci-tests
 * @group ci-tests-small
 */
class TagsTest extends KernelTestCase
{
    private TagRepository $tagRepository;

    private PostRepository $postRepository;

    private ContentRepository $contentRepository;

    public function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();
        $this->tagRepository = $container->get(TagRepository::class);
        $this->postRepository = $container->get(PostRepository::class);
        $this->contentRepository = $container->get(ContentRepository::class);
    }

    /**
     * Verify Tags can be associated to Contents and Contents can be retrieved
     * via their Tags.
     *
     * @return void
     */
    public function testAddTagsToContents(): void
    {
        $post = $this->postRepository->findOneBy(['redditId' => 'x00001']);
        $this->assertEquals('[OC] All of Vegeta transformation', $post->getTitle());

        $tagOne = new Tag();
        $tagOne->setName('TestTagOne');
        $tagOne->setLabelColor(substr(md5(microtime()),rand(0,26), 6));
        $tagOne->setLabelFontColor(substr(md5(microtime()),rand(0,26), 6));
        $this->tagRepository->add($tagOne, true);

        $tagTwo = new Tag();
        $tagTwo->setName('Testing Tag 2');
        $tagTwo->setLabelColor(substr(md5(microtime()),rand(0,26), 6));
        $tagTwo->setLabelFontColor(substr(md5(microtime()),rand(0,26), 6));
        $this->tagRepository->add($tagTwo, true);

        $content = $post->getContent();
        $content->addTag($tagOne);
        $content->addTag($tagTwo);
        $this->contentRepository->add($content, true);

        $fetchedContent = $this->getContentByTag($tagOne);
        $this->assertEquals($content->getId(), $fetchedContent->getId());

        $fetchedContent = $this->getContentByTag($tagTwo);
        $this->assertEquals($content->getId(), $fetchedContent->getId());
    }

    /**
     * Retrieve a Content Entity (first one found) by the provided Tag.
     *
     * @param  Tag  $tag
     *
     * @return Content
     */
    private function getContentByTag(Tag $tag): Content
    {
        return $this->contentRepository->createQueryBuilder("c")
            ->where(':tag MEMBER OF c.tags')
            ->setParameters(array('tag' => $tag))
            ->getQuery()
            ->getResult()[0]
        ;
    }
}
