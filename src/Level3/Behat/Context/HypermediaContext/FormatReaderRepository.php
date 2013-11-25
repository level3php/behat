<?php
namespace Level3\Behat\Context\HypermediaContext;

use Level3\Resource\Format\Reader;

class FormatReaderRepository
{
    protected $readers = [];

    public function addReader(Reader $reader)
    {
        $contentType = $reader->getContentType();

        $this->readers[$contentType] = $reader;
    }

    public function getReaders()
    {
        return $this->readers;
    }

    public function getReaderByContentType($contentType)
    {
        if (!isset($this->readers[$contentType])) {
            return null;
        }

        return $this->readers[$contentType];
    }
}
