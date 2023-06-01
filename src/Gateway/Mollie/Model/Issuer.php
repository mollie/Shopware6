<?php

namespace Kiener\MolliePayments\Gateway\Mollie\Model;

class Issuer
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $image1x;

    /**
     * @var string
     */
    private $image2x;

    /**
     * @var string
     */
    private $svg;


    /**
     * @param string $id
     * @param string $name
     * @param string $image1x
     * @param string $image2x
     * @param string $svg
     */
    public function __construct(string $id, string $name, string $image1x, string $image2x, string $svg)
    {
        $this->id = $id;
        $this->name = $name;
        $this->image1x = $image1x;
        $this->image2x = $image2x;
        $this->svg = $svg;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getImage1x(): string
    {
        return $this->image1x;
    }

    /**
     * @return string
     */
    public function getImage2x(): string
    {
        return $this->image2x;
    }

    /**
     * @return string
     */
    public function getSvg(): string
    {
        return $this->svg;
    }
}
