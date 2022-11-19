<?php

declare( strict_types = 1 );

namespace yzh52521\filesystem\driver;

use League\Flysystem\Visibility;
use Obs\ObsClient;
use think\helper\Arr;
use yzh52521\filesystem\Driver;
use yzh52521\Flysystem\Obs\ObsAdapter;
use yzh52521\Flysystem\Obs\PortableVisibilityConverter;

class Obs extends Driver
{

    protected function createAdapter()
    {
        $config                      = $this->config;
        $root                        = $this->config['root'] ?? '';
        $options                     = $this->config['options'] ?? [];
        $portableVisibilityConverter = new PortableVisibilityConverter(
            $this->config['directory_visibility'] ?? $this->config['visibility'] ?? Visibility::PUBLIC
        );
        $config['is_cname']          ??= $this->config['is_cname'] ?? false;
        $config['token']             ??= $this->config['token'] ?? null;
        $config['bucket_endpoint']   = $this->config['bucket_endpoint'];
        $config['security_token']    = $this->config['security_token'];
        $options                     = array_merge( $options,Arr::only( $config,['url','temporary_url','endpoint','bucket_endpoint'] ) );
        $obsClient                   = new ObsClient( $config );
        return new ObsAdapter( $obsClient,$config['bucket'],$root,$portableVisibilityConverter,null,$options );
    }

}
