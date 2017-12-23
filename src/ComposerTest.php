<?php

namespace HRS\Ci;

class ComposerTest
{

    public function run()
    {
        $container = Builder::getContainerName();
        $tag = Builder::getLocalImageTag();
        $cmd = "docker run --name ${container} -d -e COMPOSER_INSTALL=0 {$tag}";
        passthru($cmd);

        $cmd = "docker exec -i {$container} bash -c \"composer install && ./vendor/bin/phpunit\"";

        passthru($cmd, $retVal);

        if ($retVal !== 0) {
            echo "Build failed";
            exit($retVal);
        }
    }
}
