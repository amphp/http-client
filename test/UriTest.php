<?php

namespace Amp\Test\Artax;

use Amp\Artax\Uri;

class UriTest extends \PHPUnit_Framework_TestCase {
    public function provideResolvables() {
        return array(
            array('http://localhost/1/2/a.php', 'http://google.com/', 'http://google.com/'),
            array(
                'http://www.google.com/',
                '/level1/level2/test.php',
                'http://www.google.com/level1/level2/test.php'
            ),
            array('http://localhost/1/2/a.php', '../b.php', 'http://localhost/1/b.php'),
            array('http://localhost/1/2/a.php', '../../b.php', 'http://localhost/b.php'),
            array('http://localhost/', './', 'http://localhost/'),
            array('http://localhost/', './dir/', 'http://localhost/dir/'),
            array('http://localhost/', '././', 'http://localhost/'),
            array('http://localhost/', '././dir/', 'http://localhost/dir/'),
            array('http://localhost/', '#frag', 'http://localhost/#frag'),
            array('http://localhost/', '?query', 'http://localhost/?query'),
            array(
                'http://localhost/',
                'http://www.google.com/%22-%3Eresolve%28%22..%2F..%2F%22%29',
                'http://www.google.com/%22-%3Eresolve%28%22..%2F..%2F%22%29'
            ),
            array("http://a/b/c/d;p?q", "g", "http://a/b/c/g"),
            array("http://a/b/c/d;p?q", "./g", "http://a/b/c/g"),
            array("http://a/b/c/d;p?q", "g/", "http://a/b/c/g/"),
            array("http://a/b/c/d;p?q", "/g", "http://a/g"),
            array("http://a/b/c/d;p?q", "//g", "http://g"),
            array("http://a/b/c/d;p?q", "?y", "http://a/b/c/d;p?y"),
            array("http://a/b/c/d;p?q", "g?y", "http://a/b/c/g?y"),
            array("http://a/b/c/d;p?q", "#s", "http://a/b/c/d;p?q#s"),
            array("http://a/b/c/d;p?q", "g#s", "http://a/b/c/g#s"),
            array("http://a/b/c/d;p?q", "g?y#s", "http://a/b/c/g?y#s"),
            array("http://a/b/c/d;p?q", ";x", "http://a/b/c/;x"),
            array("http://a/b/c/d;p?q", "g;x", "http://a/b/c/g;x"),
            array("http://a/b/c/d;p?q", "g;x?y#s", "http://a/b/c/g;x?y#s"),
            array("http://a/b/c/d;p?q", "", "http://a/b/c/d;p?q"),
            array("http://a/b/c/d;p?q", ".", "http://a/b/c/"),
            array("http://a/b/c/d;p?q", "./", "http://a/b/c/"),
            array("http://a/b/c/d;p?q", "..", "http://a/b/"),
            array("http://a/b/c/d;p?q", "../", "http://a/b/"),
            array("http://a/b/c/d;p?q", "../g", "http://a/b/g"),
            array("http://a/b/c/d;p?q", "../..", "http://a/"),
            array("http://a/b/c/d;p?q", "../../", "http://a/"),
            array("http://a/b/c/d;p?q", "../../g", "http://a/g")
        );
    }

    /**
     * @dataProvider provideResolvables
     */
    public function testResolve($baseUri, $toResolve, $expectedResult) {
        $baseUri = new Uri($baseUri);
        $this->assertEquals($expectedResult, $baseUri->resolve($toResolve));
    }

    public function provideUris() {
        return array(
            array(
                'rawUri' => 'http://www.google.com/somePath?var=42#myFrag',
                'expectedVals' => array (
                    'scheme' => 'http',
                    'user' => '',
                    'pass' => '',
                    'host' => 'www.google.com',
                    'port' => '',
                    'path' => '/somePath',
                    'query' => 'var=42',
                    'fragment' => 'myFrag'
                )
            ),
            array(
                'rawUri' => 'http://localhost:80',
                'expectedVals' => array (
                    'scheme' => 'http',
                    'user' => '',
                    'pass' => '',
                    'host' => 'localhost',
                    'port' => '', // <--- default port for scheme should be normalized away
                    'path' => '',
                    'query' => '',
                    'fragment' => ''
                )
            ),
            array(
                'rawUri' => 'https://localhost:443',
                'expectedVals' => array (
                    'scheme' => 'https',
                    'user' => '',
                    'pass' => '',
                    'host' => 'localhost',
                    'port' => '', // <--- default port for scheme should be normalized away
                    'path' => '',
                    'query' => '',
                    'fragment' => ''
                )
            ),
            array(
                'rawUri' => 'ftp://localhost:21',
                'expectedVals' => array (
                    'scheme' => 'ftp',
                    'user' => '',
                    'pass' => '',
                    'host' => 'localhost',
                    'port' => '', // <--- default port for scheme should be normalized away
                    'path' => '',
                    'query' => '',
                    'fragment' => ''
                )
            ),
            array(
                'rawUri' => 'ftps://localhost:990',
                'expectedVals' => array (
                    'scheme' => 'ftps',
                    'user' => '',
                    'pass' => '',
                    'host' => 'localhost',
                    'port' => '', // <--- default port for scheme should be normalized away
                    'path' => '',
                    'query' => '',
                    'fragment' => ''
                )
            ),
            array(
                'rawUri' => 'smtp://localhost:25',
                'expectedVals' => array (
                    'scheme' => 'smtp',
                    'user' => '',
                    'pass' => '',
                    'host' => 'localhost',
                    'port' => '', // <--- default port for scheme should be normalized away
                    'path' => '',
                    'query' => '',
                    'fragment' => ''
                )
            ),
            array(
                'rawUri' => 'http://someuser:mypass@localhost:8080/#frag',
                'expectedVals' => array (
                    'scheme' => 'http',
                    'user' => 'someuser',
                    'pass' => 'mypass',
                    'host' => 'localhost',
                    'port' => '8080',
                    'path' => '/',
                    'query' => '',
                    'fragment' => 'frag'
                )
            ),
            array(
                'rawUri' => 'http://192.168.1.1/?q=42',
                'expectedVals' => array (
                    'scheme' => 'http',
                    'user' => '',
                    'pass' => '',
                    'host' => '192.168.1.1',
                    'port' => '',
                    'path' => '/',
                    'query' => 'q=42',
                    'fragment' => ''
                )
            ),
            array(
                'rawUri' => 'tcp://[fe80::1]:80',
                'expectedVals' => array (
                    'scheme' => 'tcp',
                    'user' => '',
                    'pass' => '',
                    'host' => '[fe80::1]',
                    'port' => '80',
                    'path' => '',
                    'query' => '',
                    'fragment' => ''
                )
            ),
            array(
                'rawUri' => 'tcp://[fe80::1]',
                'expectedVals' => array (
                    'scheme' => 'tcp',
                    'user' => '',
                    'pass' => '',
                    'host' => '[fe80::1]',
                    'port' => '',
                    'path' => '',
                    'query' => '',
                    'fragment' => ''
                )
            ),
            array(
                'rawUri' => 'http://localhost/test.php?params[]=1&params[]=2',
                'expectedVals' => array (
                    'scheme' => 'http',
                    'user' => '',
                    'pass' => '',
                    'host' => 'localhost',
                    'port' => '',
                    'path' => '/test.php',
                    'query' => "params[]=1&params[]=2",
                    'fragment' => ''
                )
            )
        );
    }

    /**
     * @dataProvider provideUris
     */
    public function testUri($rawUri, $expectedVals) {
        $uri = new Uri($rawUri);
        $this->assertEquals($expectedVals['scheme'], $uri->getScheme());
        $this->assertEquals($expectedVals['host'], $uri->getHost());
        $this->assertEquals($expectedVals['user'], $uri->getUser());
        $this->assertEquals($expectedVals['pass'], $uri->getPass());
        $this->assertEquals($expectedVals['port'], $uri->getPort());
        $this->assertEquals($expectedVals['path'], $uri->getPath());
        $this->assertEquals($expectedVals['query'], $uri->getQuery());
        $this->assertEquals($expectedVals['fragment'], $uri->getFragment());
    }

    public function testQueryParams() {
        $uri = new Uri('http://localhost/test.php?params=1&params=2');
        $expected = array('params' => array(1, 2));
        $actual = $uri->getAllQueryParameters();
        $this->assertEquals($expected, $actual);
    }
}
