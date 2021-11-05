<?php

namespace Kiener\MolliePayments\Struct\Attribute;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

abstract class AttributeStruct extends Struct
{
    public function __construct(?array $attributes = [])
    {
        $this->reflect();

        $this->addExtension('additionalAttributes', new ArrayStruct());

        if (empty($attributes)) {
            return;
        }

        $caseConverter = new CamelCaseToSnakeCaseNameConverter();

        foreach ($attributes as $key => $value) {
            $camelKey = $caseConverter->denormalize($key);

            $assignMethod = 'assign' . ucfirst($camelKey);
            if (method_exists($this, $assignMethod)) {
                $this->$assignMethod($value);
                continue;
            }

            $setMethod = 'set' . ucfirst($camelKey);
            if (method_exists($this, $setMethod)) {
                $this->$setMethod($value);
                continue;
            }

            if (property_exists($this, $camelKey)) {
                $this->$camelKey = $value;
                continue;
            }

            $this->getExtension('additionalAttributes')->set($key, $value);
        }
    }

    public function getVars(): array {
        $data = $this->hasExtension('additionalAttributes')
            ? $this->getExtension('additionalAttributes')->all()
            : [];

        foreach(parent::getVars() as $key => $value) {
            if(in_array($key, ['extensions'])) {
                continue;
            }

            if($value instanceof Collection) {
                $data[$key] = $value->getElements();
                continue;
            }

            if($value instanceof Struct) {
                $data[$key] = $value->getVars();
                continue;
            }

            $data[$key] = $value;
        }

        return $data;
    }

    public function toArray(): array {
        return $this->getVars();
    }

    public function merge(AttributeStruct $struct): self
    {
        foreach($struct->getVars() as $key => $value) {
            $setMethod = 'set' . ucfirst($key);
            if (method_exists($this, $setMethod)) {
                $this->$setMethod($value);
            } elseif (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }

        return $this;
    }

    private function reflect() {
        $reflectionClass = new \ReflectionClass($this);

        foreach($reflectionClass->getMethods() as $method) {
            if($method->getDeclaringClass()->getName() !== static::class) {
                continue;
            }

            if(!str_starts_with($method->getName(), 'assign')) {
                continue;
            }

            if($method->isProtected()) {
                continue;
            }

            throw new \Exception(sprintf('Assignment method "%s" should be declared protected.', $method->getName()));
        }
    }
}
