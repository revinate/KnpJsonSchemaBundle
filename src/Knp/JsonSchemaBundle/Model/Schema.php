<?php

namespace Knp\JsonSchemaBundle\Model;

class Schema implements \JsonSerializable
{
    const TYPE_OBJECT = 'object';
    const SCHEMA_V3 = 'http://json-schema.org/draft-04/schema#';

    private $title;
    private $id;
    private $type;
    private $oneOf = array();
    private $schema;
    private $properties;

    public function getTitle()
    {
        return $this->title;
    }

    public function setTitle($title)
    {
        $this->title = $title;
    }

    public function getProperties()
    {
        return $this->properties;
    }

    public function addProperty(Property $property)
    {
        $this->properties[strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $property->getName()))] = $property;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType($type)
    {
        $this->type = $type;
    }

    public function getSchema()
    {
        return $this->schema;
    }

    public function setSchema($schema)
    {
        $this->schema = $schema;
    }

    /**
     * @return array
     */
    public function getOneOf()
    {
        return $this->oneOf;
    }

    /**
     * @param array $oneOf
     */
    public function setOneOf($oneOf)
    {
        $this->oneOf = $oneOf;
    }

    public function jsonSerialize()
    {
        $serialized = array(
            'title'      => $this->title,
            'type'       => $this->type,
            '$schema'    => $this->schema,
            'id'         => $this->id
        );

        if ($this->oneOf) {
            $serialized['oneOf'] = $this->oneOf;
        } else {
            $properties = array();

            foreach ($this->properties as $i => $property) {
                $properties[$i] = $property->jsonSerialize();
            }
            $serialized['properties'] = $this->properties;
            $requiredProperties = array_keys(array_filter($this->properties, function (Property $property) {
                return $property->isRequired();
            }));

            if (count($requiredProperties) > 0) {
                $serialized['required'] = $requiredProperties;
            }
        }

        return $serialized;
    }
}
