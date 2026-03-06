<?php

declare(strict_types=1);

namespace Slub\DigasFeManagement\Tests\Unit\Command;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2026 SLUB Dresden <typo3@slub-dresden.de>
 *  All rights reserved
 ***************************************************************/

use Slub\DigasFeManagement\Command\RemindUnusedAccountsCommand;
use Slub\DigasFeManagement\Domain\Model\User;
use Slub\DigasFeManagement\Domain\Repository\AccessRepository;
use Slub\DigasFeManagement\Domain\Repository\UserRepository;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Tests for RemindUnusedAccountsCommand
 */
class RemindUnusedAccountsCommandTest extends UnitTestCase
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

        // TYPO3's initUserLocal() accesses $GLOBALS['LANG']->init() – stub it for unit tests
        $GLOBALS['LANG'] = new class {
            public function init(string $lang): void
            {
            }
        };
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['LANG']);
        parent::tearDown();
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

        $command = new RemindUnusedAccountsCommand(
            $this->userRepositoryMock,
            $this->accessRepositoryMock,
            $this->persistenceManagerMock,
            $this->configManagerMock,
            'digas_fe_management:remindunusedaccounts'
        );

        $application = new Application();
        $application->add($command);

        return new CommandTester($command);
    }

    /**
     * @test
     */
    public function executeReturnsErrorWhenUnusedTimespanIsZero(): void
    {
        $commandTester = $this->buildCommandTester();
        $commandTester->execute(['unusedTimespan' => '0', 'deleteTimespan' => '30']);

        self::assertEquals(1, $commandTester->getStatusCode());
    }

    /**
     * @test
     */
    public function executeReturnsErrorWhenDeleteTimespanIsZero(): void
    {
        $commandTester = $this->buildCommandTester();
        $commandTester->execute(['unusedTimespan' => '365', 'deleteTimespan' => '0']);

        self::assertEquals(1, $commandTester->getStatusCode());
    }

    /**
     * @test
     */
    public function executeReturnsSuccessWithNoUsersToRemindOrDelete(): void
    {
        $this->userRepositoryMock->method('findUnusedAccounts')->willReturn([]);
        $this->userRepositoryMock->method('findAccountsToDelete')->willReturn([]);

        $commandTester = $this->buildCommandTester();
        $commandTester->execute(['unusedTimespan' => '365', 'deleteTimespan' => '30']);

        self::assertEquals(0, $commandTester->getStatusCode());
    }

    /**
     * @test
     */
    public function executeRemindsMandatoryUsers(): void
    {
        $user = $this->createMock(User::class);
        // Use invalid email → sendEmail() skips sending (avoids $GLOBALS['LANG'] call)
        $user->method('getEmail')->willReturn('not-a-valid-email');
        $user->method('getFullName')->willReturn('Test User');
        $user->method('getLocale')->willReturn(0);
        $user->method('getUid')->willReturn(42);

        $this->userRepositoryMock->method('findUnusedAccounts')->willReturn([$user]);
        $this->userRepositoryMock->method('findAccountsToDelete')->willReturn([]);

        $this->userRepositoryMock->expects(self::once())->method('update')->with($user);

        $commandTester = $this->buildCommandTester();
        $commandTester->execute(['unusedTimespan' => '365', 'deleteTimespan' => '30']);

        // Command should complete (exit code 0 or 1 depending on email config)
        // The key check: update() was called
        self::assertStringContainsString('Task finished', $commandTester->getDisplay());
    }

    /**
     * @test
     */
    public function executeDeletesAccountsMarkedForDeletion(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getUid')->willReturn(99);

        $this->userRepositoryMock->method('findUnusedAccounts')->willReturn([]);
        $this->userRepositoryMock->method('findAccountsToDelete')->willReturn([$user]);

        $this->userRepositoryMock->expects(self::once())
            ->method('remove')
            ->with($user);

        $this->persistenceManagerMock->expects(self::once())
            ->method('persistAll');

        $commandTester = $this->buildCommandTester();
        $commandTester->execute(['unusedTimespan' => '365', 'deleteTimespan' => '30']);

        self::assertEquals(0, $commandTester->getStatusCode());
    }

    /**
     * @test
     */
    public function executeOutputsBothRemindAndDeleteResults(): void
    {
        $this->userRepositoryMock->method('findUnusedAccounts')->willReturn([]);
        $this->userRepositoryMock->method('findAccountsToDelete')->willReturn([]);

        $commandTester = $this->buildCommandTester();
        $commandTester->execute(['unusedTimespan' => '365', 'deleteTimespan' => '30']);

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('remindUnusedAccounts', $output);
        self::assertStringContainsString('deleteUnusedAccounts', $output);
    }
}
