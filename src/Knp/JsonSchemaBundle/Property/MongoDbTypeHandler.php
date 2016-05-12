<?php

namespace Knp\JsonSchemaBundle\Property;

use Doctrine\Common\Inflector\Inflector;
use Doctrine\Common\Persistence\Mapping\MappingException;
use Knp\JsonSchemaBundle\Model\Link;
use Knp\JsonSchemaBundle\Model\Property;
use Knp\JsonSchemaBundle\Schema\SchemaRegistry;
use Revinate\LustroFormServiceBundle\Document\Attributes\ResourceTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\Guess\TypeGuess;
use Symfony\Component\Form\FormTypeGuesserInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class MongoDbTypeHandler implements PropertyHandlerInterface
{
    /**
     * @var ContainerInterface
     */
    private $containerInterface;

    public function __construct(ContainerInterface $containerInterface)
    {
        $this->containerInterface = $containerInterface;
    }

    public function handle($className, Property $property)
    {
        try {
            $metadata = $this->containerInterface->get('doctrine.odm.mongodb.document_manager')->getClassMetadata($className);
        } catch(MappingException $e) {
            return; //entity doens't support mongo reflection
        }
        if ($metadata->hasReference($property->getName())) {
            $targetDocumentClass = $this->containerInterface->get('doctrine.odm.mongodb.document_manager')->getClassMetadata($metadata->fieldMappings[$property->getName()]['targetDocument']);
            $targetDocumentType = $targetDocumentClass->isIdGeneratorIncrement() ? Property::TYPE_INTEGER : Property::TYPE_STRING;
            $isCollection = $metadata->isCollectionValuedReference($property->getName());
            $property->setType($targetDocumentType);
            $link = new Link();
            /**
             * @var ResourceTypeInterface $targetDocument
             */
            $targetDocument = $targetDocumentClass->getReflectionClass()->newInstance();
            $resourceName = $targetDocument->getResourceType()->getValue();
            $link->setHref($this->containerInterface->get('router')->generate("get_$resourceName", array('id' => '{$}' ), \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL));
            $property->setLink($link);
        } else if ($metadata->hasEmbed($property->getName()) && !empty($metadata->fieldMappings[$property->getName()]['discriminatorMap'])) {
            $property->setType(Property::TYPE_OBJECT);
            if ($property->getPolymorphicType()) {
                $property->setObject($this->containerInterface->get('json_schema.registry')->getAlias($metadata->fieldMappings[$property->getName()]['discriminatorMap'][$property->getPolymorphicType()]));
            } else {
                $property->setOneOf($this->containerInterface->get('json_schema.generator')->getOneOf($metadata->fieldMappings[$property->getName()]['discriminatorMap']));
            }
        } else if (is_array($metadata->getIdentifierFieldNames()) && !empty($metadata->getIdentifierFieldNames()) && $metadata->getIdentifierFieldNames()[0] == $property->getName()) {
            //$property->setIgnored(true);
            $property->setType($metadata->isIdGeneratorIncrement() ? Property::TYPE_INTEGER : Property::TYPE_STRING);
        }
    }
}
