<?php

declare(strict_types=1);

namespace Slub\DigasFeManagement\Tests\Unit\ViewHelpers;

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

use Slub\DigasFeManagement\ViewHelpers\ExplodeNLViewHelper;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * Tests for ExplodeNLViewHelper
 */
class ExplodeNLViewHelperTest extends UnitTestCase
{
    private RenderingContextInterface $renderingContext;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderingContext = $this->createMock(RenderingContextInterface::class);
    }

    /**
     * @test
     */
    public function renderStaticExplodesByWindowsLineEnding(): void
    {
        $result = ExplodeNLViewHelper::renderStatic(
            ['string' => "line1\r\nline2\r\nline3"],
            static function () {
                return '';
            },
            $this->renderingContext
        );

        self::assertEquals(['line1', 'line2', 'line3'], $result);
    }

    /**
     * @test
     */
    public function renderStaticWithSingleLineReturnsArrayWithOneElement(): void
    {
        $result = ExplodeNLViewHelper::renderStatic(
            ['string' => 'single line without newline'],
            static function () {
                return '';
            },
            $this->renderingContext
        );

        self::assertIsArray($result);
        self::assertCount(1, $result);
        self::assertEquals('single line without newline', $result[0]);
    }

    /**
     * @test
     */
    public function renderStaticWithEmptyStringReturnsArrayWithEmptyElement(): void
    {
        $result = ExplodeNLViewHelper::renderStatic(
            ['string' => ''],
            static function () {
                return '';
            },
            $this->renderingContext
        );

        self::assertIsArray($result);
        self::assertCount(1, $result);
        self::assertEquals('', $result[0]);
    }

    /**
     * @test
     */
    public function renderStaticDoesNotSplitOnUnixLineEnding(): void
    {
        // Only \r\n splits – plain \n does NOT split (by design)
        $result = ExplodeNLViewHelper::renderStatic(
            ['string' => "line1\nline2"],
            static function () {
                return '';
            },
            $this->renderingContext
        );

        // Remains unsplit because only \r\n is the delimiter
        self::assertCount(1, $result);
    }

    /**
     * @test
     */
    public function renderStaticWithTwoLinesReturnsTwoElements(): void
    {
        $result = ExplodeNLViewHelper::renderStatic(
            ['string' => "erste Zeile\r\nzweite Zeile"],
            static function () {
                return '';
            },
            $this->renderingContext
        );

        self::assertCount(2, $result);
        self::assertEquals('erste Zeile', $result[0]);
        self::assertEquals('zweite Zeile', $result[1]);
    }

    /**
     * @test
     */
    public function renderStaticReturnsAnArray(): void
    {
        $result = ExplodeNLViewHelper::renderStatic(
            ['string' => "a\r\nb\r\nc"],
            static function () {
                return '';
            },
            $this->renderingContext
        );

        self::assertIsArray($result);
    }
}
