<?php
declare( strict_types = 1 );

namespace yzh52521\filesystem\driver;


use Overtrue\Flysystem\Qiniu\QiniuAdapter;
use yzh52521\filesystem\Driver;

class Qiniu extends Driver
{

    protected function createAdapter()
    {
        return new QiniuAdapter(
            $this->config['accessKey'], $this->config['secretKey'],
            $this->config['bucket'], $this->config['domain']
        );
    }
}
