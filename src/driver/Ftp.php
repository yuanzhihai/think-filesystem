<?php
declare( strict_types = 1 );

namespace yzh52521\filesystem\driver;

use League\Flysystem\Ftp\FtpAdapter;
use League\Flysystem\Ftp\FtpConnectionOptions;
use yzh52521\filesystem\Driver;

class Ftp extends Driver
{
    protected function createAdapter()
    {
        if (!isset( $this->config['root'] )) {
            $this->config['root'] = '';
        }

        return new FtpAdapter( FtpConnectionOptions::fromArray( $this->config ) );
    }
}