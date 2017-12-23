<?php
namespace HRS\Ci;

class MvnBuilder extends Builder
{

    public function build($env = 'dev', $buildDocker = true)
    {

        $cmd = "mvn clean compile assembly:single";

        exec($cmd, $output, $retVal);
        print_r($output);
        if ($retVal !== 0) {
            echo "mvn build failed";
            exit($retVal);
        }
        if ($buildDocker) {
            $localTag = $this->buildDocker();

            $container = Builder::getContainerName();

            $cmd = "docker run -d --name $container $localTag";
            passthru($cmd, $retVal);
        }
    }
}
