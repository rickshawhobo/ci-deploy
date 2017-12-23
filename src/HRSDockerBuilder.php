<?php

namespace HRS\Ci;


/**
 * Class HRSDockerBuilder
 * @package HRS\Ci
 */
class HRSDockerBuilder extends Builder
{
    /**
     * @param string $dockerFile
     * @param string|null $tagSuffix
     * @return string
     */
    public function build(string $dockerFile, string $tagSuffix = null)
    {
        $tagSuffix = $tagSuffix ?: strtolower($dockerFile);
        return parent::buildDocker($dockerFile, $tagSuffix);
    }
}