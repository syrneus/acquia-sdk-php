<?php

namespace Acquia\Search;

use Acquia\Common\AcquiaClient;
use Guzzle\Common\Collection;
use Guzzle\Http\Url;

class AcquiaSearchClient extends AcquiaClient
{
    /**
     * @var int
     */
    protected $maxQueryLength = 3500;

    /**
     * {@inheritdoc}
     */
    public static function factory($config = array())
    {
        $indexId = isset($config['index_id']) ? $config['index_id'] : '';

        $required = array(
            'base_url',
            'index_id',
            'derived_key',
        );

        $defaults = array(
            'base_path' => '/solr/' . $indexId,
        );

        // Instantiate the Acquia Search plugin.
        $config = Collection::fromConfig($config, $defaults, $required);
        $client = new static($config->get('base_url'), $config);

        // Attach the Acquia Search plugin to the client.
        $client->addSubscriber(new AcquiaSearchAuthPlugin(
            $config->get('index_id'),
            $config->get('derived_key'),
            $client->getNoncer()
        ));

        // Set template that doesn't expand URI template expressions.
        $client->setUriTemplate(new SolrUriTemplate());

        return $client;
    }

    /**
     * {@inheritdoc}
     */
    public function getBuilderClass()
    {
        return 'Acquia\Search\AcquiaSearchService';
    }

    /**
     * {@inheritdoc}
     */
    public function getBuilderParams()
    {
        return array(
            'base_url' => $this->getConfig('base_url'),
            'index_id' => $this->getConfig('index_id'),
            'derived_key' => $this->getConfig('derived_key'),
        );
    }

    /**
     * If the query string exceeds this length, a POST request is issues instead
     * of a GET request to prevent server-side errors.
     *
     * @param int $length
     *
     * @return \Acquia\Search\AcquiaSearchClient
     */
    public function setMaxQueryLength($length)
    {
        $this->maxQueryLength = $length;
        return $this;
    }

    /**
     * @return int
     */
    public function getMaxQueryLength()
    {
        return $this->maxQueryLength;
    }

    /**
     * @param string $uri
     * @param array $params
     *
     * @return boolean
     */
    public function useGetMethod($uri, array $params)
    {
        $url = Url::factory($this->getBaseUrl())->combine($this->expandTemplate($uri, $params));
        return strlen($url) <= $this->maxQueryLength;
    }

    /**
     * @param array $params
     * @param array|null $headers
     * @param array $options
     *
     * @return array
     */
    public function select($params = array(), $headers = null, array $options = array())
    {
        if (!is_array($params)) {
            $params = array('q' => (string) $params);
        }

        $params['wt'] = 'json';
        $params['json.nl'] = 'json';

        $params += array(
            'defType' => 'edismax',
            'q' => '*:*',
            'rows' => 10,
            'start' => 0,
        );

        // Issue GET or POST request depending on url length.
        $uri = '{+base_path}/select';
        if ($this->useGetMethod($uri, $params)) {
            $options['query'] = $params;
            return $this->get($uri, $headers, $options)->send()->json();
        } else {
            unset($options['query']);
            return $this->post($uri, $headers, $params, $options)->send()->json();
        }
    }

    /**
     * @param array $params
     * @param array|null $headers
     * @param array $options
     *
     * @return array
     */
    public function ping(array $params = array(), $headers = null, array $options = array())
    {
        $params += array('wt' => 'json');
        $options['query'] = $params;
        return $this
            ->head('{+base_path}/admin/ping', $headers, $options)
            ->send()
            ->json()
        ;
    }
}