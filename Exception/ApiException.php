<?php

declare(strict_types=1);

namespace Ekyna\Bundle\DigitalOceanBundle\Exception;

use RuntimeException;

/**
 * Class ApiException
 * @package Ekyna\Bundle\DigitalOceanBundle\Exception
 * @author  Étienne Dauvergne <contact@ekyna.com>
 */
class ApiException extends RuntimeException implements ExceptionInterface
{

}
