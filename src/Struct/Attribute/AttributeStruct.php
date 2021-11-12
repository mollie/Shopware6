<?php

namespace Kiener\MolliePayments\Struct\Attribute;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

abstract class AttributeStruct extends Struct
{
    const ADDITIONAL = 'additionalAttributes';
    const CONSTRUCT_KEYS = 'construct_keys';

    /**
     * @param array<mixed>|null $attributes
     * @throws \Exception
     */
    public function __construct(?array $attributes = [])
    {
        /**
         * Use Reflection to ensure that all construct* methods can only be used from within the AttributeStruct,
         * and not be called from outside the scope.
         */
        $this->ensureConstructMethodsAreProtected();

        /**
         * Create a struct to store attributes that don't have properties, but whose data still needs to be kept.
         */
        $additionalAttributes = $this->getArrayStructExtension(self::ADDITIONAL);

        /**
         * Create a struct to store the keys that were available during construct.
         */
        $constructKeys = $this->getArrayStructExtension(self::CONSTRUCT_KEYS);

        if (empty($attributes)) {
            return;
        }

        /**
         * Our class properties are all in camelCase, but our custom fields are stored as snake_case.
         * Initialize a NameConverter to convert between the two.
         *
         * e.g. molliePayments <=> mollie_payments
         */
        $caseConverter = new CamelCaseToSnakeCaseNameConverter();

        /**
         * Loop through all attributes in our array and assign the value to the property using
         */
        foreach ($attributes as $key => $value) {
            /**
             * Save the attributes, so we can determine which properties should be added to the array later
             */
            $constructKeys->offsetSet($key, $value);

            /**
             * Convert the snake_case property name to camelCase for our construct and set methods.
             */
            $camelKey = $caseConverter->denormalize($key);

            /**
             * If a construct method exists for this property, call it to set the value.
             * Construct methods can be used for a one-time setup for the data.
             * For example, converting an array of objects into an AttributeCollection with AttributeStructs
             */
            $constructMethod = 'construct' . ucfirst($camelKey);
            if (method_exists($this, $constructMethod)) {
                $this->$constructMethod($value);
                continue;
            }

            /**
             * If a construct method doesn't exist, try the set method for this property
             */
            $setMethod = 'set' . ucfirst($camelKey);
            if (method_exists($this, $setMethod)) {
                $this->$setMethod($value);
                continue;
            }

            /**
             * If a set method also doesn't exist, set the value on the property itself.
             */
            if (property_exists($this, $camelKey)) {
                $this->$camelKey = $value;
                continue;
            }

            if (property_exists($this, $key)) {
                $this->$key = $value;
                continue;
            }

            /**
             * If the property doesn't exist in this class at all, store the attribute in the additional attribute struct
             * so we don't lose track of it.
             *
             * Using offsetSet() instead of set() because:
             * 1) Shopware is dumb
             * 2) They both have the same functionality
             * 3) They added dumb type-hinting to set
             * https://github.com/shopware/platform/commit/9c4cbe415d33419d9a9c5a3070007bf5cdf0a00e#diff-f21dd8cab48e2967baac4b0d4fb97e6107099f658733615947db47490e31b511R55
             */
            $additionalAttributes->offsetSet($key, $value);
        }
    }

    /**
     * Returns all the properties of this struct as a key-value array
     *
     * @return array<mixed>
     */
    public function getVars(): array
    {
        /**
         * If we have an extension with additional attributes, use that as the starting point
         */
        $data = $this->getArrayStructExtension(self::ADDITIONAL)->all();

        /**
         * Loop through all the properties of this class.
         */
        foreach (parent::getVars() as $key => $value) {
            /**
             * Ignore these properties, don't add them to the data we return
             */
            if (in_array($key, ['extensions'])) {
                continue;
            }

            /**
             * If the value was not set during construct, and the value is still null, don't add this to our data
             */
            if (!$this->getArrayStructExtension(self::CONSTRUCT_KEYS)->has($key) && is_null($value)) {
                continue;
            }

            /**
             * If $value is a Collection, return the inner elements array
             */
            if ($value instanceof Collection) {
                $data[$key] = $value->getElements();
                continue;
            }

            /**
             * If $value is a Struct, return all the properties of the struct
             */
            if ($value instanceof Struct) {
                $data[$key] = $value->getVars();
                continue;
            }

            /**
             * Otherwise just set the value in our data array.
             */
            $data[$key] = $value;
        }

        return $data;
    }

    /**
     * Alias for getVars
     *
     * @return array<mixed>
     */
    public function toArray(): array
    {
        return $this->getVars();
    }

    /**
     * Merges another attribute struct into this attribute struct
     *
     * @param AttributeStruct $struct
     * @return $this
     */
    public function merge(AttributeStruct $struct): self
    {
        /**
         * Our class properties are all in camelCase, but our custom fields are stored as snake_case.
         * Initialize a NameConverter to convert between the two.
         *
         * e.g. molliePayments <=> mollie_payments
         */
        $caseConverter = new CamelCaseToSnakeCaseNameConverter();

        /**
         * Loop through the other struct's properties and set them on this struct,
         * either using the set method for the property, or setting the property directly.
         */
        foreach ($struct->getVars() as $key => $value) {
            /**
             * Convert the snake_case property name to camelCase for our construct and set methods.
             */
            $camelKey = $caseConverter->denormalize($key);

            /**
             * If a set method exists for this property, use it
             */
            $setMethod = 'set' . ucfirst($camelKey);
            if (method_exists($this, $setMethod)) {
                $this->$setMethod($value);
                continue;
            }

            /**
             * Otherwise try to set the property directly
             */
            if (property_exists($this, $camelKey)) {
                $this->$camelKey = $value;
                continue;
            }

            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }

        return $this;
    }

    /**
     * Gets an extension by name, and makes sure it's an ArrayStruct
     * @param string $extensionName
     * @return ArrayStruct
     */
    protected function getArrayStructExtension(string $extensionName): ArrayStruct
    {
        /**
         * If we don't have an extension with this name, create it.
         */
        if (!$this->hasExtension($extensionName)) {
            $this->addExtension($extensionName, new ArrayStruct());
        }

        /**
         * Get the extension
         */
        $extension = $this->getExtension($extensionName);

        if(!$extension instanceof Struct) {
            return new ArrayStruct();
        }

        /**
         * Return it if it's an ArrayStruct
         */
        if ($extension instanceof ArrayStruct) {
            return $extension;
        }

        /**
         * Otherwise, get all the properties of this Struct, create a new ArrayStruct and return it.
         */
        return new ArrayStruct($extension->getVars());
    }

    /**
     * Ensures all construct method are protected, so they can't be called outside this class
     */
    private function ensureConstructMethodsAreProtected(): void
    {
        $reflectionClass = new \ReflectionClass($this);

        foreach ($reflectionClass->getMethods() as $method) {
            /**
             * If the method was not declared in the topmost class, skip it
             */
            if ($method->getDeclaringClass()->getName() !== static::class) {
                continue;
            }

            /**
             * If this method name does not start with "construct", skip it
             */
            if (!str_starts_with($method->getName(), 'construct')) {
                continue;
            }

            /**
             * If the method is protected, continue to the next
             */
            if ($method->isProtected()) {
                continue;
            }

            /**
             * If it fails all the above tests, throw an error.
             */
            // TODO 001 specific exception
            throw new \Exception(sprintf('Assignment method "%s" should be declared protected.', $method->getName()));
        }
    }
}
