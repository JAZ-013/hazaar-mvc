<?php

namespace HazaarTest;

class MapTest extends \PHPUnit\Framework\TestCase {

    public function testCanCount() {

        $m = new \Hazaar\Map();

        $m->fill(0, 10, '0000');

        $this->assertCount(10, $m->toArray());

        $this->assertEquals('0000', $m[3]);

    }

}