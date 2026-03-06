<?php

declare(strict_types=1);

namespace Slub\DigasFeManagement\Tests\Unit\Command;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2026 SLUB Dresden <typo3@slub-dresden.de>
 *  All rights reserved
 ***************************************************************/

use Slub\DigasFeManagement\Command\KitodoAccessExpirationNotification;
use Slub\DigasFeManagement\Domain\Model\Access;
use Slub\DigasFeManagement\Domain\Repository\AccessRepository;
use Slub\DigasFeManagement\Domain\Repository\UserRepository;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Tests for KitodoAccessExpirationNotification command
 */
class KitodoAccessExpirationNotificationTest extends UnitTestCase
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

        $command = new KitodoAccessExpirationNotification(
            $this->userRepositoryMock,
            $this->accessRepositoryMock,
            $this->persistenceManagerMock,
            $this->configManagerMock,
            'digas_fe_management:kitodoaccessexpirationnotification'
        );

        $application = new Application();
        $application->add($command);

        return new CommandTester($command);
    }

    /**
     * @test
     */
    public function executeReturnsErrorOnExpirationTimestampZero(): void
    {
        $commandTester = $this->buildCommandTester();
        $commandTester->execute(['expirationTimestamp' => '0']);

        self::assertEquals(1, $commandTester->getStatusCode());
    }

    /**
     * @test
     */
    public function executeReturnsSuccessWithNoExpiringUsers(): void
    {
        $this->accessRepositoryMock->method('findExpirationUsers')->willReturn([]);

        $commandTester = $this->buildCommandTester();
        $commandTester->execute(['expirationTimestamp' => '14']);

        self::assertEquals(0, $commandTester->getStatusCode());
    }

    /**
     * @test
     */
    public function executeSkipsNotificationForMissingUser(): void
    {
        $accessEntry = $this->createMock(Access::class);
        $accessEntry->method('getFeUser')->willReturn(777);

        $this->accessRepositoryMock->method('findExpirationUsers')
            ->willReturn([$accessEntry]);

        $this->userRepositoryMock->method('findByUid')->with(777)->willReturn(null);

        $commandTester = $this->buildCommandTester();
        $commandTester->execute(['expirationTimestamp' => '14']);

        self::assertEquals(0, $commandTester->getStatusCode());
        self::assertStringContainsString('Skip expiration notification', $commandTester->getDisplay());
    }

    /**
     * @test
     */
    public function executeOutputsFoundUsersCountAndSuccessMessage(): void
    {
        $this->accessRepositoryMock->method('findExpirationUsers')->willReturn([]);

        $commandTester = $this->buildCommandTester();
        $commandTester->execute(['expirationTimestamp' => '30']);

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('0 fe_users', $output);
        self::assertStringContainsString('successfully', $output);
    }
}
