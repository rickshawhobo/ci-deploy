<?php
/**
 * Created by PhpStorm.
 * User: gyates
 * Date: 10/11/17
 * Time: 12:55 PM
 */

namespace HRS\Ci;


class HRSRegistry
{
    public static $REGISTRY_URL = "registry.healthrecoverysolutions.com";

    public function push(string $tag)
    {
        $cmd = "docker push {$tag}";
        passthru($cmd, $retVal);
    }

    public function tagForPushing(string $sourceTag, string $newTag, string $version = "latest")
    {
        $fullTag = static::$REGISTRY_URL . "/{$newTag}:{$version}";
        $cmd = "docker tag {$sourceTag} {$fullTag}";

        passthru($cmd, $retVal);

        if ($retVal !== 0) {
            echo "Tagging failed";
            exit($retVal);
        }

        return $fullTag;
    }
}