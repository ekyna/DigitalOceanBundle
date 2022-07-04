<?php

declare(strict_types=1);

namespace Ekyna\Bundle\DigitalOceanBundle\Service;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\Visibility;
use LogicException;

/**
 * Class SpaceHelper
 * @package Ekyna\Bundle\DigitalOceanBundle\Service
 * @author  Étienne Dauvergne <contact@ekyna.com>
 */
class CDNHelper
{
    private readonly array $config;

    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly Api        $api,
        array                       $config
    ) {
        $this->config = array_replace([
            'space'       => null,
            'mime_types ' => [],
            'gzip'        => false,
        ], $config);

        if (empty($this->config['space'])) {
            throw new LogicException("Config entry 'space' must be configured.");
        }
    }

    public function getSpace(): string
    {
        return $this->config['space'];
    }

    /**
     * Lists the files (or directories) in the given path.
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
     */
    public function deploy(array $files, callable $callback = null): void
    {
        foreach ($files as $file => $path) {
            $options = [
                'visibility' => Visibility::PUBLIC,
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
                $this->filesystem->write($path, $content, $options);
                $result = true;
            } catch (FilesystemException) {
                $result = false;
            }

            if ($callback) {
                $callback($result);
            }
        }
    }

    /**
     * Returns whether the content should be gzipped for the given extension.
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
     */
    public function deleteFiles(array $paths, callable $callback = null): void
    {
        foreach ($paths as $path) {
            try {
                $this->filesystem->delete($path);
                $result = true;
            } catch (FilesystemException) {
                $result = false;
            }

            if ($callback) {
                $callback($result);
            }
        }
    }

    /**
     * Deletes the given directories.
     */
    public function deleteDirectories(array $paths, callable $callback = null): void
    {
        foreach ($paths as $path) {
            try {
                $this->filesystem->deleteDirectory($path);
                $result = true;
            } catch (FilesystemException) {
                $result = false;
            }

            if ($callback) {
                $callback($result);
            }
        }
    }

    /**
     * Purges the CDN cache.
     */
    public function purge(array $files = []): void
    {
        $this->api->purgeSpace($this->config['space'], $files);
    }
}
