<?php

declare(strict_types=1);

namespace Ekyna\Bundle\DigitalOceanBundle\Exception;

use InvalidArgumentException;

/**
 * Class SpaceNotFoundException
 * @package Ekyna\Bundle\DigitalOceanBundle\Exception
 * @author  Étienne Dauvergne <contact@ekyna.com>
 */
class SpaceNotFoundException extends InvalidArgumentException implements ExceptionInterface
{
    public function __construct(string $name)
    {
        parent::__construct("Space '$name' not found.");
    }
}
