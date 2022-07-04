<?php

declare(strict_types=1);

namespace Ekyna\Bundle\DigitalOceanBundle\Service;

use InvalidArgumentException;

/**
 * Class Registry
 * @package Ekyna\Bundle\DigitalOceanBundle\Service
 * @author  Étienne Dauvergne <contact@ekyna.com>
 */
class Registry
{
    public function __construct(private readonly array $spaces)
    {
    }

    /**
     * Finds a space by its name.
     */
    public function get(string $name): array
    {
        foreach ($this->spaces as $space) {
            if ($space['name'] === $name) {
                return $space;
            }
        }

        throw new InvalidArgumentException("Space '$name' is not defined.");
    }
}
