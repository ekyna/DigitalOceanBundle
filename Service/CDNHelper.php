<?php

namespace Ekyna\Bundle\DigitalOceanBundle\Service;

use League\Flysystem\AdapterInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;

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
     * @var string
     */
    private $space;

    /**
     * @var array
     */
    private $mimeTypes;


    /**
     * Constructor.
     *
     * @param Filesystem $filesystem
     * @param Api        $api
     * @param string     $space
     * @param array      $mimeTypes
     */
    public function __construct(Filesystem $filesystem, Api $api, string $space, array $mimeTypes = [])
    {
        $this->filesystem = $filesystem;
        $this->api        = $api;
        $this->space      = $space;
        $this->mimeTypes  = $mimeTypes;
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
                'visibility'      => AdapterInterface::VISIBILITY_PUBLIC,
                'ContentEncoding' => 'gzip',
            ];

            $extension = pathinfo($file, PATHINFO_EXTENSION);

            if (isset($this->mimeTypes[$extension])) {
                $options['mimetype'] = $this->mimeTypes[$extension];
            }

            try {
                $result = $this->filesystem->write($path, file_get_contents($file), $options);
            } catch (FilesystemException $e) {
                $result = false;
            }

            if ($callback) {
                $callback($result);
            }
        }
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
        $this->api->purgeSpace($this->space, $files);
    }
}
