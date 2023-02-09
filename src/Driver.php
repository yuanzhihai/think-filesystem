<?php

declare ( strict_types = 1 );

namespace yzh52521\filesystem;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemException;
use League\Flysystem\Ftp\FtpAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\PathPrefixer;
use League\Flysystem\PhpseclibV3\SftpAdapter;
use League\Flysystem\ReadOnly\ReadOnlyFilesystemAdapter;
use League\Flysystem\PathPrefixing\PathPrefixedAdapter;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\Visibility;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use think\Cache;
use think\File;
use think\file\UploadedFile;
use think\helper\Arr;
use voku\helper\ASCII;

/**
 * Class Driver
 * @package yzh52521\filesystem
 * @mixin Filesystem
 */
abstract class Driver
{

    /** @var Cache */
    protected Cache $cache;

    /** @var Filesystem */
    protected Filesystem $filesystem;


    protected FilesystemAdapter $adapter;

    /**
     * The Flysystem PathPrefixer instance.
     *
     * @var PathPrefixer
     */
    protected PathPrefixer $prefixer;

    /**
     * 配置参数
     * @var array
     */
    protected array $config = [];

    public function __construct(Cache $cache,array $config)
    {
        $this->cache  = $cache;
        $this->config = array_merge( $this->config,$config );

        $separator      = $config['directory_separator'] ?? DIRECTORY_SEPARATOR;
        $this->prefixer = new PathPrefixer( $config['root'] ?? '',$separator );

        if (isset( $config['prefix'] )) {
            $this->prefixer = new PathPrefixer( $this->prefixer->prefixPath( $config['prefix'] ),$separator );
        }

        $this->adapter    = $this->createAdapter();
        $this->filesystem = $this->createFilesystem( $this->adapter,$this->config );
    }

    abstract protected function createAdapter();

    /**
     * @param FilesystemAdapter $adapter
     * @param array $config
     * @return Filesystem
     */
    protected function createFilesystem(FilesystemAdapter $adapter,array $config): Filesystem
    {
        if ($config['read-only'] ?? false === true) {
            $adapter = new ReadOnlyFilesystemAdapter( $adapter );
        }

        if (!empty( $config['prefix'] )) {
            $adapter = new PathPrefixedAdapter( $adapter,$config['prefix'] );
        }

        return new Filesystem( $adapter,Arr::only( $config,[
            'directory_visibility',
            'disable_asserts',
            'temporary_url',
            'url',
            'visibility',
        ] ) );
    }

    /**
     * 获取文件完整路径
     * @param string $path
     * @return string
     */
    public function path(string $path): string
    {
        return $this->prefixer->prefixPath( $path );
    }

    protected function concatPathToUrl($url,$path): string
    {
        return rtrim( $url,'/' ).'/'.ltrim( $path,'/' );
    }

    /**
     * Determine if a file or directory exists.
     *
     * @param string $path
     * @return bool
     */
    public function exists(string $path): bool
    {
        return $this->filesystem->has( $path );
    }

    /**
     * Determine if a file or directory is missing.
     *
     * @param string $path
     * @return bool
     */
    public function missing(string $path): bool
    {
        return !$this->exists( $path );
    }

    /**
     * Determine if a file exists.
     *
     * @param string $path
     * @return bool
     * @throws FilesystemException
     */
    public function fileExists(string $path): bool
    {
        return $this->filesystem->fileExists( $path );
    }

    /**
     * Determine if a file is missing.
     *
     * @param string $path
     * @return bool
     * @throws FilesystemException
     */
    public function fileMissing(string $path): bool
    {
        return !$this->fileExists( $path );
    }

    /**
     * Determine if a directory exists.
     *
     * @param string $path
     * @return bool
     * @throws FilesystemException
     */
    public function directoryExists(string $path): bool
    {
        return $this->filesystem->directoryExists( $path );
    }

    /**
     * Determine if a directory is missing.
     *
     * @param string $path
     * @return bool
     * @throws FilesystemException
     */
    public function directoryMissing(string $path): bool
    {
        return !$this->directoryExists( $path );
    }

    /**
     * Get the contents of a file.
     *
     * @param string $path
     * @return string|void
     * @throws FilesystemException
     * @throws \Throwable
     */
    public function get(string $path)
    {
        try {
            return $this->filesystem->read( $path );
        } catch ( UnableToReadFile $e ) {
            throw_if( $this->throwsExceptions(),$e );
        }
    }

    /**
     * Create a streamed response for a given file.
     *
     * @param string $path
     * @param string|null $name
     * @param array $headers
     * @param string|null $disposition
     * @return StreamedResponse
     * @throws FilesystemException
     * @throws \Throwable
     */
    public function response(string $path,string $name = null,array $headers = [],?string $disposition = 'inline'): StreamedResponse
    {
        $response = new StreamedResponse;

        if (!array_key_exists( 'Content-Type',$headers )) {
            $headers['Content-Type'] = $this->mimeType( $path );
        }

        if (!array_key_exists( 'Content-Length',$headers )) {
            $headers['Content-Length'] = $this->size( $path );
        }

        if (!array_key_exists( 'Content-Disposition',$headers )) {
            $filename = $name ?? basename( $path );

            $disposition = $response->headers->makeDisposition(
                $disposition,$filename,$this->fallbackName( $filename )
            );

            $headers['Content-Disposition'] = $disposition;
        }

        $response->headers->replace( $headers );

        $response->setCallback( function () use ($path) {
            $stream = $this->readStream( $path );
            fpassthru( $stream );
            fclose( $stream );
        } );

        return $response;
    }

    /**
     * Create a streamed download response for a given file.
     *
     * @param $path
     * @param string|null $name
     * @param array $headers
     * @return StreamedResponse
     * @throws FilesystemException
     * @throws \Throwable
     */
    public function download($path,string $name = null,array $headers = []): StreamedResponse
    {
        return $this->response( $path,$name,$headers,'attachment' );
    }

    /**
     * Convert the string to ASCII characters that are equivalent to the given name.
     *
     * @param string $name
     * @return string
     */
    protected function fallbackName(string $name): string
    {
        return str_replace( '%','',ASCII::to_ascii( $name,'en' ) );
    }

    /**
     * Get the visibility for the given path.
     *
     * @param string $path
     * @return string
     * @throws FilesystemException
     */
    public function getVisibility(string $path): string
    {
        if ($this->filesystem->visibility( $path ) == Visibility::PUBLIC) {
            return 'public';
        }

        return 'private';
    }

    /**
     * Set the visibility for the given path.
     *
     * @param string $path
     * @param string $visibility
     * @return bool
     * @throws FilesystemException
     * @throws \Throwable
     */
    public function setVisibility(string $path,string $visibility): bool
    {
        try {
            $this->filesystem->setVisibility( $path,$visibility );
        } catch ( UnableToSetVisibility $e ) {
            throw_if( $this->throwsExceptions(),$e );

            return false;
        }

        return true;
    }

    /**
     * Prepend to a file.
     *
     * @param string $path
     * @param string $data
     * @param string $separator
     * @return bool|string
     */
    public function prepend(string $path,string $data,string $separator = PHP_EOL): bool|string
    {
        if ($this->fileExists( $path )) {
            return $this->put( $path,$data.$separator.$this->get( $path ) );
        }

        return $this->put( $path,$data );
    }

    /**
     * Append to a file.
     *
     * @param string $path
     * @param string $data
     * @param string $separator
     * @return bool|string
     * @throws FilesystemException
     * @throws \Throwable
     */
    public function append(string $path,string $data,string $separator = PHP_EOL): bool|string
    {
        if ($this->fileExists( $path )) {
            return $this->put( $path,$this->get( $path ).$separator.$data );
        }

        return $this->put( $path,$data );
    }


    /**
     * Delete the file at a given path.
     *
     * @param array|string $paths
     * @return bool
     * @throws FilesystemException
     * @throws \Throwable
     */
    public function delete(array|string $paths): bool
    {
        $paths = is_array( $paths ) ? $paths : func_get_args();

        $success = true;

        foreach ( $paths as $path ) {
            try {
                $this->filesystem->delete( $path );
            } catch ( UnableToDeleteFile $e ) {
                throw_if( $this->throwsExceptions(),$e );

                $success = false;
            }
        }

        return $success;
    }

    /**
     * Copy a file to a new location.
     *
     * @param string $from
     * @param string $to
     * @return bool
     * @throws FilesystemException
     * @throws \Throwable
     */
    public function copy(string $from,string $to): bool
    {
        try {
            $this->filesystem->copy( $from,$to );
        } catch ( UnableToCopyFile $e ) {
            throw_if( $this->throwsExceptions(),$e );

            return false;
        }

        return true;
    }

    /**
     * Move a file to a new location.
     *
     * @param string $from
     * @param string $to
     * @return bool
     * @throws FilesystemException
     * @throws \Throwable
     */
    public function move(string $from,string $to): bool
    {
        try {
            $this->filesystem->move( $from,$to );
        } catch ( UnableToMoveFile $e ) {
            throw_if( $this->throwsExceptions(),$e );

            return false;
        }

        return true;
    }

    /**
     * Get the file size of a given file.
     *
     * @param string $path
     * @return int
     * @throws FilesystemException
     */
    public function size(string $path): int
    {
        return $this->filesystem->fileSize( $path );
    }

    /**
     * Get the mime-type of a given file.
     *
     * @param $path
     * @return bool|string
     * @throws FilesystemException
     * @throws \Throwable
     */
    public function mimeType($path): bool|string
    {
        try {
            return $this->filesystem->mimeType( $path );
        } catch ( UnableToRetrieveMetadata $e ) {
            throw_if( $this->throwsExceptions(),$e );
        }

        return false;
    }

    /**
     * Get the file's last modification time.
     *
     * @param string $path
     * @return int
     * @throws FilesystemException
     */
    public function lastModified(string $path): int
    {
        return $this->filesystem->lastModified( $path );
    }


    /**
     * {@inheritdoc}
     * @param string $path
     * @return resource|void
     * @throws FilesystemException
     * @throws \Throwable
     */
    public function readStream(string $path)
    {
        try {
            return $this->filesystem->readStream( $path );
        } catch ( UnableToReadFile $e ) {
            throw_if( $this->throwsExceptions(),$e );
        }
    }

    /**
     * {@inheritdoc}
     * @return bool
     * @throws FilesystemException
     */
    public function writeStream(string $path,string $resource,array $options = []): bool
    {
        try {
            $this->filesystem->writeStream( $path,$resource,$options );
        } catch ( UnableToWriteFile|UnableToSetVisibility $e ) {
            throw_if( $this->throwsExceptions(),$e );

            return false;
        }

        return true;
    }

    protected function getLocalUrl($path)
    {
        if (isset( $this->config['url'] )) {
            return $this->concatPathToUrl( $this->config['url'],$path );
        }

        return $path;
    }

    public function url(string $path): string
    {
        $adapter = $this->adapter;

        if (method_exists( $adapter,'getUrl' )) {
            return $adapter->getUrl( $path );
        } elseif (method_exists( $this->filesystem,'getUrl' )) {
            return $this->filesystem->getUrl( $path );
        } elseif ($adapter instanceof SftpAdapter || $adapter instanceof FtpAdapter) {
            return $this->getFtpUrl( $path );
        } elseif ($adapter instanceof LocalFilesystemAdapter) {
            return $this->getLocalUrl( $path );
        } else {
            throw new \RuntimeException( 'This driver does not support retrieving URLs.' );
        }
    }


    /**
     * Get the URL for the file at the given path.
     *
     * @param string $path
     * @return string
     */
    protected function getFtpUrl(string $path): string
    {
        return isset( $this->config['url'] )
            ? $this->concatPathToUrl( $this->config['url'],$path )
            : $path;
    }

    /**
     * Replace the scheme, host and port of the given UriInterface with values from the given URL.
     *
     * @param $uri
     * @param string $url
     * @return mixed
     */
    protected function replaceBaseUrl($uri,string $url): mixed
    {
        $parsed = parse_url( $url );

        return $uri
            ->withScheme( $parsed['scheme'] )
            ->withHost( $parsed['host'] )
            ->withPort( $parsed['port'] ?? null );
    }

    /**
     * Get the Flysystem driver.
     * @return Filesystem|\League\Flysystem\FilesystemOperator
     */
    public function getDriver(): Filesystem|\League\Flysystem\FilesystemOperator
    {
        return $this->filesystem;
    }

    /**
     * Get the Flysystem adapter.
     *
     * @return \League\Flysystem\FilesystemAdapter
     */
    public function getAdapter(): FilesystemAdapter
    {
        return $this->adapter;
    }

    /**
     * 保存文件
     * @param string $path 路径
     * @param string|File $file 文件
     * @param string|\Closure|null $rule 文件名规则
     * @param array $options 参数
     * @return bool|string
     */
    public function putFile(string $path,File|string $file,string|\Closure $rule = null,array $options = []): bool|string
    {
        $file = is_string( $file ) ? new File( $file ) : $file;
        return $this->putFileAs( $path,$file,$file->hashName( $rule ),$options );
    }

    /**
     * 指定文件名保存文件
     * @param string $path 路径
     * @param File $file 文件
     * @param string $name 文件名
     * @param array $options 参数
     * @return bool|string
     */
    public function putFileAs(string $path,File $file,string $name,array $options = []): bool|string
    {
        $stream = fopen( $file->getRealPath(),'r' );
        $path   = trim( $path.'/'.$name,'/' );

        $result = $this->put( $path,$stream,$options );

        if (is_resource( $stream )) {
            fclose( $stream );
        }

        return $result ? $path : false;
    }


    public function put($path,$contents,$options = []): bool|string
    {
        $options = is_string( $options )
            ? ['visibility' => $options]
            : (array)$options;

        // If the given contents is actually a file or uploaded file instance than we will
        // automatically store the file using a stream. This provides a convenient path
        // for the developer to store streams without managing them manually in code.
        if ($contents instanceof File ||
            $contents instanceof UploadedFile) {
            return $this->putFile( $path,$contents,$options );
        }

        try {
            if ($contents instanceof StreamInterface) {
                $this->writeStream( $path,$contents->detach(),$options );

                return true;
            }

            is_resource( $contents )
                ? $this->writeStream( $path,$contents,$options )
                : $this->write( $path,$contents,$options );
        } catch ( UnableToWriteFile|UnableToSetVisibility $e ) {
            throw_if( $this->throwsExceptions(),$e );

            return false;
        }

        return true;
    }

    /**
     * Get an array of all files in a directory.
     *
     * @param string|null $directory
     * @param bool $recursive
     * @return array
     * @throws FilesystemException
     */
    public function files(string $directory = null,bool $recursive = false): array
    {
        return $this->filesystem->listContents( $directory ?? '',$recursive )
            ->filter( function (StorageAttributes $attributes) {
                return $attributes->isFile();
            } )
            ->sortByPath()
            ->map( function (StorageAttributes $attributes) {
                return $attributes->path();
            } )
            ->toArray();
    }

    /**
     * Get all of the files from the given directory (recursive).
     *
     * @param string|null $directory
     * @return array
     */
    public function allFiles(string $directory = null): array
    {
        return $this->files( $directory,true );
    }

    /**
     * Get all the directories within a given directory.
     *
     * @param string|null $directory
     * @param bool $recursive
     * @return array
     */
    public function directories(string $directory = null,bool $recursive = false): array
    {
        return $this->filesystem->listContents( $directory ?? '',$recursive )
            ->filter( function (StorageAttributes $attributes) {
                return $attributes->isDir();
            } )
            ->map( function (StorageAttributes $attributes) {
                return $attributes->path();
            } )
            ->toArray();
    }

    /**
     * Get all the directories within a given directory (recursive).
     *
     * @param string|null $directory
     * @return array
     */
    public function allDirectories(string $directory = null): array
    {
        return $this->directories( $directory,true );
    }

    /**
     * Create a directory.
     *
     * @param string $path
     * @return bool
     */
    public function makeDirectory(string $path): bool
    {
        try {
            $this->filesystem->createDirectory( $path );
        } catch ( UnableToCreateDirectory|UnableToSetVisibility $e ) {
            throw_if( $this->throwsExceptions(),$e );

            return false;
        }

        return true;
    }

    /**
     * Recursively delete a directory.
     *
     * @param string $directory
     * @return bool
     */
    public function deleteDirectory(string $directory): bool
    {
        try {
            $this->filesystem->deleteDirectory( $directory );
        } catch ( UnableToDeleteDirectory $e ) {
            throw_if( $this->throwsExceptions(),$e );

            return false;
        }

        return true;
    }

    /**
     * Determine if Flysystem exceptions should be thrown.
     *
     * @return bool
     */
    protected function throwsExceptions(): bool
    {
        return (bool)( $this->config['throw'] ?? false );
    }

    public function __call($method,$parameters)
    {
        return $this->filesystem->$method( ...$parameters );
    }
}
