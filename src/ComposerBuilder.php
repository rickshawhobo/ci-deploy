<?php
namespace HRS\Ci;

class ComposerBuilder extends Builder
{
    public function build($env = 'dev', $buildDocker = true)
    {
        if ($buildDocker) {
            // unfortunately this app has everything in the dockerfile so no need to build anything
            return $this->buildDocker();
        }
    }
    public function buildDocker()
    {
        $container = Builder::getContainerName();

        $cmd = "docker stop $container || exit 0";
        passthru($cmd);

        return parent::buildDocker();
    }
}
