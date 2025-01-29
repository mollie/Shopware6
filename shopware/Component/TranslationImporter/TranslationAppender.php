<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\TranslationImporter;

use DOMDocument;
use DOMElement;
use DOMNamedNodeMap;
use DOMXPath;

final class TranslationAppender implements AppenderInterface
{
    public function append(DOMDocument $config, string $key, string $text, string $languageCode): DOMDocument
    {
        $domXpath = new DOMXPath($config);

        $keyParts = explode('.', $key);


        $lastKeyPart = array_pop($keyParts);
        $keyPartPath = '';
        foreach ($keyParts as $keyPart) {
            if (is_numeric($keyPart)) {
                $keyPartPath .= "/*[" . $keyPart . "]";
                continue;
            }
            $keyPartPath .= '/' . $keyPart;
        }

        $replaceXpathQuery = "/" . $keyPartPath . '/' . $lastKeyPart . '[@lang="' . $languageCode . '"]';


        $domElement = $domXpath->query($replaceXpathQuery);

        if ($domElement->count() === 1) {
            $oldElement = $domElement->item(0);
            if ($oldElement !== null) {
                $textElement = $oldElement->firstChild;
                if ($textElement instanceof \DOMText) {
                    $textElement->data = $text;
                }
            }

            return $config;
        }


        $path = '/';
        foreach ($keyParts as $index => $keyPart) {
            $keyPart = mb_strtolower($keyPart);

            $searchParts = [
                "following::" . $keyPart, //search for the HTMLElement
                "following::title[contains(translate(text(),'ABCDEFGHIJKLMNOPQRSTUVWXYZ ','abcdefghijklmnopqrstuvwxyz'),'" . $keyPart . "')]",//search for the title which contains the key part,
                "following::name[contains(translate(text(),'ABCDEFGHIJKLMNOPQRSTUVWXYZ ','abcdefghijklmnopqrstuvwxyz'),'" . $keyPart . "')]",//search for the name which contains the key part,
                "descendant::" . $keyPart, //search for the HTMLElement
                "descendant::title[contains(translate(text(),'ABCDEFGHIJKLMNOPQRSTUVWXYZ ','abcdefghijklmnopqrstuvwxyz'),'" . $keyPart . "')]",//search for the title which contains the key part,
                "descendant::name[contains(translate(text(),'ABCDEFGHIJKLMNOPQRSTUVWXYZ ','abcdefghijklmnopqrstuvwxyz'),'" . $keyPart . "')]",//search for the name which contains the key part,
            ];

            if (is_numeric($keyPart)) {
                //convert options.0. to options/*[0]
                $searchParts = [
                    "*[" . $keyPart . "]"
                ];
            }

            foreach ($searchParts as $searchPart) {

                $result = $domXpath->query($path . '/' . $searchPart);

                if ($result->count() === 0) {
                    continue;
                }
                $path .= '/' . $searchPart;
                break;

            }

        }


        $domElement = $domXpath->query($path);
        //we expect to find exactly one node, not multiple
        if ($domElement->count() !== 1) {
            return $config;
        }

        $newNode = $config->createElement($lastKeyPart, $text);
        $attribute = $config->createAttribute('lang');
        $attribute->value = $languageCode;
        $newNode->appendChild($attribute);
        $targetNodePath = $path.'/descendant::'.$lastKeyPart.'[last()]';

        $targetChildren = $domXpath->query($targetNodePath);
        if($targetChildren->count() === 0) {
            $targetNodePath = $path.'/following-sibling::'.$lastKeyPart.'[last()]';
        }

        $targetChildren = $domXpath->query($targetNodePath);

        if($targetChildren->count() === 1){
            $targetNode = $targetChildren->item(0);
            $targetNode->parentNode->insertBefore($newNode,$targetNode->nextSibling);
            return $config;
        }

        if($targetChildren->count() === 0){
            $domElement->item(0)->appendChild($newNode);
        }
        return $config;
    }

}