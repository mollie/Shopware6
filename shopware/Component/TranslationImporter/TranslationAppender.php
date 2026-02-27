<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\TranslationImporter;

final class TranslationAppender implements AppenderInterface
{
    public function append(\DOMDocument $config, string $key, string $text, string $languageCode): AppenderResult
    {
        $domXpath = new \DOMXPath($config);

        $keyParts = explode('.', $key);
        $lastKeyPart = array_pop($keyParts);

        $path = '/';
        foreach ($keyParts as $keyPart) {
            $keyPart = mb_strtolower($keyPart);

            $searchParts = [
                'following-sibling::' . $keyPart, // search for the HTMLElement
                'following::' . $keyPart, // search for the HTMLElement
                "following::title[translate(normalize-space(text()),'ABCDEFGHIJKLMNOPQRSTUVWXYZ ','abcdefghijklmnopqrstuvwxyz')='" . $keyPart . "']", // exact match on title
                "following::name[translate(normalize-space(text()),'ABCDEFGHIJKLMNOPQRSTUVWXYZ ','abcdefghijklmnopqrstuvwxyz')='" . $keyPart . "']", // exact match on name
                'descendant::' . $keyPart, // search for the HTMLElement
                "descendant::title[translate(normalize-space(text()),'ABCDEFGHIJKLMNOPQRSTUVWXYZ ','abcdefghijklmnopqrstuvwxyz')='" . $keyPart . "']", // exact match on title
                "descendant::name[translate(normalize-space(text()),'ABCDEFGHIJKLMNOPQRSTUVWXYZ ','abcdefghijklmnopqrstuvwxyz')='" . $keyPart . "']", // exact match on name
            ];

            if (is_numeric($keyPart)) {
                // convert options.0. to options/*[0]
                $searchParts = [
                    '*[' . $keyPart . ']',
                ];
            }

            foreach ($searchParts as $searchPart) {
                $result = $domXpath->query($path . '/' . $searchPart);
                if ($result === false) {
                    continue;
                }
                if ($result->count() === 0) {
                    continue;
                }
                $path .= '/' . $searchPart;
                break;
            }
        }

        $replaceXpathQuery = $path . '/' . $lastKeyPart . '[@lang="' . $languageCode . '"]';
        $domElement = $domXpath->query($replaceXpathQuery);
        if ($domElement !== false && $domElement->count() === 0) {
            $replaceXpathQuery = $path . '/following-sibling::' . $lastKeyPart . '[@lang="' . $languageCode . '"]';
            $domElement = $domXpath->query($replaceXpathQuery);

            if ($domElement !== false && $domElement->count() === 0) {
                $replaceXpathQuery = $path . '/following::' . $lastKeyPart . '[@lang="' . $languageCode . '"]';
                $domElement = $domXpath->query($replaceXpathQuery);
            }
        }

        if ($domElement !== false && $domElement->count() === 1) {
            $oldElement = $domElement->item(0);

            if ($oldElement instanceof \DOMNode) {
                $textElement = $oldElement->firstChild;
                if ($textElement instanceof \DOMText) {
                    $textElement->data = $text;
                }
            }

            return new AppenderResult(sprintf('Replace "%s" with the key %s', $text, $key));
        }

        $domElement = $domXpath->query($path);

        // we expect to find exactly one node, not multiple
        if ($domElement === false || $domElement->count() === 0) {
            return new AppenderResult(sprintf('Failed to find entry for key "%s" with the path "%s"', $key, $path), AppenderResult::STATUS_ERROR);
        }

        $newNode = $config->createElement($lastKeyPart, $text);
        $attribute = $config->createAttribute('lang');
        $attribute->value = $languageCode;
        $newNode->appendChild($attribute);
        $targetNodePath = $path . '/descendant::' . $lastKeyPart . '[last()]';

        $targetChildren = $domXpath->query($targetNodePath);

        if ($targetChildren !== false && $targetChildren->count() === 0) {
            $targetNodePath = $path . '/following-sibling::' . $lastKeyPart . '[last()]';
        }

        $targetChildren = $domXpath->query($targetNodePath);

        if ($targetChildren !== false && $targetChildren->count() === 1) {
            /** @var \DOMNode $targetNode */
            $targetNode = $targetChildren->item(0);
            /** @var \DOMNode $parentNode */
            $parentNode = $targetNode->parentNode;
            $parentNode->insertBefore($newNode, $targetNode->nextSibling);

            return new AppenderResult(sprintf('Appended "%s" with the key %s', $text, $key));
        }

        if ($targetChildren !== false && $targetChildren->count() === 0) {
            /** @var \DOMNode $firstNode */
            $firstNode = $domElement->item(0);
            $firstNode->appendChild($newNode);
        }

        return new AppenderResult(sprintf('Created new entry "%s" with the key %s', $text, $key));
    }
}
