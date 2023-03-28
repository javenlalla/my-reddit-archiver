<?php
declare(strict_types=1);

namespace App\Trait\Debug;

use Symfony\Component\Console\Output\OutputInterface;

trait MemoryUsageTrait
{
    /**
     * @TODO: Convert this variable to a constant after upgrading to PHP 8.2.
     * @var string[]
     */
    private array $byteUnits = ['B','KB','MB','GB','TB','PB'];

    /**
     * Get the peak memory usage formatted for human-readable display.
     *
     * Logic source: https://www.php.net/manual/en/function.memory-get-usage.php#96280
     *
     * @return string
     */
    public function getFormattedMemoryUsage(): string
    {
        $memoryUsage = memory_get_peak_usage();

        return $this->formatMemoryUsage($memoryUsage);
    }

    /**
     * Format the memory usage bytes for human-readable display.
     * Ex: 54.19 MB
     *
     * @param  int  $memoryUsage
     *
     * @return string
     */
    private function formatMemoryUsage(int $memoryUsage): string
    {
        $exponent = floor(log($memoryUsage,1024));
        $formattedBytes = $memoryUsage/pow(1024, $exponent);

        return @round($formattedBytes,2) . ' ' . $this->byteUnits[$exponent];
    }
}
