<?php
declare(strict_types=1);

namespace App\Command;

use App\Entity\ItemJson;
use App\Repository\AssetRepository;
use App\Repository\ItemJsonRepository;
use App\Service\Reddit\Api;
use App\Service\Reddit\Manager\Assets;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'reddit:resync-test-data',
    description: 'Sync the Item JSON for test data to temporary database table.'
)]
class ResyncTestDataCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly ItemJsonRepository $itemJsonRepository, private readonly Api $redditApi)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $targetRedditIds = [
            't3_vepbt0',
            't5_2u1if',
            't3_won0ky',
            't5_310rm',
            't3_uk7ctt',
            't5_2qh3s',
            't3_vdmg2f',
            't5_2qh1i',
            't3_v443nh',
            't5_2rc7j',
            't3_tl8qic',
            't5_2w67q',
            't3_wfylnl',
            't5_2tex6',
            't3_v27nr7',
            't5_2xdwt',
            't1_j84z4vm',
            't3_10zrjou',
            't5_3c2d7',
            't3_8ung3q',
            't5_2sljg',
            't3_cs8urd',
            't3_utsmkw',
            't3_8vkdsq',
            't5_2qifv',
            't1_ip914eh',
            't3_xjarj9',
            't3_dvuois',
            't3_jcc799',
            't5_2t46o',
            't3_jtwoe0',
            't5_2qh33',
            't3_y1vmdf',
            't1_is022vs',
            't5_2qh72',
            't3_10bt9qv',
            't5_2sdu8',
            't3_wvg39c',
            't5_2qpmd',
            't3_14jw39w',
            't5_2uqcm',
            't3_wf1e8p',
            't5_mouw',
            't3_102oo0x',
            't5_2t7no',
            't1_ip90mlq',
            't3_14cerh3',
            't5_2quz8',
        ];

        $retrievedInfos = $this->redditApi->getRedditItemInfoByIds(new Api\Context(Api\Context::SOURCE_COMMAND_TEST_DATA_RESYNC), $targetRedditIds);

        foreach ($retrievedInfos as $retrievedInfo) {
            $redditId = $this->getRedditIdFromInfo($retrievedInfo);

            $existingItemJson = $this->itemJsonRepository->findOneBy(['redditId' => $redditId]);
            if ($existingItemJson instanceof ItemJson) {
                $existingItemJson->setJsonBody(json_encode($retrievedInfo, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
                $this->entityManager->persist($existingItemJson);
            } else {
                $itemJson = new ItemJson();
                $itemJson->setRedditId($redditId);
                $itemJson->setJsonBody(json_encode($retrievedInfo, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));

                $this->entityManager->persist($itemJson);
            }
        }

        $this->entityManager->flush();

        $ids = '';
        foreach ($targetRedditIds as $targetRedditId) {
            $ids .= "'" . $targetRedditId . "',";
        }

        $output->writeln(rtrim($ids, ','));

        return Command::SUCCESS;
    }

    /**
     * Navigate the provided Item Info array and retrieve its Reddit ID.
     *
     * @param  array  $retrievedInfo
     *
     * @return string
     * @throws Exception
     */
    private function getRedditIdFromInfo(array $retrievedInfo): string
    {
        if (!empty($retrievedInfo['data']['name'])) {
            return $retrievedInfo['data']['name'];
        }

        throw new Exception(sprintf(
                'No Reddit ID found in Info Body: %s',
                var_export($retrievedInfo, true))
        );
    }
}