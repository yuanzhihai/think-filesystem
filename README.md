<h1><p align="center">think-filesystem</p></h1>
<p align="center"> thinkphp6.1 的文件系统扩展包，支持上传阿里云OSS和七牛和腾讯云COS和华为云OBS和awsS3</p>

## 包含

1. php >= 8.0.2
2. thinkphp >=6.1

## 支持

1. 阿里云
2. 七牛云
3. 腾讯云
4. 华为云
5. AwsS3
6. google
7. ftp
8. sftp

## 安装

第一步：

```shell
$ composer require yzh52521/think-filesystem
```

第二步： 在config/filesystem.php中添加配置

```
'aliyun' => [
    'type'         => 'aliyun',
    'accessId'     => '******',
    'accessSecret' => '******',
    'bucket'       => 'bucket',
    'endpoint'     => 'oss-cn-hongkong.aliyuncs.com',
    'url'          => 'http://oss-cn-hongkong.aliyuncs.com',
],
'qiniu'  => [
    'type'      => 'qiniu',
    'access_key' => '******',
    'secret_key' => '******',
    'bucket'    => 'bucket',
    'domain'    => 'https://youcdn.domain.com',
],
'qcloud' => [
    'type'       => 'qcloud',
    'region'      => '***', //bucket 所属区域 英文
    'app_id'      => '***', // 域名中数字部分
    'secret_id'   => '***',
    'secret_key'  => '***',
    'bucket'          => '***',
    'timeout'         => 60,
    'connect_timeout' => 60,
    'cdn'             => '您的 CDN 域名',
    'scheme'          => 'https',
    'read_from_cdn'   => false,
]
'obs'=>[
      'type' =>'obs',
      'root' => '',
      'key' => env('OBS_KEY'),
      'secret' => env('OBS_SECRET'),
      'bucket' => env('OBS_BUCKET'),
      'endpoint' => env('OBS_ENDPOINT'),
      'is_cname' => env('OBS_IS_CNAME', false), //true or false...
      'security_token' => env('OBS_SECURITY_TOKEN'),//true or false...
],
's3'=>[
       'type' =>'s3',
      'credentials'             => [
                'key'    => 'S3_KEY',
                'secret' => 'S3_SECRET',
      ],
      'region'                  => 'S3_REGION',
      'version'                 => 'latest',
      'bucket_endpoint'         => false,
      'use_path_style_endpoint' => false,
      'endpoint'                => 'S3_ENDPOINT',
      'bucket_name'             => 'S3_BUCKET',
],
'google'=>[
    'type' =>'google',
    'projectId' => 'GOOGLE_PROJECT_ID',//your-project-id
    'bucket' => 'GOOGLE_BUCKET', //your-bucket-name
    'prefix' => '', //optional-prefix 
],
'ftp'=[
    'type' =>'ftp',
    'host' => 'example.com',
    'username' => 'username',
    'password' => 'password',
    // 可选的 FTP 设置
    // 'port' => 21,
    // 'root' => '',
    // 'passive' => true,
    // 'ssl' => true,
    // 'timeout' => 30,
    // 'url'=>''
],
'sftp'=>[
    'type' =>'sftp',
    'host' => 'example.com',
    // 基于基础的身份验证设置...
    'username' => 'username',
    'password' => 'password',
    // 使用加密密码进行基于 SSH 密钥的身份验证的设置...
    'privateKey' => null,
    'passphrase' => null,
    // 可选的 SFTP 设置
    'port' => 22,
    'root' => '/path/to/root',
    'url' => '/path/to/root',
    'timeout' => 10
]
```

第三步： 开始使用。 请参考thinkphp文档
文档地址：[https://www.kancloud.cn/manual/thinkphp6_0/1037639 ](https://www.kancloud.cn/manual/thinkphp6_0/1037639 )

### demo

```php
$file = $this->request->file( 'image' );
      try {
            validate(
                ['image' => [
                        // 限制文件大小(单位b)，这里限制为4M
                        'fileSize' => 10 * 1024 * 1000,
                        // 限制文件后缀，多个后缀以英文逗号分割
                        'fileExt'  => 'gif,jpg,png,jpeg'
                    ]
                ])->check( ['image' => $file] );

            $path     = \yzh52521\filesystem\facade\Filesystem::disk( 'public' )->putFile( 'test',$file);
            $url      = \yzh52521\filesystem\facade\Filesystem::disk( 'public' )->url( $path );
            return json( ['path' => $path,'url'  => $url] );
      } catch ( \think\exception\ValidateException $e ) {
            echo $e->getMessage();
     }
```

## 授权

MIT

## 感谢

1. thinkphp
3. overtrue/flysystem-qiniu
4. league/flysystem
5. overtrue/flysystem-cos
