<?php

namespace Knp\JsonSchemaBundle\Annotations;

/**
 * @Annotation
 * @Target({"PROPERTY"})
 */
class LinkTo
{
    /** @var string */
    public $route;
    /** @var array */
    public $params;
    /** @var string */
    public $method = 'get';
}
