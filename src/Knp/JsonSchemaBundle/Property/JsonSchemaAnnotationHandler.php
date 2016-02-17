<?php

namespace Knp\JsonSchemaBundle\Property;

use Knp\JsonSchemaBundle\Model\Link;
use Knp\JsonSchemaBundle\Model\Property;
use Knp\JsonSchemaBundle\Reflection\ReflectionFactory;
use Doctrine\Common\Annotations\Reader;
use Knp\JsonSchemaBundle\Schema\SchemaGenerator;
use Revinate\LustroFormServiceBundle\Security\Router\Router;
use Symfony\Component\Intl\Intl;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

class JsonSchemaAnnotationHandler implements PropertyHandlerInterface
{
    private $reader;
    private $reflectionFactory;
    private $router;

    public function __construct(Reader $reader, ReflectionFactory $reflectionFactory, RouterInterface $router)
    {
        $this->reader = $reader;
        $this->reflectionFactory = $reflectionFactory;
        $this->router = $router;
    }

    public function handle($className, Property $property)
    {
        foreach ($this->getJsonSchemaConstraintsForClass($className) as $constraint) {
            if ($constraint instanceof \Knp\JsonSchemaBundle\Annotations\PolymorphicType && $constraint->property == $property->getName()) {
                $property->setPolymorphicType($constraint->type);
            }
        }
        foreach ($this->getJsonSchemaConstraintsForProperty($className, $property) as $constraint) {
            if ($constraint instanceof \Knp\JsonSchemaBundle\Annotations\Minimum) {
                $property->setMinimum($constraint->minimum);
            }
            if ($constraint instanceof \Knp\JsonSchemaBundle\Annotations\ExclusiveMinimum) {
                $property->setExclusiveMinimum(true);
            }
            if ($constraint instanceof \Knp\JsonSchemaBundle\Annotations\Maximum) {
                $property->setMaximum($constraint->maximum);
            }
            if ($constraint instanceof \Knp\JsonSchemaBundle\Annotations\ExclusiveMaximum) {
                $property->setExclusiveMaximum(true);
            }
            if ($constraint instanceof \Knp\JsonSchemaBundle\Annotations\Disallow) {
                $property->setDisallowed($constraint->disallowed);
            }
            if ($constraint instanceof \Knp\JsonSchemaBundle\Annotations\Type) {
                $types = (array) $constraint->type;
                $property->setType($types);
            }
            if ($constraint instanceof \Knp\JsonSchemaBundle\Annotations\Locale) {
                $property->setEnumeration(array_keys(Intl::getLocaleBundle()->getLocaleNames()));
            }
            if ($constraint instanceof \Knp\JsonSchemaBundle\Annotations\Title) {
                $property->setTitle($constraint->name);
            }
            if ($constraint instanceof \Knp\JsonSchemaBundle\Annotations\DefaultValue) {
                $property->setDefaultValue($constraint->value);
            }
            if ($constraint instanceof \Knp\JsonSchemaBundle\Annotations\Description) {
                $property->setDescription($constraint->name);
            }
            if ($constraint instanceof \Knp\JsonSchemaBundle\Annotations\Format) {
                $property->setFormat($constraint->format);
            }
            if ($constraint instanceof \Knp\JsonSchemaBundle\Annotations\Options) {
                $property->setOptions($constraint->options);
            }
            if ($constraint instanceof \Knp\JsonSchemaBundle\Annotations\Enum) {
                if ($constraint->callback) {
                    if (!is_callable($choices = $constraint->callback)
                    ) {
                        throw new ConstraintDefinitionException('The Enum constraint expects a valid callback');
                    }
                    $choices = call_user_func($choices);
                } else {
                    $choices = $constraint->enum;
                }
                $property->setEnumeration($choices);
            }
            if ($constraint instanceof \Knp\JsonSchemaBundle\Annotations\Multiple) {
                $property->setMultiple(true);
            }
            if ($constraint instanceof \Knp\JsonSchemaBundle\Annotations\Ignore) {
                $property->setIgnored(true);
            }
            if ($constraint instanceof \Knp\JsonSchemaBundle\Annotations\Required) {
                $property->setRequired($constraint->isRequired);
            }
            if ($constraint instanceof \Knp\JsonSchemaBundle\Annotations\ReadOnly) {
                $property->setReadOnly(true);
            }
            if ($constraint instanceof \Knp\JsonSchemaBundle\Annotations\Object) {
                $property->setObject($constraint->alias);
                $property->setMultiple($constraint->multiple);
            }
            if ($constraint instanceof \Knp\JsonSchemaBundle\Annotations\LinkTo) {
                $link = new Link();
                $link->setHref($this->router->generate($constraint->route, $constraint->params, true));
                $link->setMethod($constraint->method);
                $property->setLink($link);
            }
        }
    }

    private function getJsonSchemaConstraintsForProperty($className, Property $property)
    {
        $reflectionProperties = $this->reflectionFactory->getClassProperties($className);
        foreach($reflectionProperties as $reflectionProperty) {
            if ($reflectionProperty->getName() == $property->getName()) {
                return $this->reader->getPropertyAnnotations($reflectionProperty);
            }
        }
    }

    private function getJsonSchemaConstraintsForClass($className)
    {
        $reflectionClass = $this->reflectionFactory->create($className);
        return $this->reader->getClassAnnotations($reflectionClass);
    }
}
