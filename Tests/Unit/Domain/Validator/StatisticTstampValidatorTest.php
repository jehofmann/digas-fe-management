<?php

declare(strict_types=1);

namespace Slub\DigasFeManagement\Tests\Unit\Domain\Validator;

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

use Slub\DigasFeManagement\Domain\Validator\StatisticTstampValidator;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Tests for StatisticTstampValidator
 */
class StatisticTstampValidatorTest extends UnitTestCase
{
    protected StatisticTstampValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new StatisticTstampValidator();
    }

    /**
     * @test
     * @dataProvider validDatesProvider
     */
    public function validDatePassesValidation(string $date): void
    {
        $result = $this->validator->validate($date);
        self::assertFalse($result->hasErrors(), sprintf('Expected no errors for date "%s"', $date));
    }

    public function validDatesProvider(): array
    {
        return [
            'standard date' => ['2023-01-15'],
            'leap year Feb 29' => ['2024-02-29'],
            'year start' => ['2023-01-01'],
            'year end' => ['2023-12-31'],
            'single digit months as zero-padded' => ['2023-06-05'],
            'far future date' => ['2099-12-31'],
            'year 2000' => ['2000-01-01'],
        ];
    }

    /**
     * @test
     * @dataProvider invalidDatesProvider
     */
    public function invalidDateFailsValidation(string $date): void
    {
        $result = $this->validator->validate($date);
        self::assertTrue($result->hasErrors(), sprintf('Expected errors for date "%s"', $date));
    }

    public function invalidDatesProvider(): array
    {
        return [
            'invalid month 13' => ['2023-13-01'],
            'invalid day 32' => ['2023-01-32'],
            'german date format' => ['15.01.2023'],
            'us date format' => ['01/15/2023'],
            'plain text' => ['not-a-date'],
            'partial date missing day' => ['2023-01'],
            'non-zero-padded month' => ['2023-1-1'],
            'non-leap year Feb 29' => ['2023-02-29'],
            'with time' => ['2023-01-15 10:00:00'],
            'reversed format' => ['15-01-2023'],
        ];
    }

    /**
     * @test
     */
    public function validatorAddsErrorWithCorrectCode(): void
    {
        $result = $this->validator->validate('not-a-date');
        self::assertTrue($result->hasErrors());

        $errors = $result->getErrors();
        self::assertCount(1, $errors);
        self::assertEquals(15159425, $errors[0]->getCode());
    }

    /**
     * @test
     */
    public function validDateProducesNoErrors(): void
    {
        $result = $this->validator->validate('2023-06-15');
        self::assertFalse($result->hasErrors());
        self::assertCount(0, $result->getErrors());
    }

    /**
     * @test
     *
     * Note: TYPO3's AbstractValidator has acceptsEmptyValues=true by default,
     * so empty string bypasses isValid() and produces no errors.
     * Only non-empty invalid strings trigger the validator.
     */
    public function emptyStringIsAcceptedByDefaultDueToTypo3Behavior(): void
    {
        $result = $this->validator->validate('');
        // AbstractValidator skips validation for empty values by default
        self::assertFalse($result->hasErrors());
    }
}
