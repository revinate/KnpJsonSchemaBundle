<?php

namespace Knp\JsonSchemaBundle\Annotations;

/**
 * @Annotation
 * @Target({"PROPERTY"})
 */
class Required
{
    /** @var boolean */
    public $isRequired = true;
}
