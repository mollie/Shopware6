<?php declare(strict_types=1);

namespace MolliePayments\Tests\Fakes\Attribute;

use Kiener\MolliePayments\Struct\Attribute\AttributeStruct;

class FakeAttributeStruct extends AttributeStruct
{
    /**
     * @var ?string
     */
    protected $string;

    /**
     * @var ?int
     */
    protected $int;

    /**
     * @var ?bool
     */
    protected $bool;

    /**
     * @var ?string
     */
    protected $camelCaseAttribute;

    /**
     * @var ?string
     */
    protected $snake_case_attribute;

    /**
     * @return string|null
     */
    public function getString(): ?string
    {
        return $this->string;
    }

    /**
     * @param string|null $string
     */
    public function setString(?string $string): void
    {
        $this->string = $string;
    }

    /**
     * @return int|null
     */
    public function getInt(): ?int
    {
        return $this->int;
    }

    /**
     * @param int|null $int
     */
    public function setInt(?int $int): void
    {
        $this->int = $int;
    }

    /**
     * @return bool|null
     */
    public function getBool(): ?bool
    {
        return $this->bool;
    }

    /**
     * @param bool|null $bool
     */
    public function setBool(?bool $bool): void
    {
        $this->bool = $bool;
    }

    /**
     * @return string|null
     */
    public function getCamelCaseAttribute(): ?string
    {
        return $this->camelCaseAttribute;
    }

    /**
     * @param string|null $camelCaseAttribute
     */
    public function setCamelCaseAttribute(?string $camelCaseAttribute): void
    {
        $this->camelCaseAttribute = $camelCaseAttribute;
    }

    /**
     * @return string|null
     */
    public function getSnakeCaseAttribute(): ?string
    {
        return $this->snake_case_attribute;
    }

    /**
     * @param string|null $snake_case_attribute
     */
    public function setSnakeCaseAttribute(?string $snake_case_attribute): void
    {
        $this->snake_case_attribute = $snake_case_attribute;
    }
}
