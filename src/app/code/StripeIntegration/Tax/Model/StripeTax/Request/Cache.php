<?php

namespace StripeIntegration\Tax\Model\StripeTax\Request;

use \Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\SerializerInterface;

class Cache
{
    public const STRIPE_CACHE_TAG = 'stripe_tax';

    private $cache;
    private $hash;
    private $serializer;

    public function __construct(
        CacheInterface $cache,
        SerializerInterface $serializer
    )
    {
        $this->cache = $cache;
        $this->serializer = $serializer;
    }

    public function sortRecursiveByKeys(&$request)
    {
        foreach ($request as &$component) {
            if (is_array($component))
                $this->sortRecursiveByKeys($component);
        }
        ksort($request);
    }

    public function setRequest($request)
    {
        $preparedRequest = $this->prepareRequest($request);
        $this->sortRecursiveByKeys($preparedRequest);
        $hash = hash('sha256', $this->serializer->serialize($preparedRequest));
        $this->setHash($hash);
    }

    /**
     * Take out the request components which are not needed
     *
     * @param $request
     * @return array
     */
    private function prepareRequest($request)
    {
        unset($request['customer_details']['address']['line1']);

        return $request;
    }

    private function setHash($hash)
    {
        $this->hash = $hash;
    }

    public function getCachedResponse()
    {
        $response = $this->cache->load($this->hash);
        if ($response) {
            return $this->serializer->unserialize($response);
        }

        return $response;
    }

    public function cacheResponse($response)
    {
        $serializedResponse = $this->serializer->serialize($response);
        $result = $this->cache->save($serializedResponse, $this->hash, [self::STRIPE_CACHE_TAG], 60 * 60);

        return $result ? $serializedResponse : $result;
    }

    public function invalidate()
    {
        $this->cache->clean([self::STRIPE_CACHE_TAG]);
    }
}