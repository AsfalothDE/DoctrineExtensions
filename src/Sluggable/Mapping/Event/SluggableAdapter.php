<?php

/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gedmo\Sluggable\Mapping\Event;

use Doctrine\Persistence\Mapping\ClassMetadata;
use Gedmo\Mapping\Event\AdapterInterface;
use Gedmo\Sluggable\SluggableListener;

/**
 * Doctrine event adapter for the Sluggable extension.
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @phpstan-import-type SluggableConfiguration from SluggableListener
 * @phpstan-import-type SlugConfiguration from SluggableListener
 */
interface SluggableAdapter extends AdapterInterface
{
    /**
     * Loads the similar slugs for a managed object.
     *
     * @param object                $object
     * @param ClassMetadata<object> $meta
     * @param string                $slug
     *
     * @phpstan-param SlugConfiguration $config
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSimilarSlugs($object, $meta, array $config, $slug);

    /**
     * Replace part of a slug on all objects matching the target pattern.
     *
     * @param object $object
     * @param string $target
     * @param string $replacement
     *
     * @phpstan-param SlugConfiguration $config
     *
     * @return int the number of updated records
     */
    public function replaceRelative($object, array $config, $target, $replacement);

    /**
     * Replace part of a slug on all objects matching the target pattern
     * and having a relation to the managed object.
     *
     * @param object $object
     * @param string $target
     * @param string $replacement
     *
     * @phpstan-param SluggableConfiguration $config
     *
     * @return int the number of updated records
     */
    public function replaceInverseRelative($object, array $config, $target, $replacement);
}
