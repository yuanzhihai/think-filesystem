<?php

declare( strict_types = 1 );

namespace yzh52521\filesystem\driver;

use Obs\ObsClient;
use yzh52521\filesystem\Driver;
use yzh52521\Flysystem\Obs\ObsAdapter;

class Obs extends Driver
{

    protected function createAdapter()
    {
        $config            = [
            'key'      => $this->config['key'],
            'secret'   => $this->config['secret'],
            'bucket'   => $this->config['bucket'],
            'endpoint' => $this->config['endpoint'],
        ];
        $client            = new ObsClient( $config );
        $config['options'] = [
            'url'             => '',
            'endpoint'        => $this->config['endpoint'],
            'bucket_endpoint' => '',
            'temporary_url'   => '',
        ];
        return new ObsAdapter( $client,$this->config['bucket'],$this->config['prefix'],null,null,$config['options'] );
    }

}
