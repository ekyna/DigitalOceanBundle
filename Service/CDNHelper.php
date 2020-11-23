<?php

namespace Ekyna\Bundle\DigitalOceanBundle\Service;

use League\Flysystem\AdapterInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use LogicException;

/**
 * Class SpaceHelper
 * @package Ekyna\Bundle\DigitalOceanBundle\Service
 * @author  Étienne Dauvergne <contact@ekyna.com>
 */
class CDNHelper
{
    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var Api
     */
    private $api;

    /**
     * @var array
     */
    private $config;


    /**
     * Constructor.
     *
     * @param Filesystem $filesystem
     * @param Api        $api
     * @param array      $config
     */
    public function __construct(Filesystem $filesystem, Api $api, array $config)
    {
        $this->filesystem = $filesystem;
        $this->api = $api;

        $this->config = array_replace([
            'space'       => null,
            'mime_types ' => [],
            'gzip'        => false,
        ], $config);

        if (empty($this->config['space'])) {
            throw new LogicException("Config entry 'space' must be configured.");
        }
    }

    /**
     * Lists the files (or directories) int he given path.
     *
     * @param string $path
     * @param bool   $dir
     *
     * @return array
     */
    public function list(string $path, bool $dir = false): array
    {
        $paths = [];

        $objects = $this->filesystem->listContents($path, true);

        foreach ($objects as $object) {
            if (!$dir xor $object['type'] === 'dir') {
                $paths[] = $object['path'];
            }
        }

        return $paths;
    }

    /**
     * Deploys the given files (local file => CDN path).
     *
     * @param array         $files
     * @param callable|null $callback
     */
    public function deploy(array $files, callable $callback = null): void
    {
        foreach ($files as $file => $path) {
            $options = [
                'visibility' => AdapterInterface::VISIBILITY_PUBLIC,
            ];

            $extension = pathinfo($file, PATHINFO_EXTENSION);

            if (isset($this->config['mime_types'][$extension])) {
                $options['mimetype'] = $this->config['mime_types'][$extension];
            }

            $content = file_get_contents($file);

            if ($this->shouldGzip($extension)) {
                $content = gzencode($content, 6);
                $options['ContentEncoding'] = 'gzip';
            }

            try {
                $result = $this->filesystem->write($path, $content, $options);
            } catch (FilesystemException $e) {
                $result = false;
            }

            if ($callback) {
                $callback($result);
            }
        }
    }

    /**
     * Returns whether the content should be gzipped for the given extension.
     *
     * @param string $extension
     *
     * @return bool
     */
    private function shouldGzip(string $extension): bool
    {
        if (is_bool($this->config['gzip']) && $this->config['gzip']) {
            return true;
        }

        if (is_array($this->config['gzip']) && in_array($extension, $this->config['gzip'], true)) {
            return true;
        }

        return false;
    }

    /**
     * Deletes the given files.
     *
     * @param array         $paths
     * @param callable|null $callback
     */
    public function deleteFiles(array $paths, callable $callback = null): void
    {
        foreach ($paths as $path) {
            try {
                $result = $this->filesystem->delete($path);
            } catch (FilesystemException $e) {
                $result = false;
            }

            if ($callback) {
                $callback($result);
            }
        }
    }

    /**
     * Deletes the given directories.
     *
     * @param array         $paths
     * @param callable|null $callback
     */
    public function deleteDirectories(array $paths, callable $callback = null): void
    {
        foreach ($paths as $path) {
            try {
                $result = $this->filesystem->deleteDir($path);
            } catch (FilesystemException $e) {
                $result = false;
            }

            if ($callback) {
                $callback($result);
            }
        }
    }

    /**
     * Purges the CDN cache.
     *
     * @param array $files
     */
    public function purge(array $files = []): void
    {
        $this->api->purgeSpace($this->config['space'], $files);
    }
}
