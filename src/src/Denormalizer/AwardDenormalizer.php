<?php

namespace App\Denormalizer;

use App\Entity\Award;
use App\Repository\AwardRepository;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class AwardDenormalizer implements DenormalizerInterface
{
    public function __construct(
        private readonly AwardRepository $awardRepository,
    ) {
    }

    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = null): bool
    {
        return is_array($data) && $type === Award::class;
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
     * @return Award
     */
    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): Award
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
        $award->setRedditUrl($awardData['icon_url']);

        if (!empty($awardData['description'])) {
            $award->setDescription($awardData['description']);
        }

        $this->awardRepository->add($award, true);

        return $award;
    }
}
