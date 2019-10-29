<?php

namespace HazaarTest;

class MoneyTest extends \PHPUnit\Framework\TestCase {

    public function testCanDoAdd() {

        $a = new \Hazaar\Money(100, 'USD');

        $b = new \Hazaar\Money(100, 'AUD');

        $this->assertIsFloat($a->toFloat());

        $this->assertIsFloat($b->toFloat());

        $a->add($b);

        $this->assertIsFloat($a->toFloat());

    }

    public function testCanGetExchangeRate() {

        $a = new \Hazaar\Money(100, 'AUD');

        $rate = $a->getExchangeRate('USD');

        $this->assertIsFloat($rate);

        $this->assertGreaterThan(0, $rate);

    }

    public function testCanConvertTo() {

        $a = new \Hazaar\Money(100, 'USD');

        $a->convertTo('AUD');

        $this->assertIsString($a->toString());

    }

}