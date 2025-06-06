<?php

/*
 * This file is part of the Doctrine Behavioral Extensions package.
 * (c) Gediminas Morkevicius <gediminas.morkevicius@gmail.com> http://www.gediminasm.org
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gedmo\Sortable\Mapping\Driver;

use Doctrine\Persistence\Mapping\ClassMetadata;
use Gedmo\Exception\InvalidMappingException;
use Gedmo\Mapping\Driver\Xml as BaseXml;

/**
 * This is a xml mapping driver for Sortable
 * behavioral extension. Used for extraction of extended
 * metadata from xml specifically for Sortable
 * extension.
 *
 * @author Lukas Botsch <lukas.botsch@gmail.com>
 *
 * @internal
 */
class Xml extends BaseXml
{
    /**
     * List of types which are valid for position field
     *
     * @var string[]
     */
    private const VALID_TYPES = [
        'int',
        'integer',
        'smallint',
        'bigint',
    ];

    public function readExtendedMetadata($meta, array &$config)
    {
        /**
         * @var \SimpleXmlElement
         */
        $xml = $this->_getMapping($meta->getName());

        if (isset($xml->field)) {
            foreach ($xml->field as $mappingDoctrine) {
                $mapping = $mappingDoctrine->children(self::GEDMO_NAMESPACE_URI);

                $field = $this->_getAttribute($mappingDoctrine, 'name');
                if (isset($mapping->{'sortable-position'})) {
                    if (!$this->isValidField($meta, $field)) {
                        throw new InvalidMappingException("Sortable position field - [{$field}] type is not valid and must be 'integer' in class - {$meta->getName()}");
                    }
                    $config['position'] = $field;

                    $config['startWith'] = 0;
                    if ($this->_isAttributeSet($mapping->{'sortable-position'}, 'startWith')) {
                        $config['startWith'] = $this->_getAttribute($mapping->{'sortable-position'}, 'startWith');
                    }

                    $config['incrementBy'] = 1;
                    if ($this->_isAttributeSet($mapping->{'sortable-position'}, 'incrementBy')) {
                        $config['incrementBy'] = $this->_getAttribute($mapping->{'sortable-position'}, 'incrementBy');
                    }
                }
            }
            $config = $this->readSortableGroups($xml->field, $config, 'name');
        }

        // Search for sortable-groups in association mappings
        if (isset($xml->{'many-to-one'})) {
            $config = $this->readSortableGroups($xml->{'many-to-one'}, $config);
        }

        // Search for sortable-groups in association mappings
        if (isset($xml->{'many-to-many'})) {
            $config = $this->readSortableGroups($xml->{'many-to-many'}, $config);
        }

        if (!$meta->isMappedSuperclass && $config) {
            if (!isset($config['position'])) {
                throw new InvalidMappingException("Missing property: 'position' in class - {$meta->getName()}");
            }
        }

        return $config;
    }

    /**
     * Checks if $field type is valid as Sortable Position field
     *
     * @param ClassMetadata<object> $meta
     * @param string                $field
     *
     * @return bool
     */
    protected function isValidField($meta, $field)
    {
        $mapping = $meta->getFieldMapping($field);

        return $mapping && in_array($mapping->type ?? $mapping['type'], self::VALID_TYPES, true);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function readSortableGroups(\SimpleXMLElement $mapping, array $config, string $fieldAttr = 'field'): array
    {
        foreach ($mapping as $mappingDoctrine) {
            $map = $mappingDoctrine->children(self::GEDMO_NAMESPACE_URI);

            $field = $this->_getAttribute($mappingDoctrine, $fieldAttr);
            if (isset($map->{'sortable-group'})) {
                if (!isset($config['groups'])) {
                    $config['groups'] = [];
                }
                $config['groups'][] = $field;

                $config['sortNullValues'] = true;
                if ($this->_isAttributeSet($map->{'sortable-group'}, 'sortNullValues')) {
                    $config['sortNullValues'] = $this->_getAttribute($map->{'sortable-group'}, 'sortNullValues');
                }
            }
        }

        return $config;
    }
}
