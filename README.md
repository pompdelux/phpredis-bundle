# PHPRedisBundle

This bundle brings [phpredis](https://github.com/nicolasff/phpredis) to your [Symfony2](http://symfony.com/) app.

It allows you to easily create multiple clients pointing to different databases and/or servers.

## Install:

1. Add PHPRedisBundleBundle to your dependencies:

        // composer.json
        {
            // ...
            "require": {
                // ...
                "pompdelux/phpredis-bundle": "1.*"
            }
        }
2. Use Composer to download and install the bundle:

        $ php composer.phar update pompdelux/phpredis-bundle
3. Register the bundle in your application:

        // app/AppKernel.php
        class AppKernel extends Kernel
        {
            // ...
            public function registerBundles()
            {
                $bundles = array(
                    // ...
                    new Pompdelux\PHPRedisBundle\PHPRedisBundle(),
                );
            }
        }

4. Add the configuration needed to use the bundle:

        // config.yml
        php_redis:
            class:
                service_name:
                    host:     127.0.0.1
                    port:     6379
                    prefix:   ''
                    database: 0
                    timeout:  0
                    auth:     null
                    skip_env: false
                some_other_service:
                    host:      localhost
                    port:      6379
                    ....

Two things to note:

1. If `skip_env` is not set to `false` the environment will be part of the prefix for all keys.
2. `Redis::SERIALIZER_PHP` will be used as serializer unless you override it via `setOption()`


## Usage:

```php
$redis = $this->container->get('pdl.phpredis.service_name');
$redis->set('key', 'value');
echo $redis->get('key');
```
