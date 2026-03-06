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
use Slub\DigasFeManagement\Domain\Model\Search;
use Slub\DigasFeManagement\Domain\Model\User;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Tests for User domain model
 */
class UserTest extends UnitTestCase
{
    protected User $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new User();
    }

    /**
     * @test
     */
    public function getFullNameConcatenatesFirstAndLastName(): void
    {
        $this->subject->setFirstName('Max');
        $this->subject->setLastName('Mustermann');
        self::assertEquals('Max Mustermann', $this->subject->getFullName());
    }

    /**
     * @test
     */
    public function getFullNameWithEmptyNames(): void
    {
        $this->subject->setFirstName('');
        $this->subject->setLastName('');
        self::assertEquals(' ', $this->subject->getFullName());
    }

    /**
     * @test
     */
    public function getFullNameWithOnlyFirstName(): void
    {
        $this->subject->setFirstName('Anna');
        $this->subject->setLastName('');
        self::assertEquals('Anna ', $this->subject->getFullName());
    }

    /**
     * @test
     */
    public function companyTypeGetterAndSetter(): void
    {
        $this->subject->setCompanyType('Öffentliche Bibliothek');
        self::assertEquals('Öffentliche Bibliothek', $this->subject->getCompanyType());
    }

    /**
     * @test
     */
    public function localeGetterAndSetter(): void
    {
        $this->subject->setLocale(0);
        self::assertEquals(0, $this->subject->getLocale());

        $this->subject->setLocale(1);
        self::assertEquals(1, $this->subject->getLocale());
    }

    /**
     * @test
     */
    public function mustChangePasswordGetterAndSetter(): void
    {
        $this->subject->setMustChangePassword(true);
        self::assertTrue($this->subject->getMustChangePassword());

        $this->subject->setMustChangePassword(false);
        self::assertFalse($this->subject->getMustChangePassword());
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
    public function deletedGetterAndSetter(): void
    {
        $this->subject->setDeleted(1);
        self::assertEquals(1, $this->subject->getDeleted());

        $this->subject->setDeleted(0);
        self::assertEquals(0, $this->subject->getDeleted());
    }

    /**
     * @test
     */
    public function oldAccountGetterAndSetter(): void
    {
        $this->subject->setOldAccount(99);
        self::assertEquals(99, $this->subject->getOldAccount());
    }

    /**
     * @test
     */
    public function districtGetterAndSetter(): void
    {
        $this->subject->setDistrict('Dresden');
        self::assertEquals('Dresden', $this->subject->getDistrict());
    }

    /**
     * @test
     */
    public function pwChangedOnConfirmationGetterAndSetter(): void
    {
        $this->subject->setPwChangedOnConfirmation(true);
        self::assertTrue($this->subject->getPwChangedOnConfirmation());

        $this->subject->setPwChangedOnConfirmation(false);
        self::assertFalse($this->subject->getPwChangedOnConfirmation());
    }

    /**
     * @test
     */
    public function tempUserOrderingPartyGetterAndSetter(): void
    {
        $this->subject->setTempUserOrderingParty('Sächsische Landesbibliothek');
        self::assertEquals('Sächsische Landesbibliothek', $this->subject->getTempUserOrderingParty());
    }

    /**
     * @test
     */
    public function tempUserAreaLocationGetterAndSetter(): void
    {
        $this->subject->setTempUserAreaLocation('Lesesaal Ost');
        self::assertEquals('Lesesaal Ost', $this->subject->getTempUserAreaLocation());
    }

    /**
     * @test
     */
    public function tempUserPurposeGetterAndSetter(): void
    {
        $this->subject->setTempUserPurpose('Wissenschaftliche Forschung');
        self::assertEquals('Wissenschaftliche Forschung', $this->subject->getTempUserPurpose());
    }

    /**
     * @test
     */
    public function inactivemessageTstampGetterAndSetter(): void
    {
        $date = new \DateTime('2023-06-15 10:00:00');
        $this->subject->setInactivemessageTstamp($date);
        self::assertEquals($date, $this->subject->getInactivemessageTstamp());
    }

    /**
     * @test
     */
    public function inactivemessageTstampCanBeSetToNull(): void
    {
        $this->subject->setInactivemessageTstamp(null);
        self::assertNull($this->subject->getInactivemessageTstamp());
    }

    /**
     * @test
     */
    public function savedSearchesInitializedAsEmptyObjectStorage(): void
    {
        self::assertEquals(0, $this->subject->getSavedSearches()->count());
    }

    /**
     * @test
     */
    public function savedSearchCanBeAddedAndRemoved(): void
    {
        $search = new Search();

        $this->subject->addSavedSearch($search);
        self::assertEquals(1, $this->subject->getSavedSearches()->count());

        $this->subject->removeSavedSearch($search);
        self::assertEquals(0, $this->subject->getSavedSearches()->count());
    }

    /**
     * @test
     */
    public function multipleSavedSearchesCanBeAdded(): void
    {
        $search1 = new Search();
        $search2 = new Search();

        $this->subject->addSavedSearch($search1);
        $this->subject->addSavedSearch($search2);

        self::assertEquals(2, $this->subject->getSavedSearches()->count());
    }

    /**
     * @test
     */
    public function kitodoDocumentAccessInitializedAsEmptyObjectStorage(): void
    {
        self::assertEquals(0, $this->subject->getKitodoDocumentAccess()->count());
    }

    /**
     * @test
     */
    public function kitodoDocumentAccessCanBeAddedAndRemoved(): void
    {
        $access = new Access();

        $this->subject->addKitodoDocumentAccess($access);
        self::assertEquals(1, $this->subject->getKitodoDocumentAccess()->count());

        $this->subject->removeKitodoDocumentAccess($access);
        self::assertEquals(0, $this->subject->getKitodoDocumentAccess()->count());
    }

    /**
     * @test
     */
    public function setSavedSearchesReplacesStorage(): void
    {
        $newStorage = new \TYPO3\CMS\Extbase\Persistence\ObjectStorage();
        $search = new Search();
        $newStorage->attach($search);

        $this->subject->setSavedSearches($newStorage);
        self::assertEquals(1, $this->subject->getSavedSearches()->count());
    }

    /**
     * @test
     */
    public function setKitodoDocumentAccessReplacesStorage(): void
    {
        $newStorage = new \TYPO3\CMS\Extbase\Persistence\ObjectStorage();
        $access = new Access();
        $newStorage->attach($access);

        $this->subject->setKitodoDocumentAccess($newStorage);
        self::assertEquals(1, $this->subject->getKitodoDocumentAccess()->count());
    }
}
