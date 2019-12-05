<?php

namespace HazaarTest;

class FilesystemTest extends \PHPUnit\Framework\TestCase {

    public function testCanDoAdd() {

        $file = new \Hazaar\File\Temp();

        $file->put_contents('test');

        $this->assertTrue($file->exists());

    }

}