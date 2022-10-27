<?php

declare(strict_types=1);

namespace yzh52521\filesystem\driver;

use League\Flysystem\AdapterInterface;
use yzh52521\filesystem\Driver;
use yzh52521\Flysystem\Oss\OssAdapter;

class Aliyun extends Driver
{

    protected function createAdapter(): AdapterInterface
    {
        return new OssAdapter([
            'accessId'     => $this->config['accessId'],
            'accessSecret' => $this->config['accessSecret'],
            'bucket'       => $this->config['bucket'],
            'endpoint'     => $this->config['endpoint'],
        ]);
    }


}
