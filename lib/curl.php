<?php

/**
 * A basic CURL wrapper
 *
 * See the README for documentation/examples or http://php.net/curl for more information about the libcurl extension for PHP
 *
 * @package curl
 * @author Sean Huber <shuber@huberry.com>
 * @author Fabian Grassl
**/
class Curl {

    /**
     * The file to read and write cookies to for requests
     *
     * @var string
    **/
    public $cookie_file = null;

    /**
     * Determines whether or not requests should follow redirects
     *
     * @var boolean
    **/
    public $follow_redirects = true;

    /**
     * An associative array of headers to send along with requests
     *
     * @var array
    **/
    public $headers = array();

    /**
     * An associative array of CURLOPT options to send along with requests
     *
     * @var array
    **/
    public $options = array();

    /**
     * The referer header to send along with requests
     *
     * @var string
    **/
    public $referer = null;

    /**
     * The user agent to send along with requests
     *
     * @var string
    **/
    public $user_agent = null;

    /**
     * Whether to validate SSL certificates
     *
     * @var boolean
     * @access protected
    **/
    protected $validate_ssl = false;

    /**
     * Stores resource handle for the current CURL request
     *
     * @var resource
     * @access protected
    **/
    protected $request = null;

    /**
     * Stores the HTTP auth credentials
     *
     * @var $userpwd
     * @access protected
    **/
    protected $userpwd = false;

    /**
     * Initializes a Curl object
     *
     * Sets the $cookie_file to "curl_cookie.txt" in the current directory
     * Also sets the $user_agent to $_SERVER['HTTP_USER_AGENT'] if it exists, 'Curl/PHP '.PHP_VERSION.' (http://github.com/shuber/curl)' otherwise
    **/
    function __construct() {
        $this->user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Curl/PHP '.PHP_VERSION.' (http://github.com/shuber/curl)';
    }

    /**
     * Makes an HTTP DELETE request to the specified $url with an optional array or string of $vars
     *
     * Returns a CurlResponse object if the request was successful, false otherwise
     *
     * @param string $url
     * @param array|string $vars
     * @return CurlResponse object
    **/
    public function delete($url, $vars = array()) {
        return $this->request('DELETE', $this->create_get_url($url, $vars));
    }

    /**
     * Makes an HTTP POST request to the specified $url with an optional array or string of $vars
     *
     * @param string $url
     * @param array|string $vars
     * @return CurlResponse|boolean
    **/
    function post($url, $vars = array()) {
        return $this->request('POST', $url, $vars);
    }

    /**
     * Modify the given $url with an optional array or string of $vars
     *
     * Returns the modified $url string
     *
     * @param string $url
     * @param array|string $vars
     * @return string
    **/
    protected function create_get_url($url, $vars = array()) {
        if (!empty($vars)) {
            $url .= (stripos($url, '?') !== false) ? '&' : '?';
            $url .= (is_string($vars)) ? $vars : http_build_query($vars, '', '&');
        }
        return $url;
    }

    /**
     * Makes an HTTP HEAD request to the specified $url with an optional array or string of $vars
     *
     * Returns a CurlResponse object if the request was successful, false otherwise
     *
     * @param string $url
     * @param array|string $vars
     * @return CurlResponse
    **/
    public function head($url, $vars = array()) {
        return $this->request('HEAD', $this->create_get_url($url, $vars));
    }

    /**
     * Makes an HTTP PUT request to the specified $url with an optional array or string of $vars
     *
     * Returns a CurlResponse object if the request was successful, false otherwise
     *
     * @param string $url
     * @param CurlPutData|string $put_data
     * @param array|string $vars
     * @return CurlResponse|boolean
    **/
    public function put($url, $put_data, $vars = array()) {
        return $this->request('PUT', $this->create_get_url($url, $vars), array(), $put_data);
    }
    /**
     * Makes an HTTP request of the specified $method to a $url with an optional array or string of $vars
     *
     * Returns a CurlResponse object if the request was successful, false otherwise
     *
     * @param string $method
     * @param string $url
     * @param array|string $vars
     * @return CurlResponse|boolean
    **/
    function request($method, $url, $post_vars = array(), $put_data = null) {
        if (null !== $put_data && is_string($put_data)) {
          $put_data = CurlPutData::fromString($put_data);
        }
        $this->request = curl_init();
        if (is_array($post_vars)) $vars = http_build_query($vars, '', '&');
        if (is_array($put_data)) $put_data = http_build_query($put_data, '', '&');


        $this->set_request_options($url, $method, $post_vars, $put_data);
        $this->set_request_headers();

        $response = curl_exec($this->request);
        if (!$response) {
          throw new CurlException(curl_error($this->request), curl_errno($this->request));
        }

        $response = new CurlResponse($response);

        curl_close($this->request);

        return $response;
    }

    /**
     * Sets the user and password for HTTP auth basic authentication method.
     *
     * @param string|null $username
     * @param string|null $password
     * @return Curl
     */
    function set_auth($username, $password=null) {
      if (null === $username) {
        $this->userpwd = null;
        return $this;
      }

      $this->userpwd = $username.':'.$password;
      return $this;
    }

    /**
     * Formats and adds custom headers to the current request
     *
     * @return void
     * @access protected
    **/
    protected function set_request_headers() {
        $headers = array();
        foreach ($this->headers as $key => $value) {
            $headers[] = $key.': '.$value;
        }
        curl_setopt($this->request, CURLOPT_HTTPHEADER, $headers);
    }

    /**
     * Set the associated CURL options for a request method
     *
     * @param string $method
     * @return void
     * @access protected
    **/
    protected function set_request_method($method) {
        switch ($method) {
            case 'HEAD':
            case 'OPTIONS':
                curl_setopt($this->request, CURLOPT_NOBODY, true);
                break;
            case 'GET':
                curl_setopt($this->request, CURLOPT_HTTPGET, true);
                break;
            case 'POST':
                curl_setopt($this->request, CURLOPT_POST, true);
                break;
            case 'PUT':
                curl_setopt($this->request, CURLOPT_PUT, true);
                break;
            default:
                curl_setopt($this->request, CURLOPT_CUSTOMREQUEST, $method);
        }
    }
    /**
     * Sets the CURLOPT options for the current request.
     *
     * @param string $url
     * @param string $vars
     * @return void
     * @access protected
    **/
    protected function set_request_options($url, $method, $vars, $put_data) {
        $purl = parse_url($url);

        if (!empty($purl['scheme']) && $purl['scheme'] == 'https') {
            curl_setopt($this->request, CURLOPT_PORT , empty($purl['port'])?443:$purl['port']);
            if ($this->validate_ssl) {
              curl_setopt($this->request,CURLOPT_SSL_VERIFYPEER, true);
              curl_setopt($this->request, CURLOPT_CAINFO, dirname(__FILE__).'/cacert.pem');
          } else {
              curl_setopt($this->request, CURLOPT_SSL_VERIFYPEER, false);
              curl_setopt($this->request, CURLOPT_SSL_VERIFYHOST, 2);
          }
        }

        $method = strtoupper($method);
        set_request_method($method);

        curl_setopt($this->request, CURLOPT_URL, $url);
        if (!empty($vars)) curl_setopt($this->request, CURLOPT_POSTFIELDS, $vars);

        if (!empty($vars)) {
            if ('POST' != $method) {
              throw new InvalidArgumentException('POST-vars may only be set for a POST-Request.');
            }
            curl_setopt($this->request, CURLOPT_POSTFIELDS, $vars);
        } elseif ('POST' == $method) {
          throw new InvalidArgumentException('POST-vars must be set for a POST-Request.');
        }


        if (null !== $put_data) {
          if ('PUT' != $method) {
            throw new InvalidArgumentException('PUT-data may only be set for a PUT-Request.');
          }
          curl_setopt($this->request, CURLOPT_INFILE, $put_data->getResource());
          curl_setopt($this->request, CURLOPT_INFILESIZE, $put_data->getResourceSize());
        } elseif ('PUT' == $method) {
            throw new InvalidArgumentException('PUT-data must be set for a PUT-Request.');
        }

        # Set some default CURL options
        curl_setopt($this->request, CURLOPT_HEADER, true);
        curl_setopt($this->request, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->request, CURLOPT_USERAGENT, $this->user_agent);
        curl_setopt($this->request, CURLOPT_TIMEOUT, 30);

        if ($this->cookie_file) {
            curl_setopt($this->request, CURLOPT_COOKIEFILE, $this->cookie_file);
            curl_setopt($this->request, CURLOPT_COOKIEJAR, $this->cookie_file);
        }
        if ($this->follow_redirects) curl_setopt($this->request, CURLOPT_FOLLOWLOCATION, true);
        if ($this->referer) curl_setopt($this->request, CURLOPT_REFERER, $this->referer);
        if ($this->userpwd) {
            curl_setopt($this->request, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($this->request, CURLOPT_USERPWD, $this->userpwd);
        } else {
            curl_setopt($this->request, CURLOPT_HTTPAUTH, false);
        }

        # Set any custom CURL options
        foreach ($this->options as $option => $value) {
            curl_setopt($this->request, constant('CURLOPT_'.str_replace('CURLOPT_', '', strtoupper($option))), $value);
        }
    }

    /**
     * Returns an associative array of curl options
     * currently configured.
     *
     * @return array Associative array of curl options
    **/
    function get_request_options() {
        return curl_getinfo($this->request);
    }

}