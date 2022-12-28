<?php
declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Content;
use App\Service\Typesense\Search;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Http\Client\Exception;
use Typesense\Exceptions\TypesenseClientError;

class ContentSearchIndexListener
{
    public function __construct(private readonly Search $searchService)
    {
    }

    /**
     * Index a Content Entity after it has been persisted.
     *
     * @param  Content  $content
     * @param  LifecycleEventArgs  $event
     *
     * @return void
     * @throws Exception
     * @throws TypesenseClientError
     */
    public function postPersist(Content $content, LifecycleEventArgs $event): void
    {
        $this->searchService->indexContent($content);
    }

    /**
     * Index a Content Entity after it has been updated.
     *
     * @param  Content  $content
     * @param  LifecycleEventArgs  $event
     *
     * @return void
     * @throws Exception
     * @throws TypesenseClientError
     */
    public function postUpdate(Content $content, LifecycleEventArgs $event): void
    {
        // @TODO: Ensure (using tests) updates are covered with persistence logic.
        $this->searchService->indexContent($content);
    }
}