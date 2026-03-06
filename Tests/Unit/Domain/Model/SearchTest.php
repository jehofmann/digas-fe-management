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

use Slub\DigasFeManagement\Domain\Model\Search;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Tests for Search domain model
 */
class SearchTest extends UnitTestCase
{
    protected Search $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new Search();
    }

    /**
     * @test
     */
    public function titleDefaultsToEmptyString(): void
    {
        self::assertEquals('', $this->subject->getTitle());
    }

    /**
     * @test
     */
    public function titleGetterAndSetter(): void
    {
        $this->subject->setTitle('Dresdner Hefte');
        self::assertEquals('Dresdner Hefte', $this->subject->getTitle());
    }

    /**
     * @test
     */
    public function feUserGetterAndSetter(): void
    {
        $this->subject->setFeUser(7);
        self::assertEquals(7, $this->subject->getFeUser());
    }

    /**
     * @test
     */
    public function crdateGetterAndSetter(): void
    {
        $timestamp = 1700000000;
        $this->subject->setCrdate($timestamp);
        self::assertEquals($timestamp, $this->subject->getCrdate());
    }

    /**
     * @test
     */
    public function searchParamsAreJsonEncodedOnSet(): void
    {
        $params = ['q' => 'Dresden', 'facet' => 'author'];
        $this->subject->setSearchParams($params);

        $result = $this->subject->getSearchParams();
        self::assertEquals('Dresden', $result->q);
        self::assertEquals('author', $result->facet);
    }

    /**
     * @test
     */
    public function searchParamsReturnsNullForUnsetParams(): void
    {
        self::assertNull($this->subject->getSearchParams());
    }

    /**
     * @test
     */
    public function searchParamsCanStoreComplexArrays(): void
    {
        $params = [
            'query' => 'Sächsische Geschichte',
            'filters' => ['year' => '1900-2000', 'type' => 'book'],
            'page' => 1,
        ];
        $this->subject->setSearchParams($params);

        $result = $this->subject->getSearchParams();
        self::assertEquals('Sächsische Geschichte', $result->query);
        self::assertEquals('1900-2000', $result->filters->year);
        self::assertEquals(1, $result->page);
    }
}
