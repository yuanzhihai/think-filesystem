<?php

declare( strict_types = 1 );

namespace yzh52521\filesystem\driver;

use yzh52521\filesystem\Driver;
use yzh52521\Flysystem\Oss\OssAdapter;

class Aliyun extends Driver
{

    protected function createAdapter()
    {
        return new OssAdapter( $this->config );
    }

}
