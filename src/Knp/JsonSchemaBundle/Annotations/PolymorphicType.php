<?php

namespace Knp\JsonSchemaBundle\Annotations;

/**
 * @Annotation
 * @Target({"CLASS"})
 */
class PolymorphicType
{
    public $property;
    public $type;
}
