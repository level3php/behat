<?php
namespace Level3\Behat\Context;

use Behat\Behat\Context\BehatContext;
use Symfony\Component\Yaml\Yaml;
use Guzzle\Http\Url;
use Guzzle\Http\StaticClient;
use Guzzle\Http\Exception;
use UnexpectedValueException;

use Level3\Resource\Format\Reader\HAL;
use Level3\Behat\Context\HypermediaContext\FormatReaderRepository;

/**
 * Hypermedia context.
 */
class HypermediaContext extends BehatContext
{
    protected $parameters = [];
    protected $formatRepository;
    protected $method;
    protected $clientConfig =  [
        'headers' => [],
        'timeout' => 10
    ];

    /**
     * Initializes context.
     * Every scenario gets it's own context object.
     */
    public function __construct(Array $parameters)
    {
        $this->parameters = $parameters;

        $this->formatRepository = new FormatReaderRepository();
        $this->formatRepository->addReader(new HAL\JsonReader());
    }

    public function getParameter($name)
    {
        if (isset($this->parameters[$name])) {
            return $this->parameters[$name];
        }

        return null;
    }

    /**
     * @Given /^that I want to (delete|remove) an? /
     */
    public function thatIWantToDelete()
    {
        $this->method = 'DELETE';
    }

    /**
     * @Given /^that I want to (get|find|look for) an? /
     */
    public function thatIWantToFind()
    {
        $this->method = 'GET';
    }

    /**
     * @Given /^that I want to (add|create|make) an? (new )?/
     */
    public function thatIWantToMakeANew()
    {
        $this->method = 'POST';
    }

    /**
     * @Given /^that I want to (change|update) (an?|that) /
     */
    public function thatIWantToUpdate()
    {
        $this->method = 'PUT';
    }

    /**
     * @Given /^that I want to (patch|modify) (an?|that) /
     */
    public function thatIWantToModify()
    {
        $this->method = 'PATCH';
    }

    /**
     * @Given /^I have a "([^"]*)" header equal to "([^"]*)"$/
     */
    public function iHaveAHeaderEqualTo($header, $value)
    {
        $this->clientConfig['headers'][$header] = $value;
    }

    /**
     * @When /^I request "([^"]*)"$/
     */
    public function iRequest($uri)
    {
        $url = $this->getURL($uri);
        $method = strtolower($this->method);

        try {
            $response = StaticClient::get($url, $this->clientConfig);
        } catch (Exception\BadResponseException $e) {
            $response = $e->getResponse();
        } catch (Exception\ServerErrorResponseException $e) {
            $response = $e->getResponse();
        }

        $reader = $this->getReader($response->getContentType());

        $this->response = $response;
        $this->resource = $reader->execute($response->getBody(true));
    }

    protected function getURL($uri)
    {
        return Url::factory($this->getParameter('base_url'))->combine($uri);
    }

    protected function getReader($contentType)
    {
        $reader = $this->formatRepository->getReaderByContentType($contentType);
        if (!$reader) {
            throw new UnexpectedValueException(sprintf(
                'Unsuported Content-Type "%s"',
                $contentType
            ));
        }

        return $reader;
    }

    /**
     * @Then /^the response status code should be (\d+)$/
     */
    public function theResponseStatusCodeShouldBe($code)
    {
        if ($this->response->getStatusCode() !== (int) $code) {
            throw new UnexpectedValueException(sprintf(
                'HTTP code does not match %s (actual: %s)',
                $code,
                $this->response->getStatusCode()
            ));
        }
    }

    /**
     * @Given /^the response has a "([^"]*)" header$/
     */
    public function theResponseHasAHeader($header)
    {
        $found = $this->response->getHeader($header);
        if (!$found) {
            throw new UnexpectedValueException(sprintf(
                'Header "%s" is not set!',
                $header
            ));
        }
    }

    /**
     * @Then /^the "([^"]*)" header is equal to "([^"]*)"$/
     */
    public function theHeaderIsEqualTo($header, $expected)
    {
        $this->theResponseHasAHeader($header);

        $found = $this->response->getHeader($header);
        if ($found != $expected) {
            throw new UnexpectedValueException(sprintf(
                'Header value mismatch! (found: "%s", expected: "%s")',
                $found,
                $expected
            ));
        }
    }

    /**
     * @Given /^the response has a "([^"]*)" property$/
     */
    public function theResponseHasAProperty($property)
    {
        $data = $this->resource->getData();
        if (!isset($data[$property])) {
            throw new UnexpectedValueException(sprintf(
                'Property "%s" is not set!',
                $header
            ));
        }
    }

    /**
     * @Then /^the "([^"]*)" property equals "([^"]*)"$/
     */
    public function thePropertyEquals($property, $expected)
    {        
        $this->theResponseHasAProperty($property);

        $data = $this->resource->getData();
        if ($data[$property] != $expected) {
            throw new UnexpectedValueException(sprintf(
                'Property value mismatch! (found: "%s", expected: "%s")',
                $data[$property],
                $expected
            ));
        }
    }

    /**
     * @Given /^the "([^"]*)" relation links to "([^"]*)"$/
     */
    public function theRelationLinksTo($rel, $href)
    {
        $links = $this->resource->getLinks($rel);
        if (!is_array($links)) {
            $links = [$links];
        }            

        $found = false;
        foreach ($links as $link) {
            if ($link->getHref() == $href) {
                $found = true;
            } 
        }

        if (!$found) {
            throw new UnexpectedValueException(sprintf(
                'Missing relation "%s" linked to "%s"', 
                $rel,
                $href
            ));
        }
    }

    /**
     * @Given /^the "([^"]*)" relation have (\d+) links$/
     */
    public function theRelationHaveLinks($rel, $count)
    {
        $links = $this->resource->getLinks($rel);
        if(count($links) != $count) {
            throw new UnexpectedValueException(sprintf(
                'The relation contains %d links' . PHP_EOL,
                count($links)
            ));
        }
    }
}
