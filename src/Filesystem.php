<?php

declare( strict_types = 1 );

namespace yzh52521\filesystem;

use InvalidArgumentException;
use think\helper\Arr;
use think\helper\Str;
use think\Manager;

class Filesystem extends Manager
{
    /**
     * The registered custom driver creators.
     *
     * @var array
     */
    protected $customCreators = [];

    protected $namespace = '\\yzh52521\\filesystem\\driver\\';

    /**
     * @param null|string $name
     * @return Driver
     */
    public function disk(string $name = null): Driver
    {
        return $this->driver( $name );
    }

    /**
     * @param null|string $name
     * @return Driver
     */
    public function cloud(string $name = null): Driver
    {
        return $this->driver( $name );
    }

    /**
     * Call a custom driver creator.
     *
     * @param array $config
     * @return mixed
     */
    protected function callCustomCreator(array $config)
    {
        return $this->customCreators[$config['driver']]( $this->app,$config );
    }

    protected function resolveType(string $name)
    {
        return $this->getDiskConfig( $name,'type','local' );
    }

    protected function resolveConfig(string $name)
    {
        return $this->getDiskConfig( $name );
    }

    protected function createDriver(string $name)
    {
        $type = $this->resolveType( $name );


        if (isset( $this->customCreators[$name] )) {
            return $this->callCustomCreator( $type );
        }

        $method = 'create'.Str::studly( $type ).'Driver';

        $params = $this->resolveParams( $name );


        if (method_exists( $this,$method )) {
            return $this->$method( ...$params );
        }

        $class = $this->resolveClass( $type );

        return $this->app->invokeClass( $class,$params );
    }

    /**
     * 获取缓存配置
     * @access public
     * @param null|string $name 名称
     * @param mixed $default 默认值
     * @return mixed
     */
    public function getConfig(string $name = null,$default = null)
    {
        if (!is_null( $name )) {
            return $this->app->config->get( 'filesystem.'.$name,$default );
        }

        return $this->app->config->get( 'filesystem' );
    }

    /**
     * 获取磁盘配置
     * @param string $disk
     * @param null $name
     * @param null $default
     * @return array
     */
    public function getDiskConfig($disk,$name = null,$default = null)
    {
        if ($config = $this->getConfig( "disks.{$disk}" )) {
            return Arr::get( $config,$name,$default );
        }

        throw new InvalidArgumentException( "Disk [$disk] not found." );
    }

    /**
     * 默认驱动
     * @return string|null
     */
    public function getDefaultDriver()
    {
        return $this->getConfig( 'default' );
    }

    /**
     * @param $driver
     * @param \Closure $callback
     * @return $this
     */
    public function extend($driver,\Closure $callback)
    {
        $this->customCreators[$driver] = $callback;

        return $this;
    }

    /**
     * 动态调用
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call($method,$parameters)
    {
        return $this->driver()->$method( ...$parameters );
    }
}
