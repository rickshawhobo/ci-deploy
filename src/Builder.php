<?php

namespace HRS\Ci;

class Builder
{
    public function build($env = 'dev', $buildDocker = true)
    {
        if ($buildDocker) {
            return $this->buildDocker();
        }
    }

    public function buildExecute($cmd, $buildsDocker)
    {

        passthru($cmd, $retVal);
        if ($retVal !== 0) {
            exit($retVal);
        }
        if ($buildsDocker) {
            $this->buildDocker();
        }

        return 0;
    }

    /**
     * @param string $dockerFile
     * @param string|null $tagSuffix
     * @return string
     */
    public function buildDocker(string $dockerFile = 'Dockerfile', string $tagSuffix = null)
    {
        $tag = self::getLocalImageTag($tagSuffix);

        $cmd = 'docker build --no-cache -t ' . $tag . ' -f ' . $dockerFile . ' .';

        passthru($cmd, $retVal);
        if ($retVal !== 0) {
            exit($retVal);
        }
        return $tag;
    }

    public static function getContainerName()
    {
        return "container-" . getenv('CI_PIPELINE_ID');
    }

    /**
     * @param string|null $suffix
     * @return string
     */
    public static function getLocalImageTag(string $suffix = null)
    {
        $tag = "tag-" . getenv('CI_PIPELINE_ID');
        if ($suffix) {
            $tag .= "-{$suffix}";
        }

        return $tag;
    }

    public static function dockerCleanup()
    {

        $cmd = "docker container stop " . self::getContainerName() . " || exit 0";
        passthru($cmd);

        $cmd = "docker image rm -f " . self::getLocalImageTag() . " || exit 0";
        passthru($cmd);

        $cmd = "docker image rm -f $(docker images --filter reference=" . self::getLocalImageTag() ."-* -q) || exit 0";
        passthru($cmd);
    }
}
