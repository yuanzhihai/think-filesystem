<?php
declare( strict_types = 1 );

namespace yzh52521\filesystem\driver;

use yzh52521\filesystem\Driver;

class Ftp extends Driver
{
    protected function createAdapter()
    {
        $connection = \League\Flysystem\Ftp\FtpConnectionOptions::fromArray( [
            'host'     => $this->config['hostname'], // required
            'root'     => $this->config['root'], // required
            'username' => $this->config['username'], // required
            'password' => $this->config['password'], // required
            'port'     => $this->config['port'] ?? 21,
            'timeout'  => $this->config['timeout'] ?? 90
        ] );
        return new \League\Flysystem\Ftp\FtpAdapter( $connection,
            new \League\Flysystem\FTP\FtpConnectionProvider(),
            new \League\Flysystem\FTP\NoopCommandConnectivityChecker(),
            new \League\Flysystem\UnixVisibility\PortableVisibilityConverter()
        );

    }
}