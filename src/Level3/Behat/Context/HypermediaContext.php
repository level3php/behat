<?php
namespace Level3\Behat\Context;

use Behat\Behat\Context\BehatContext;
use Symfony\Component\Yaml\Yaml;
use Guzzle\Http\Url;
use Guzzle\Http\StaticClient;
use Guzzle\Http\Exception;
use UnexpectedValueException;

/**
 * Hypermedia context.
 */
class HypermediaContext extends BehatContext
{
    protected $parameters = [];
    protected $method;
    protected $clientDefaultConfig =  array(
        'headers' => array('X-Foo' => 'Bar'),
        'timeout' => 10
    );

    /**
     * Initializes context.
     * Every scenario gets it's own context object.
     */
    public function __construct(Array $parameters)
    {
        $this->parameters = $parameters;
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
     * @When /^I request "([^"]*)"$/
     */
    public function iRequest($uri)
    {
        $url = $this->getURL($uri);
        $method = strtolower($this->method);

        try {
            $this->response = StaticClient::get($url, $this->clientDefaultConfig);
        } catch (Exception\BadResponseException $e) {
            $this->response = $e->getResponse();
        } catch (Exception\ServerErrorResponseException $e) {
            $this->response = $e->getResponse();
        }
    }

    protected function getURL($uri)
    {
        return Url::factory($this->getParameter('base_url'))->combine($uri);
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
     * @Given /^the response has a "([^"]*)" property$/
     */
    public function theResponseHasAProperty($property)
    {
        $data = $this->decodeJson($this->response->getBody(true));

        if (!is_array($data) || !isset($data[$property])) {
            throw new UnexpectedValueException(sprintf(
                'Property "%s" is not set!',
                $property
            ));
        }
    }

    /**
     * @Then /^the "([^"]*)" property equals "([^"]*)"$/
     */
    public function thePropertyEquals($property, $value)
    {
        $data = $this->decodeJson($this->response->getBody(true));

        $this->theResponseHasAProperty($property);

        if ($data[$property] !== $value) {
            throw new UnexpectedValueException(sprintf(
                'Property value mismatch! (found: "%s", expected: "%s")',
                $data[$property],
                $value
            ));
        }
    }

    /**
     * @Given /^the relation "([^"]*)" links to "([^"]*)"$/
     */
    public function theRelationLinksTo($rel, $href)
    {
        $links = $this->retrieveLinkByRelation($rel);
        if ($this->isAssocArray($links)) {
            $links = [$links];
        }            

        $found = false;
        foreach ($links as $link) {
            if (isset($link['href']) && $link['href'] == $href) {
                $found = true;
            } 
        }

        if (!$found) {
            throw new UnexpectedValueException(sprintf(
                'No link found to "%s"', 
                $href
            ));
        }
    }

    /**
     * @Given /^the relation "([^"]*)" have (\d+) links$/
     */
    public function theRelationHaveLinks($rel, $count)
    {
        $link = $this->retrieveLinkByRelation($rel);
        if(is_array($link) && count($link) != $count) {
            throw new UnexpectedValueException(sprintf(
                'The relation contains %d links' . PHP_EOL,
                count($link)
            ));
        }
    }

    protected function retrieveLinkByRelation($rel)
    {
        $data = $this->decodeJson($this->response->getBody(true));

        if (!isset($data['_links']) || !$data['_links']) {
            throw new UnexpectedValueException('The resource not contains links\n');
        }

        $links = $data['_links'];

        if (!isset($links[$rel]) || !$links[$rel]) {
            throw new UnexpectedValueException(sprintf(
                'The resource not contains links with the relation "%s"' . PHP_EOL,
                $rel
            ));
        }

        return $links[$rel];
    }

    protected function decodeJson($string)
    {
        $json = json_decode($string, true);

        switch (json_last_error()) {
            case JSON_ERROR_NONE:
                return $json;
            case JSON_ERROR_DEPTH:
                $message = 'Maximum stack depth exceeded';
                break;
            case JSON_ERROR_STATE_MISMATCH:
                $message = 'Underflow or the modes mismatch';
                break;
            case JSON_ERROR_CTRL_CHAR:
                $message = 'Unexpected control character found';
                break;
            case JSON_ERROR_SYNTAX:
                $message = 'Syntax error, malformed JSON';
                break;
            case JSON_ERROR_UTF8:
                $message = 'Malformed UTF-8 characters, possibly incorrectly encoded';
                break;
            default:
                $message = 'Unknown error';
                break;
        }

        throw new UnexpectedValueException('JSON decoding error: ' . $message);
    }

    public function isAssocArray($array)
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }
}
