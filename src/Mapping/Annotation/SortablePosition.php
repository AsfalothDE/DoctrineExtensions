<?php

/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gedmo\Mapping\Annotation;

use Doctrine\Common\Annotations\Annotation;
use Gedmo\Mapping\Annotation\Annotation as GedmoAnnotation;

/**
 * Position annotation for Sortable extension
 *
 * @author Lukas Botsch <lukas.botsch@gmail.com>
 *
 * @Annotation
 *
 * @Target("PROPERTY")
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class SortablePosition implements GedmoAnnotation
{
    use ForwardCompatibilityTrait;

    /**
     * @phpstan-var int<0, max>
     */
    public int $startWith = 0;

    /**
     * @phpstan-var positive-int
     */
    public int $incrementBy = 1;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [], int $startWith = 0, int $incrementBy = 1)
    {
        if ([] !== $data) {
            @trigger_error(sprintf(
                'Passing an array as first argument to "%s()" is deprecated. Use named arguments instead.',
                __METHOD__
            ), E_USER_DEPRECATED);

            $args = func_get_args();

            $this->startWith = $this->getAttributeValue($data, 'startWith', $args, 1, $startWith);
            $this->incrementBy = $this->getAttributeValue($data, 'incrementBy', $args, 2, $incrementBy);

            return;
        }

        $this->startWith = $startWith;
        $this->incrementBy = $incrementBy;
    }
}
