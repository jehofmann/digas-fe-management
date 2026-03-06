<?php

declare(strict_types=1);

namespace Slub\DigasFeManagement\Tests\Unit\Command;

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

use Slub\DigasFeManagement\Command\DeleteInactiveAccountsCommand;
use Slub\DigasFeManagement\Domain\Model\User;
use Slub\DigasFeManagement\Domain\Repository\AccessRepository;
use Slub\DigasFeManagement\Domain\Repository\UserRepository;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Tests for DeleteInactiveAccountsCommand
 */
class DeleteInactiveAccountsCommandTest extends UnitTestCase
{
    private UserRepository $userRepositoryMock;
    private AccessRepository $accessRepositoryMock;
    private PersistenceManager $persistenceManagerMock;
    private ConfigurationManagerInterface $configManagerMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userRepositoryMock = $this->createMock(UserRepository::class);
        $this->accessRepositoryMock = $this->createMock(AccessRepository::class);
        $this->persistenceManagerMock = $this->createMock(PersistenceManager::class);
        $this->configManagerMock = $this->createMock(ConfigurationManagerInterface::class);
    }

    private function buildCommandTester(): CommandTester
    {
        $this->configManagerMock->method('getConfiguration')
            ->with(ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT)
            ->willReturn([
                'plugin.' => [
                    'tx_femanager.' => [
                        'settings.' => [
                            'pids.' => [
                                'feUsers' => 1,
                                'loginPage' => 2,
                                'kitodoTempUserPid' => 3,
                            ],
                            'feUserGroups' => '4,5',
                            'adminName' => 'DiGA.Sax Admin',
                            'adminEmail' => 'admin@diga-sax.de',
                            'kitodoTempUserGroup' => '6',
                        ],
                    ],
                ],
            ]);

        $this->userRepositoryMock->expects(self::any())->method('setStoragePid');
        $this->accessRepositoryMock->expects(self::any())->method('setStoragePid');

        $command = new DeleteInactiveAccountsCommand(
            $this->userRepositoryMock,
            $this->accessRepositoryMock,
            $this->persistenceManagerMock,
            $this->configManagerMock,
            'digas_fe_management:deleteinactiveaccounts'
        );

        $application = new Application();
        $application->add($command);

        return new CommandTester($command);
    }

    /**
     * @test
     */
    public function executeReturnsErrorOnInvalidTimespanZero(): void
    {
        $commandTester = $this->buildCommandTester();
        $commandTester->execute(['timespan' => '0']);

        self::assertEquals(1, $commandTester->getStatusCode());
    }

    /**
     * @test
     */
    public function executeReturnsErrorOnNegativeTimespan(): void
    {
        $commandTester = $this->buildCommandTester();
        $commandTester->execute(['timespan' => '-5']);

        self::assertEquals(1, $commandTester->getStatusCode());
    }

    /**
     * @test
     */
    public function executeReturnsSuccessWithNoUsersFound(): void
    {
        $this->userRepositoryMock->method('findInactiveAccounts')->willReturn([]);

        $commandTester = $this->buildCommandTester();
        $commandTester->execute(['timespan' => '24']);

        self::assertEquals(0, $commandTester->getStatusCode());
    }

    /**
     * @test
     */
    public function executeDeletesFoundUsersAndReturnsSuccess(): void
    {
        $user1 = $this->createMock(User::class);
        $user2 = $this->createMock(User::class);

        $this->userRepositoryMock->method('findInactiveAccounts')->willReturn([$user1, $user2]);

        $this->userRepositoryMock->expects(self::exactly(2))
            ->method('remove');

        $this->persistenceManagerMock->expects(self::once())
            ->method('persistAll');

        $commandTester = $this->buildCommandTester();
        $commandTester->execute(['timespan' => '48']);

        self::assertEquals(0, $commandTester->getStatusCode());
        self::assertStringContainsString('2', $commandTester->getDisplay());
    }

    /**
     * @test
     */
    public function executeOutputsSuccessMessage(): void
    {
        $this->userRepositoryMock->method('findInactiveAccounts')->willReturn([]);

        $commandTester = $this->buildCommandTester();
        $commandTester->execute(['timespan' => '24']);

        self::assertStringContainsString('successfully', $commandTester->getDisplay());
    }
}
