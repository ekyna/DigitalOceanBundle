ekyna/digital-ocean-bundle
==========

Deploy assets to Digital Ocean space CDN    

### Installation

Install using composer:

```bash
composer require ekyna/digital-ocean-bundle
```

Register the bundle:

```php
class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = [
            // ...
            new Ekyna\Bundle\DigitalOceanBundle\EkynaDigitalOceanBundle(),
        ];
    }
}
```

Add the configuration:

```yaml
ekyna_digital_ocean:
    api:
        token: YOUR_API_TOKEN
    spaces:
        -
            name: my-do-cdn # Must match space name on Digital Ocean 
            region: ams3
            key: SPACE_ACCESS_KEY
            secret: SPACE_ACCESS_SECRET
    usage:
        bundles: my-do-cdn # The space to use for assets deployment
```


### Usage

Spaces storages are available as [League\Flysystem\Filesystem](https://github.com/thephpleague/flysystem/blob/1.x/src/Filesystem.php) (v1) services :

```xml
<!-- You can inject 'my-do-cdn' filesystem service -->
<service id="Acme\Some\Service">
    <argument type="service">ekyna_digital_ocean.my_do_cdn.filesystem</argument>
</service>
```

### Commands

You can deploy bundles assets to your space CDN by running the following command:

```php bin/console ekyna:digital-ocean:assets:deploy```

_Warning: it purge the entire CDN cache._ 
