<?php

declare(strict_types=1);

namespace Slub\DigasFeManagement\Tests\Unit\Command;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2026 SLUB Dresden <typo3@slub-dresden.de>
 *  All rights reserved
 ***************************************************************/

use Slub\DigasFeManagement\Command\DeleteDeactivatedAccountsCommand;
use Slub\DigasFeManagement\Domain\Model\User;
use Slub\DigasFeManagement\Domain\Repository\AccessRepository;
use Slub\DigasFeManagement\Domain\Repository\UserRepository;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Tests for DeleteDeactivatedAccountsCommand
 */
class DeleteDeactivatedAccountsCommandTest extends UnitTestCase
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
            ->willReturn([
                'plugin.' => [
                    'tx_femanager.' => [
                        'settings.' => [
                            'pids.' => ['feUsers' => 1, 'loginPage' => 2],
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

        $command = new DeleteDeactivatedAccountsCommand(
            $this->userRepositoryMock,
            $this->accessRepositoryMock,
            $this->persistenceManagerMock,
            $this->configManagerMock,
            'digas_fe_management:deletedeactivatedaccounts'
        );

        $application = new Application();
        $application->add($command);

        return new CommandTester($command);
    }

    /**
     * @test
     */
    public function executeReturnsErrorOnTimespanZero(): void
    {
        $commandTester = $this->buildCommandTester();
        $commandTester->execute(['timespan' => '0']);

        self::assertEquals(1, $commandTester->getStatusCode());
    }

    /**
     * @test
     */
    public function executeReturnsSuccessWithNoDeactivatedUsersFound(): void
    {
        $this->userRepositoryMock->method('findDeactivatedAccounts')->willReturn([]);

        $commandTester = $this->buildCommandTester();
        $commandTester->execute(['timespan' => '30']);

        self::assertEquals(0, $commandTester->getStatusCode());
    }

    /**
     * @test
     */
    public function executeDeletesDeactivatedUsersAndPersists(): void
    {
        $user1 = $this->createMock(User::class);
        $user2 = $this->createMock(User::class);
        $user3 = $this->createMock(User::class);

        $this->userRepositoryMock->method('findDeactivatedAccounts')
            ->willReturn([$user1, $user2, $user3]);

        $this->userRepositoryMock->expects(self::exactly(3))
            ->method('remove');

        $this->persistenceManagerMock->expects(self::once())
            ->method('persistAll');

        $commandTester = $this->buildCommandTester();
        $commandTester->execute(['timespan' => '90']);

        self::assertEquals(0, $commandTester->getStatusCode());
    }

    /**
     * @test
     */
    public function executeOutputsDeleteCount(): void
    {
        $user = $this->createMock(User::class);
        $this->userRepositoryMock->method('findDeactivatedAccounts')->willReturn([$user]);

        $commandTester = $this->buildCommandTester();
        $commandTester->execute(['timespan' => '30']);

        self::assertStringContainsString('1', $commandTester->getDisplay());
    }
}
