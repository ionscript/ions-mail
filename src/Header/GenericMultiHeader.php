<?php

namespace Ions\Mail\Header;

/**
 * Class GenericMultiHeader
 * @package Ions\Mail\Header
 */
class GenericMultiHeader extends GenericHeader implements MultipleHeadersInterface
{
    /**
     * @param $headerLine
     * @return array|static
     */
    public static function fromString($headerLine)
    {
        list($fieldName, $fieldValue) = GenericHeader::splitHeaderLine($headerLine);

        $fieldValue = HeaderWrap::mimeDecodeValue($fieldValue);

        if (strpos($fieldValue, ',')) {
            $headers = [];

            foreach (explode(',', $fieldValue) as $multiValue) {
                $headers[] = new static($fieldName, $multiValue);
            }

            return $headers;
        }

        return new static($fieldName, $fieldValue);
    }

    /**
     * @param array $headers
     * @return string
     * @throws \InvalidArgumentException
     */
    public function toStringMultipleHeaders(array $headers)
    {
        $name = $this->getFieldName();

        $values = [$this->getFieldValue(HeaderInterface::FORMAT_ENCODED)];

        foreach ($headers as $header) {
            if (!$header instanceof static) {
                throw new \InvalidArgumentException('This method toStringMultipleHeaders was expecting an array of headers of the same type');
            }

            $values[] = $header->getFieldValue(HeaderInterface::FORMAT_ENCODED);
        }

        return $name . ': ' . implode(',', $values);
    }
}
