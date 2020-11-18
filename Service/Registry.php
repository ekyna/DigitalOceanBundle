<?php

namespace Ekyna\Bundle\DigitalOceanBundle\Service;

use InvalidArgumentException;

/**
 * Class Registry
 * @package Ekyna\Bundle\DigitalOceanBundle\Service
 * @author  Étienne Dauvergne <contact@ekyna.com>
 */
class Registry
{
    /**
     * @var array
     */
    private $spaces;


    /**
     * Constructor.
     *
     * @param array $spaces
     */
    public function __construct(array $spaces)
    {
        $this->spaces = $spaces;
    }

    /**
     * Finds a space by its name.
     *
     * @param string $name
     *
     * @return array
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
