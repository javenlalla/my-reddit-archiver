<?php

namespace App\Denormalizer;

use App\Entity\Asset;
use App\Entity\Award;
use App\Repository\AwardRepository;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class AwardDenormalizer implements DenormalizerInterface
{
    public function __construct(
        private readonly AwardRepository $awardRepository,
        private readonly AssetDenormalizer $assetDenormalizer,
    ) {
    }

    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = null): bool
    {
        return is_array($data) && $type === Award::class;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Award::class => true,
        ];
    }

    /**
     * Denormalize the provided Award array and return an Award Entity. Check
     * for an existing instance of the Award before creating a new record
     * to persist.
     *
     * @param  array  $data
     * @param  string  $type
     * @param  string|null  $format
     * @param  array{
     *     }  $context
     *
     * @return Award|null
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): ?Award
    {
        $awardData = $data;

        $redditId = $awardData['id'];
        $existingAward = $this->awardRepository->findOneBy(['redditId' => $redditId]);
        if ($existingAward instanceof Award) {
            return $existingAward;
        }

        $award = new Award();
        $award->setRedditId($redditId);
        $award->setName($awardData['name']);
        $award->setReferenceId(substr(md5($awardData['name']), 0, 10));

        $iconAsset = $this->assetDenormalizer->denormalize($awardData['icon_url'], Asset::class);
        if ($iconAsset instanceof Asset) {
            $award->setIconAsset($iconAsset);
        } else {
            // @TODO: Replace returning null with a default icon asset. This has been added in the somewhat edge-case of an asset 404ing on Reddit's side.
            return null;
        }

        if (!empty($awardData['description'])) {
            $award->setDescription($awardData['description']);
        }

        $this->awardRepository->add($award, true);

        return $award;
    }
}
