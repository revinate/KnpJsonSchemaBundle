<?php

namespace Knp\JsonSchemaBundle\Property;

use Knp\JsonSchemaBundle\Model\Property;
use Symfony\Component\Form\Guess\TypeGuess;
use Symfony\Component\Form\FormTypeGuesserInterface;

class FormTypeGuesserHandler implements PropertyHandlerInterface
{
    private $guesser;

    public function __construct(FormTypeGuesserInterface $guesser)
    {
        $this->guesser = $guesser;
    }

    public function handle($className, Property $property)
    {
        if ($type = $this->guesser->guessType($className, $property->getName())) {
            $property->setType($this->getJsonSchemaType($type));
        }

        if ($required = $this->guesser->guessRequired($className, $property->getName())) {
            $property->setRequired($required->getValue());
        }

        if ($pattern = $this->guesser->guessPattern($className, $property->getName())) {
            $property->setPattern($pattern->getValue());
        }
    }

    private function getJsonSchemaType(TypeGuess $type)
    {
        switch ($type->getType()) {
            case 'entity':
                return 'object';
            case 'collection':
                return 'array';
            case 'checkbox':
                return 'boolean';
            case 'number':
                return 'number';
            case 'integer':
                return 'integer';
            case 'date':
            case 'datetime':
            case 'text':
            case 'country':
            case 'email':
            case 'file':
            case 'language':
            case 'locale':
            case 'time':
            case 'url':
                return 'string';
        }
    }
}
