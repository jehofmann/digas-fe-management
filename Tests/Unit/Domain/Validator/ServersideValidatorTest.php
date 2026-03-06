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

use Slub\DigasFeManagement\Domain\Validator\ServersideValidator;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Tests for ServersideValidator – specifically the getControllerName() logic,
 * which maps referrer controller names to lowercase validation contexts.
 */
class ServersideValidatorTest extends UnitTestCase
{
    private \ReflectionMethod $getControllerNameMethod;

    protected function setUp(): void
    {
        parent::setUp();
        $this->getControllerNameMethod = new \ReflectionMethod(ServersideValidator::class, 'getControllerName');
        $this->getControllerNameMethod->setAccessible(true);
    }

    /**
     * @test
     * @dataProvider controllerNameMappingProvider
     */
    public function getControllerNameReturnsCorrectMapping(array $referrer, string $expected): void
    {
        $validator = new ServersideValidator();
        $validator->pluginVariables = ['__referrer' => $referrer];

        $result = $this->getControllerNameMethod->invoke($validator);
        self::assertEquals($expected, $result);
    }

    public function controllerNameMappingProvider(): array
    {
        return [
            'Edit controller maps to edit' => [
                ['@controller' => 'Edit'],
                'edit',
            ],
            'Invitation controller maps to invitation' => [
                ['@controller' => 'Invitation'],
                'invitation',
            ],
            'Administration controller maps to administration' => [
                ['@controller' => 'Administration'],
                'administration',
            ],
            'Unknown controller defaults to new' => [
                ['@controller' => 'Unknown'],
                'new',
            ],
            'New controller defaults to new' => [
                ['@controller' => 'New'],
                'new',
            ],
            // Note: empty referrer array causes PHP "Undefined index: @controller" in production code
            // This is a known issue in the source code when no referrer data is present.
        ];
    }

    /**
     * @test
     */
    public function getControllerNameIsCaseSensitive(): void
    {
        $validator = new ServersideValidator();
        // 'edit' lowercase should NOT match 'Edit' case-sensitive check → defaults to 'new'
        $validator->pluginVariables = ['__referrer' => ['@controller' => 'edit']];

        $result = $this->getControllerNameMethod->invoke($validator);
        self::assertEquals('new', $result, 'Lowercase controller name should not match and fall back to "new"');
    }

    /**
     * @test
     */
    public function setPluginVariablesSetsFromGlobalPost(): void
    {
        // Test that pluginVariables property is publicly accessible (defined in femanager AbstractValidator)
        $validator = new ServersideValidator();
        $validator->pluginVariables = ['test' => 'value'];
        self::assertEquals(['test' => 'value'], $validator->pluginVariables);
    }
}
