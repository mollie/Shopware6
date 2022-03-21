<?php

namespace MolliePayments\Tests\Service\Logger\Processors;

use Kiener\MolliePayments\Service\Logger\Processors\AnonymousWebProcessor;
use Kiener\MolliePayments\Service\Logger\Services\URLAnonymizer;
use Kiener\MolliePayments\Service\Logger\Services\URLAnonymizerInterface;
use Monolog\Processor\ProcessorInterface;
use PHPUnit\Framework\TestCase;

class AnonymousWebProcessorTest extends TestCase
{

    /**
     * @var AnonymousWebProcessor
     */
    private $subject;

    /**
     * @var ProcessorInterface
     */
    private $webProcessor;

    /**
     * @var URLAnonymizer
     */
    private $urlAnonymizer;

    public function setUp(): void
    {
        $this->webProcessor = $this->createMock(ProcessorInterface::class);
        $this->urlAnonymizer = $this->createMock(URLAnonymizerInterface::class);

        $this->subject = new AnonymousWebProcessor(
            $this->webProcessor,
            $this->urlAnonymizer
        );
    }

    public function testInvokeCanReturnArrayWithIpV4(): void
    {
        $url = 'http://someurl';
        $some_array = [
            'extra' => [
                'ip' => '127.0.0.1',
                'url' => $url
            ]
        ];

        $this->webProcessor->expects($this->once())->method('__invoke')
            ->with([])
            ->willReturn($some_array);

        $this->urlAnonymizer->expects($this->once())->method('anonymize')
            ->with($url)
            ->willReturn($url);

        $this->assertSame(
            [
                'extra' => [
                    'ip' => '127.0.0.0',
                    'url' => $url
                ]
            ],
            $this->subject->__invoke([])
        );
    }

    public function testInvokeCanReturnArrayWithIpV6(): void
    {
        $url = 'http://someurl';
        $some_array = [
            'extra' => [
                'ip' => '2001:0db8:85a3:08d3::0370:7344',
                'url' => $url
            ]
        ];

        $this->webProcessor->expects($this->once())->method('__invoke')
            ->with([])
            ->willReturn($some_array);

        $this->urlAnonymizer->expects($this->once())->method('anonymize')
            ->with($url)
            ->willReturn($url);

        $this->assertSame(
            [
                'extra' => [
                    'ip' => '2001:db8:85a3:8d3::',
                    'url' => $url
                ]
            ],
            $this->subject->__invoke([])
        );
    }
}
