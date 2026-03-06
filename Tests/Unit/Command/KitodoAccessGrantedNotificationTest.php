<?php

declare(strict_types=1);

namespace Slub\DigasFeManagement\Tests\Unit\Command;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2026 SLUB Dresden <typo3@slub-dresden.de>
 *  All rights reserved
 ***************************************************************/

use Slub\DigasFeManagement\Command\KitodoAccessGrantedNotification;
use Slub\DigasFeManagement\Domain\Model\Access;
use Slub\DigasFeManagement\Domain\Model\User;
use Slub\DigasFeManagement\Domain\Repository\AccessRepository;
use Slub\DigasFeManagement\Domain\Repository\UserRepository;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Tests for KitodoAccessGrantedNotification command
 */
class KitodoAccessGrantedNotificationTest extends UnitTestCase
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
                            'feUserGroups' => '4',
                            'adminName' => 'DiGA.Sax Admin',
                            'adminEmail' => 'admin@diga-sax.de',
                            'kitodoTempUserGroup' => '5',
                        ],
                    ],
                ],
            ]);

        $this->userRepositoryMock->expects(self::any())->method('setStoragePid');
        $this->accessRepositoryMock->expects(self::any())->method('setStoragePid');

        $command = new KitodoAccessGrantedNotification(
            $this->userRepositoryMock,
            $this->accessRepositoryMock,
            $this->persistenceManagerMock,
            $this->configManagerMock,
            'digas_fe_management:kitodoaccessgrantednotification'
        );

        $application = new Application();
        $application->add($command);

        return new CommandTester($command);
    }

    /**
     * @test
     */
    public function executeReturnsSuccessWhenNoUsersWithGrantedAccess(): void
    {
        $this->accessRepositoryMock->method('findAccessGrantedUsers')->willReturn([]);

        $commandTester = $this->buildCommandTester();
        $commandTester->execute([]);

        self::assertEquals(0, $commandTester->getStatusCode());
    }

    /**
     * @test
     */
    public function executeOutputsFoundUsersCount(): void
    {
        $this->accessRepositoryMock->method('findAccessGrantedUsers')->willReturn([]);

        $commandTester = $this->buildCommandTester();
        $commandTester->execute([]);

        self::assertStringContainsString('0 fe_users', $commandTester->getDisplay());
    }

    /**
     * @test
     */
    public function executeSkipsNotificationForMissingUser(): void
    {
        $accessEntry = $this->createMock(Access::class);
        $accessEntry->method('getFeUser')->willReturn(999);

        $this->accessRepositoryMock->method('findAccessGrantedUsers')
            ->willReturn([$accessEntry]);

        // User not found → should skip with warning
        $this->userRepositoryMock->method('findByUid')->with(999)->willReturn(null);

        $commandTester = $this->buildCommandTester();
        $commandTester->execute([]);

        self::assertEquals(0, $commandTester->getStatusCode());
        self::assertStringContainsString('Skip notification', $commandTester->getDisplay());
    }

    /**
     * @test
     */
    public function executeOutputsSuccessMessage(): void
    {
        $this->accessRepositoryMock->method('findAccessGrantedUsers')->willReturn([]);

        $commandTester = $this->buildCommandTester();
        $commandTester->execute([]);

        self::assertStringContainsString('successfully', $commandTester->getDisplay());
    }
}
