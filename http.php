<?php

namespace Atrox\Async;


use React\Stream\BufferedSink;
use React\Promise\Deferred;


class Http {

  private $client;

  function __construct(\React\HttpClient\Client $client) {
    $this->client = $client;
  }


  static function makeClient($loop, $dnsIP) {
    $dnsResolverFactory = new \React\Dns\Resolver\Factory();
    $dnsResolver = $dnsResolverFactory->createCached($dnsIP, $loop);
    $factory = new \React\HttpClient\Factory();
    return new self($factory->create($loop, $dnsResolver));
  }


  function request($method, $url, array $headers = array()) {
    $request = $this->client->request(strtoupper($method), $url, $headers);
    $request->end();

    $deferred = new Deferred();

    $request->on('response', function ($resp) use ($deferred) {
      BufferedSink::createPromise($resp)->then(function ($body) use ($resp, $deferred) {
        $httpResp = new HttpResponse($resp->getProtocol(), $resp->getVersion(), $resp->getCode(), $resp->getReasonPhrase(), $resp->getHeaders(), $body);
        $deferred->resolve($httpResp);
      }, function ($err) {
        $deferred->reject($err);
      });
    });

    $request->on('error', function ($err) use ($deferred) {
      $deferred->reject($err);
    });

    return $deferred->promise();
  }


  function __call($method, $args) {
    list($url, $headers) = $args + array(null, array());
    return $this->request($method, $url, $headers);
  }

}


class HttpResponse {

  private $protocol;
  private $version;
  private $code;
  private $reasonPhrase;
  private $headers;
  private $body;
  

  function __construct($protocol, $version, $code, $reasonPhrase, $headers, $body) {
    list($this->protocol, $this->version, $this->code, $this->reasonPhrase, $this->headers, $this->body) = func_get_args();
  }


  function getProtocol()     { return $this->protocol; }
  function getVersion()      { return $this->version; }
  function getCode()         { return $this->code; }
  function getReasonPhrase() { return $this->reasonPhrase; }
  function getHeaders()      { return $this->headers; }
  function getBody()         { return $this->body; }

}
