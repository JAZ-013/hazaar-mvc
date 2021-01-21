<?php

namespace HazaarTest;

class FilesystemTest extends \PHPUnit\Framework\TestCase {

    public function testCanDoAdd() {

        $file1 = new \Hazaar\File\Temp();

        $file1->put_contents('test');

        $this->assertTrue($file1->exists());

        $file2 = $file1->copyTo('/tmp');

        $this->assertTrue($file2->exists());

        $this->assertTrue($file1->get_contents() === $file2->get_contents());

    }

}