<?php
declare( strict_types = 1 );

namespace yzh52521\filesystem\driver;


use League\Flysystem\AdapterInterface;
use Overtrue\Flysystem\QiNiu\QiniuAdapter;
use yzh52521\filesystem\Driver;

class Qiniu extends Driver
{

    protected function createAdapter(): AdapterInterface
    {
        return new QiniuAdapter(
            $this->config['accessKey'],$this->config['secretKey'],
            $this->config['bucket'],$this->config['domain']
        );
    }

    public function url(string $path): string
    {
        if (isset( $this->config['url'] )) {
            return $this->concatPathToUrl( $this->config['url'],$path );
        }
        return parent::url( $path );
    }
}
