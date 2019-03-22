<?php

namespace HazaarTest;

class ApplicationTest extends \PHPUnit\Framework\TestCase {

    private $app;

    private $config_file;

    public function setUp() : void {

        $this->app = new \Hazaar\Application('development');

    }

    public function testCanInitApplication() {

        $this->assertInstanceOf('Hazaar\Application', $this->app);

        $this->assertTrue($this->app->config->loaded());

    }

    public function testReadConfig() {

        $this->assertInstanceOf('Hazaar\Application\Config', $this->app->config);

        $this->assertEquals('Example Application', $this->app->config->app->name);

        $this->assertEquals('0.0.1', $this->app->config->app->version);

        $this->assertEquals(true, $this->app->config->app->debug);

    }

    public function testLoadIndex(){

        $this->assertInstanceOf('Hazaar\Application', $this->app->bootstrap());

    }

}