<?php

namespace HRS\Ci;

class GulpBuilder extends Builder
{

    public function build($env = 'dev', $buildsDocker = true)
    {
        $cmd = 'npm install && bower install --allow-root && gulp build --ENV=' . $env;
        return parent::buildExecute($cmd, $buildsDocker);
    }
}
