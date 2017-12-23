<?php

namespace HRS\Ci;

class GulpTest
{
    public function run()
    {
        $cmd = 'npm install && bower install --allow-root && gulp test';
        passthru($cmd, $retVal);
        if ($retVal !== 0) {
            exit($retVal);
        }
    }
}
