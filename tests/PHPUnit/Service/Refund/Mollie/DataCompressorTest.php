<?php

namespace MolliePayments\Tests\Service\Refund\Mollie;

use Kiener\MolliePayments\Service\Refund\Mollie\DataCompressor;
use PHPUnit\Framework\TestCase;

class DataCompressorTest extends TestCase
{
    /**
     * @var DataCompressor
     */
    private $comporessor;


    /**
     * @return void
     */
    public function setUp(): void
    {
        $this->comporessor = new DataCompressor();
    }

    /**
     * This test verifies that a valid ID with length 32 is
     * correctly compressed and returned.
     *
     * @return void
     */
    public function testCompressID()
    {
        $value = $this->comporessor->compress('2a88d9b59d474c7e869d8071649be43c');

        $this->assertEquals(8, strlen($value));
        $this->assertEquals('2a88e43c', $value);
    }


    /**
     * This test verifies that invalid IDs with length < 32 are
     * not compressed at all.
     *
     * @return void
     */
    public function testInvalidIDsAreNotCompressed()
    {
        # string with length 31
        $value = $this->comporessor->compress('2a88d9b59d474c7e869d8071649be43');

        $this->assertEquals(31, strlen($value));
        $this->assertEquals('2a88d9b59d474c7e869d8071649be43', $value);
    }
}
