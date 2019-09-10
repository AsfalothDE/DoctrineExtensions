<?php

namespace Gedmo\Mapping\Annotation;

use Doctrine\Common\Annotations\Annotation;

/**
 * Position annotation for Sortable extension
 *
 * @author Lukas Botsch <lukas.botsch@gmail.com>
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 *
 * @Annotation
 * @Target("PROPERTY")
 */
final class Sortable extends Annotation
{
    /**
     * @var array<string>
     */
    public $groups = [];

    /**
     * @var int number to start with
     */
    public $startWith = 0;

    /**
     * @var int Increment counter by
     */
    public $incrementBy = 1;
}
