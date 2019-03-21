<?php

namespace HazaarTest;

class MoneyTest extends \PHPUnit\Framework\TestCase {

    public function testCanDoAdd() {

        $a = new \Hazaar\Money(100, 'USD');

        $b = new \Hazaar\Money(100, 'AUD');

        $this->assertInternalType('float', $a->toFloat());

        $this->assertInternalType('float', $b->toFloat());

        $a->add($b);

        $this->assertInternalType('float', $a->toFloat());

    }

    public function testCanGetExchangeRate() {

        $a = new \Hazaar\Money(100, 'AUD');

        $rate = $a->getExchangeRate('USD');

        $this->assertInternalType('float', $rate);

        $this->assertGreaterThan(0, $rate);

    }

    public function testCanConvertTo() {

        $a = new \Hazaar\Money(100, 'USD');

        $a->convertTo('AUD');

        $this->assertInternalType('string', $a->toString());

    }

}