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
 * Group annotation for Sortable extension
 *
 * @author Lukas Botsch <lukas.botsch@gmail.com>
 *
 * @Annotation
 *
 * @Target("PROPERTY")
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class SortableGroup implements GedmoAnnotation
{
    use ForwardCompatibilityTrait;

    public bool $sortNullValues = true;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [], bool $sortNullValues = true)
    {
        if ([] !== $data) {
            @trigger_error(sprintf(
                'Passing an array as first argument to "%s()" is deprecated. Use named arguments instead.',
                __METHOD__
            ), E_USER_DEPRECATED);

            $args = func_get_args();

            $this->sortNullValues = $this->getAttributeValue($data, 'sortNullValues', $args, 1, $sortNullValues);

            return;
        }

        $this->sortNullValues = $sortNullValues;
    }
}
