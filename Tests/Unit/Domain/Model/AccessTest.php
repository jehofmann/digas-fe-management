<?php

declare(strict_types=1);

namespace Slub\DigasFeManagement\Tests\Unit\Domain\Model;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2026 SLUB Dresden <typo3@slub-dresden.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 ***************************************************************/

use Slub\DigasFeManagement\Domain\Model\Access;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Tests for Access domain model
 */
class AccessTest extends UnitTestCase
{
    protected Access $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new Access();
    }

    /**
     * @test
     */
    public function recordIdGetterAndSetter(): void
    {
        $this->subject->setRecordId('oai:slub-dresden.de:123456');
        self::assertEquals('oai:slub-dresden.de:123456', $this->subject->getRecordId());
    }

    /**
     * @test
     */
    public function feUserGetterAndSetter(): void
    {
        $this->subject->setFeUser(42);
        self::assertEquals(42, $this->subject->getFeUser());
    }

    /**
     * @test
     */
    public function hiddenGetterAndSetter(): void
    {
        $this->subject->setHidden(true);
        self::assertTrue($this->subject->getHidden());

        $this->subject->setHidden(false);
        self::assertFalse($this->subject->getHidden());
    }

    /**
     * @test
     */
    public function setStartTimeSetsTimestampDirectly(): void
    {
        $timestamp = 1700000000;
        $this->subject->setStartTime($timestamp);
        self::assertEquals($timestamp, $this->subject->getStartTime());
    }

    /**
     * @test
     */
    public function setEndTimeSetsTimestampDirectly(): void
    {
        $timestamp = 1800000000;
        $this->subject->setEndTime($timestamp);
        self::assertEquals($timestamp, $this->subject->getEndTime());
    }

    /**
     * @test
     */
    public function setStartTimeStringConvertsToTimestampAndSavesString(): void
    {
        $dateString = '2023-01-15';
        $this->subject->setStartTimeString($dateString);

        self::assertEquals(strtotime($dateString), $this->subject->getStartTime());
        self::assertEquals($dateString, $this->subject->getStartTimeString());
    }

    /**
     * @test
     */
    public function setEndTimeStringConvertsToTimestampAndSavesString(): void
    {
        $dateString = '2024-06-30';
        $this->subject->setEndTimeString($dateString);

        self::assertEquals(strtotime($dateString), $this->subject->getEndTime());
        self::assertEquals($dateString, $this->subject->getEndTimeString());
    }

    /**
     * @test
     */
    public function rejectedGetterAndSetter(): void
    {
        $this->subject->setRejected(true);
        self::assertTrue($this->subject->getRejected());

        $this->subject->setRejected(false);
        self::assertFalse($this->subject->getRejected());
    }

    /**
     * @test
     */
    public function rejectedReasonGetterAndSetter(): void
    {
        $reason = 'Zugang nicht genehmigt: Nutzer nicht berechtigt.';
        $this->subject->setRejectedReason($reason);
        self::assertEquals($reason, $this->subject->getRejectedReason());
    }

    /**
     * @test
     */
    public function informUserGetterAndSetter(): void
    {
        $this->subject->setInformUser(true);
        self::assertTrue($this->subject->getInformUser());

        $this->subject->setInformUser(false);
        self::assertFalse($this->subject->getInformUser());
    }

    /**
     * @test
     */
    public function accessGrantedNotificationGetterAndSetter(): void
    {
        $timestamp = 1700000000;
        $this->subject->setAccessGrantedNotification($timestamp);
        self::assertEquals($timestamp, $this->subject->getAccessGrantedNotification());
    }

    /**
     * @test
     */
    public function expireNotificationGetterAndSetter(): void
    {
        $timestamp = 1800000000;
        $this->subject->setExpireNotification($timestamp);
        self::assertEquals($timestamp, $this->subject->getExpireNotification());
    }

    /**
     * @test
     */
    public function startTimeStringIsIndependentFromEndTime(): void
    {
        $this->subject->setStartTimeString('2023-01-01');
        $this->subject->setEndTimeString('2024-12-31');

        self::assertEquals(strtotime('2023-01-01'), $this->subject->getStartTime());
        self::assertEquals(strtotime('2024-12-31'), $this->subject->getEndTime());
        self::assertEquals('2023-01-01', $this->subject->getStartTimeString());
        self::assertEquals('2024-12-31', $this->subject->getEndTimeString());
    }
}
