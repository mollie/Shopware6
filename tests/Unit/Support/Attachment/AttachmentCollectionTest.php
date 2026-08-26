<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Support\Attachment;

use Mollie\Shopware\Component\Support\Attachment\Attachment;
use Mollie\Shopware\Component\Support\Attachment\AttachmentCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AttachmentCollection::class)]
final class AttachmentCollectionTest extends TestCase
{
    public function testEveryAttachmentIsHandedOverWithContentFileNameAndMimeType(): void
    {
        $collection = new AttachmentCollection();
        $collection->add(new Attachment('log-content', 'mollie.log', 'text/plain'));
        $collection->add(new Attachment('csv-content', 'orders.csv', 'text/csv'));

        $this->assertSame(
            [
                ['content' => 'log-content', 'fileName' => 'mollie.log', 'mimeType' => 'text/plain'],
                ['content' => 'csv-content', 'fileName' => 'orders.csv', 'mimeType' => 'text/csv'],
            ],
            $collection->toArray()
        );
    }

    public function testASupportMailWithoutAttachmentsCarriesAnEmptyList(): void
    {
        $collection = new AttachmentCollection();

        $this->assertSame([], $collection->toArray());
    }

    public function testAttachmentsKeepTheOrderTheyWereAddedIn(): void
    {
        $collection = new AttachmentCollection();
        $collection->add(new Attachment('first', 'a.txt', 'text/plain'));
        $collection->add(new Attachment('second', 'b.txt', 'text/plain'));

        $this->assertSame(['a.txt', 'b.txt'], array_column($collection->toArray(), 'fileName'));
    }
}
