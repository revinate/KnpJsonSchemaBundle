<?php

namespace Knp\JsonSchemaBundle\Schema;

use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\Reader;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Mapping\Annotations\DiscriminatorMap;
use Knp\JsonSchemaBundle\Reflection\ReflectionFactory;
use Knp\JsonSchemaBundle\Schema\SchemaRegistry;
use Knp\JsonSchemaBundle\Model\SchemaFactory;
use Knp\JsonSchemaBundle\Model\Schema;
use Knp\JsonSchemaBundle\Model\PropertyFactory;
use Knp\JsonSchemaBundle\Model\Property;
use Knp\JsonSchemaBundle\Property\PropertyHandlerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SchemaGenerator
{
    protected $jsonValidator;
    protected $reflectionFactory;
    protected $schemaRegistry;
    protected $schemaFactory;
    protected $propertyFactory;
    protected $propertyHandlers;
    protected $aliases = array();
    protected $reader;

    public function __construct(
        \JsonSchema\Validator $jsonValidator,
        UrlGeneratorInterface $urlGenerator,
        ReflectionFactory $reflectionFactory,
        SchemaRegistry $schemaRegistry,
        SchemaFactory $schemaFactory,
        PropertyFactory $propertyFactory,
        Reader $reader
    ) {
        $this->jsonValidator     = $jsonValidator;
        $this->urlGenerator      = $urlGenerator;
        $this->reflectionFactory = $reflectionFactory;
        $this->schemaRegistry    = $schemaRegistry;
        $this->schemaFactory     = $schemaFactory;
        $this->propertyFactory   = $propertyFactory;
        $this->propertyHandlers  = new \SplPriorityQueue;
        $this->reader = $reader;
    }

    protected function getUrlToAlias($alias) {
        return $this->urlGenerator->generate('show_json_schema', array('alias' => $alias), true) . '#';
    }

    public function getRefToAlias($alias) {
        return array('$ref' => $this->getUrlToAlias($alias));
    }

    public function getRefToClass($className) {
        return $this->getRefToAlias($this->schemaRegistry->getAlias($className));
    }

    public function getOneOf($discriminatorMap) {
        return array_values(array_map(function($className, $type) {
            return array_merge(array('properties' => array('type' => array('type' => 'string', 'enum' => array($type)))), $this->getRefToClass($className));
        }, $discriminatorMap, array_keys($discriminatorMap)));
    }

    /**
     * @return null|array
     */
    protected function getDiscriminatorMapIfPolymorphic($className)
    {
        $reflectionClass = $this->reflectionFactory->create($className);
        $annotations = $this->reader->getClassAnnotations($reflectionClass);
        foreach($annotations as $annotation) {
            if ($this->reflectionFactory->create($annotation)->getShortName() == 'DiscriminatorMap') {
                /**
                 * @var Annotation $annotation
                 */
                return $annotation->value;
            }
        }
        return null;
    }

    public function getClassAnnotations($className){
        $reflectionClass = $this->reflectionFactory->create($className);
        $annotations = $this->reader->getClassAnnotations($reflectionClass);
        if($parentClass = $reflectionClass->getParentClass()){
            $parentAnnotations = $this->getClassAnnotations($parentClass->getName());
            if(count($parentAnnotations) > 0)
                $annotations = array_merge($annotations, $parentAnnotations);
        }
        return $annotations;
    }

    protected function isLinkingToOtherResource($className) {
        $annotations = $this->getClassAnnotations($className);
        foreach($annotations as $annotation) {
            if (strpos(get_class($annotation), 'Hateoas') === 0) {
                return true;
            }
        }
        return null;
    }


    public function generate($alias)
    {
        $this->aliases[] = $alias;

        $className = $this->schemaRegistry->getNamespace($alias);
        $schema    = $this->schemaFactory->createSchema(ucfirst($alias));

        $schema->setId($this->getUrlToAlias($alias));
        $schema->setSchema(Schema::SCHEMA_V3);
        $schema->setType(Schema::TYPE_OBJECT);
        $discriminatorMap = $this->getDiscriminatorMapIfPolymorphic($className);
        if (!empty($discriminatorMap) && !in_array($className, $discriminatorMap)) {
            $schema->setOneOf($this->getOneOf($discriminatorMap));
        } else {
            foreach ($this->reflectionFactory->getClassProperties($className) as $property) {
                $property = $this->propertyFactory->createProperty($property->name);
                $this->applyPropertyHandlers($className, $property);
                if (!$property->isIgnored() && $property->hasType(Property::TYPE_OBJECT) && $property->getObject() && is_null($property->getLink())) {
                    $property->setSchema(
                        $this->generate($property->getObject())
                    );
                }

                if (!$property->isIgnored()) {
                    $schema->addProperty($property);
                }
            }
            if ($this->isLinkingToOtherResource($className)) {
                $property = $this->propertyFactory->createProperty('_links');
                $property->setType('object');
                $property->setReadOnly(true);
                $schema->addProperty($property);
            }
        }

        if (false === $this->validateSchema($schema)) {
            $message = "Generated schema is invalid. Please report on" .
                "https://github.com/KnpLabs/KnpJsonSchemaBundle/issues/new.\n" .
                "The following problem(s) were detected:\n";
            foreach ($this->jsonValidator->getErrors() as $error) {
                $message .= sprintf("[%s] %s\n", $error['property'], $error['message']);
            }
            $message .= sprintf("Json schema:\n%s", json_encode($schema, JSON_PRETTY_PRINT));
            throw new \Exception($message);
        }

        return $schema;
    }

    public function registerPropertyHandler(PropertyHandlerInterface $handler, $priority)
    {
        $this->propertyHandlers->insert($handler, $priority);
    }

    public function getPropertyHandlers()
    {
        return array_values(iterator_to_array(clone $this->propertyHandlers));
    }

    /**
     * Validate a schema against the meta-schema provided by http://json-schema.org/schema
     *
     * @param Schema $schema a json schema
     *
     * @return boolean
     */
    private function validateSchema(Schema $schema)
    {
        $this->jsonValidator->check(
            json_decode(json_encode($schema->jsonSerialize())),
            json_decode(file_get_contents($schema->getSchema()))
        );

        return $this->jsonValidator->isValid();
    }

    private function applyPropertyHandlers($className, Property $property)
    {
        $propertyHandlers = clone $this->propertyHandlers;
        while ($propertyHandlers->valid()) {
            $handler = $propertyHandlers->current();
            $handler->handle($className, $property);
            $propertyHandlers->next();
        }
    }
}
