<?php
declare(strict_types=1);

namespace App\Entity;

interface AssetInterface
{
    /**
     * Return the ID of the Asset.
     *
     * @return int|null
     */
    public function getId(): ?int;

    /**
     * Return the local filename of the Asset.
     *
     * @return string|null
     */
    public function getFilename(): ?string;

    /**
     * Return the first level directory name of the Asset.
     *
     * @return string|null
     */
    public function getDirOne(): ?string;

    /**
     * Return the second level directory name of the Asset.
     *
     * @return string|null
     */
    public function getDirTwo(): ?string;

    /**
     * Return the original Source URL from which the Asset was downloaded from.
     *
     * @return string|null
     */
    public function getSourceUrl(): ?string;
}