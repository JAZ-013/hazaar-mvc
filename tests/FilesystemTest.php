<?php

namespace HazaarTest;

class FilesystemTest extends \PHPUnit\Framework\TestCase {

    public function testCanDoAdd() {

        $file = new \Hazaar\File\Temp();

        $this->assertTrue($file->exists());

        $this->assertTrue($file->touch());


    }

}