<?php
// Do a simple GET request with http2 PECL extension

// Define headers here
$headers = array();
$headers['User-Agent'] = 'my-user-agent/0.0';

// Set request options and headers to new Request object
$request = new http\Client\Request('GET', 'http://www.google.es', $headers);
$request->setOptions(array('timeout' => 1));

// Setup HTTP client and send request
$http = new http\Client();
$http->enqueue($request)->send();

// Get response
$response = $http->getResponse();
