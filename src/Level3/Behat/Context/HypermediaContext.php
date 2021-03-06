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
     * @Given /^I have "([^"]*)" property equal to "([^"]*)"$/
     */
    public function iHavePropertyEqualTo($property, $value)
    {
        $this->clientConfig['body'][$property] = $this->jsonDecode($value);
    }

    protected function jsonDecode($value)
    {
        if (!in_array($value[0], ["'", '"','[', '{'])) {
            $value = '"' . $value . '"';
        } else {
            $value = str_replace("'", '"', $value);
        }

        return  json_decode($value);
    }

    /**
     * @When /^I request last resource$/
     */
    public function iRequestLastResource()
    {
        $this->doIRequestResource($this->resource);
    }

    /**
     * @When /^I request (\d+) resource from "([^"]*)" relation$/
     */
    public function iRequestResourceFromRelation($position, $rel)
    {

        $resource = $this->resource->getResources($rel);
        if (!isset($resource[$position])) {
            throw new UnexpectedValueException(sprintf(
                'Resource at position "%d" on relation "%s" is not set!',
                $postion, $rel
            ));
        }
        
        $this->doIRequestResource($resource[$position]);

    }

    protected function doIRequestResource($resource)
    {
        if (!$resource) {
            throw new UnexpectedValueException(
                'Before use this step make a normal request!'
            );
        }

        if (!$resource->getURI()) {
            throw new UnexpectedValueException(
                'This resource not contains an url!'
            );
        }

        $this->iRequest($resource->getURI());
    }


    /**
     * @When /^I request "([^"]*)" link from last resource$/
     */
    public function iRequestLinkFromLastResource($rel)
    {
        if (!$this->resource) {
            throw new UnexpectedValueException(
                'Before use this step make a normal request!'
            );
        }

        $link = $this->resource->getLinks($rel);
        if (!is_object($link)) {
             throw new UnexpectedValueException(sprintf(
                'Link "%s" is not set!',
                $rel
            ));
        }

        $this->iRequest($link->getHref());
    }

    /**
     * @When /^I request "([^"]*)"$/
     */
    public function iRequest($uri)
    {
        $url = $this->getURL($uri);
        $method = strtolower($this->method);

        try {
            $response = $this->doRequest($method, $url);
        } catch (Exception\BadResponseException $e) {
            $response = $e->getResponse();
        } catch (Exception\ServerErrorResponseException $e) {
            $response = $e->getResponse();
        }

        if ($response->getStatusCode() !== 204 && 
            $response->getStatusCode() >= 200 && 
            $response->getStatusCode() < 300
        ) {

            $reader = $this->getReader($response->getContentType());
            $this->resource = $reader->execute($response->getBody(true));
        }

        $this->response = $response;
    }

    protected function doRequest($method, $url)
    {
        if (isset($this->clientConfig['body']) && $this->clientConfig['body']) {
            $this->clientConfig['body'] = json_encode($this->clientConfig['body']);
        }        

        $response = StaticClient::$method($url, $this->clientConfig);
        unset($this->clientConfig['body']);
        return $response;
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
        if (!array_key_exists($property, $data)) {
            throw new UnexpectedValueException(sprintf(
                'Property "%s" is not set!',
                $property
            ));
        }
    }

    /**
     * @Given /^the response not has a "([^"]*)" property$/
     */
    public function theResponseNotHasAProperty($property)
    {
        $data = $this->resource->getData();
        if (array_key_exists($property, $data)) {
            throw new UnexpectedValueException(sprintf(
                'Property "%s" is not set!',
                $property
            ));
        }
    }

    /**
     * @Then /^the "([^"]*)" property equals "([^"]*)"$/
     */
    public function thePropertyEquals($property, $expected)
    {        
        $this->theResponseHasAProperty($property);

        $expected = $this->jsonDecode($expected);
        $data = $this->resource->getData();
        if ($data[$property] != $expected) {
            throw new UnexpectedValueException(sprintf(
                'Property value mismatch! (found: "%s", expected: "%s")',
                json_encode($data[$property]),
                json_encode($expected)
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

    /**
     * @Given /^the "([^"]*)" relation have links$/
     */
    public function theRelationHaveAnyLinks($rel)
    {
        $links = $this->resource->getLinks($rel);
        if(!$links) {
            throw new UnexpectedValueException(sprintf(
                'The relation contains %d links' . PHP_EOL,
                count($links)
            ));
        }
    }

    /**
     * @Given /^the "([^"]*)" relation have (\d+) resources$/
     */
    public function theRelationHaveResources($rel, $count)
    {
        $links = $this->resource->getResources($rel);
        if(count($links) != $count) {
            throw new UnexpectedValueException(sprintf(
                'The relation contains %d resources' . PHP_EOL,
                count($links)
            ));
        }
    }
    
}
