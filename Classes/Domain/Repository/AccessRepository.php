<?php

namespace Slub\DigasFeManagement\Domain\Repository;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2022 SLUB Dresden <typo3@slub-dresden.de>
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
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use Slub\DigasFeManagement\Domain\Model\Access;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * DiGA.Sax Frontend User repository
 */
class AccessRepository extends Repository
{
    /**
     * @var int Storage PID
     */
    protected int $storagePid;

    /**
     * @param int $storagePid
     *
     * @return void
     */
    public function setStoragePid(int $storagePid): void
    {
        $this->storagePid = $storagePid;
    }

    /**
     * @var string
     */
    protected string $tableName = 'tx_digasfemanagement_domain_model_access';

    /**
     * Find all access requests for a given frontend user id.
     *
     * @param int $feUserId
     *
     * @return array
     */
    public function findRequestsForUser(int $feUserId): array
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setIgnoreEnableFields(true);

        return $query->matching(
            $query->logicalAnd([
                $query->equals('fe_user', $feUserId),
            ]))
            ->execute()
            ->toArray();
    }

    /**
     * Count all open access requests for a given frontend user id.
     *
     * @param int $feUserId
     *
     * @return int
     */
    public function countByFeUserAndOpen(int $feUserId): int
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setIgnoreEnableFields(true);

        $constraints = [];
        $constraints[] = $query->equals('fe_user', $feUserId);
        $constraints[] = $query->equals('hidden', true);
        $constraints[] = $query->equals('rejected', 0);

        if (count($constraints)) {
            $query->matching($query->logicalAnd($constraints));
        }

        return $query->count();
    }

    /**
     * Get all access entries for the given user where access has been granted
     * but no notification has been sent yet
     *
     * @param int $feUserId
     *
     * @return Access[]
     *
     * @throws DBALException
     * @throws Exception|\TYPO3\CMS\Extbase\Object\Exception
     */
    public function findAccessGrantedEntriesByUser(int $feUserId): array
    {
        $queryBuilder = $this->getQueryBuilder();

        // start_time + end_time get checked by typo3 core logic
        //remove hidden restriction
        $queryBuilder->getRestrictions()->removeByType(HiddenRestriction::class);

        $rows = $queryBuilder->select('*')
            ->where(
                $queryBuilder->expr()->eq($this->tableName . '.access_granted_notification', 0),
                $queryBuilder->expr()->eq($this->tableName . '.inform_user', 1),
                $queryBuilder->expr()->eq($this->tableName . '.fe_user', $feUserId),
            )
            ->from($this->tableName)
            ->orderBy($this->tableName . '.rejected','ASC')
            ->execute()
            ->fetchAllAssociative();

        $dataMapper = GeneralUtility::makeInstance(ObjectManager::class)->get(DataMapper::class);

        return $dataMapper->map(Access::class, $rows);
    }

    /**
     * Get uids of fe_users with granted access
     *
     * @return Access[]
     *
     * @throws DBALException
     * @throws Exception|\TYPO3\CMS\Extbase\Object\Exception
     */
    public function findAccessGrantedUsers(): array
    {
        $queryBuilder = $this->getQueryBuilder();

        // start_time + end_time get checked by typo3 core logic
        //remove hidden restriction
        $queryBuilder->getRestrictions()->removeByType(HiddenRestriction::class);

        $rows = $queryBuilder->select('*')
            ->where(
                $queryBuilder->expr()->eq($this->tableName . '.access_granted_notification', 0),
                $queryBuilder->expr()->eq($this->tableName . '.inform_user', true),
            )
            ->from($this->tableName)
            ->groupBy($this->tableName . '.fe_user')
            ->execute()
            ->fetchAllAssociative();

        $dataMapper = GeneralUtility::makeInstance(ObjectManager::class)->get(DataMapper::class);
        return $dataMapper->map(Access::class, $rows);
    }

    /**
     * Get uids of fe_users with expiring document access
     *
     * @param int $expirationTimestamp
     *
     * @return Access[]
     *
     * @throws DBALException
     * @throws Exception|\TYPO3\CMS\Extbase\Object\Exception
     */
    public function findExpirationUsers(int $expirationTimestamp): array
    {
        $queryBuilder = $this->getQueryBuilder();

        $rows = $queryBuilder->select('*')
            ->where(
                $queryBuilder->expr()->eq($this->tableName . '.expire_notification', 0),
                $queryBuilder->expr()->lte($this->tableName . '.end_time', $expirationTimestamp)
            )
            ->from($this->tableName)
            ->groupBy($this->tableName . '.fe_user')
            ->execute()
            ->fetchAllAssociative();

        $dataMapper = GeneralUtility::makeInstance(ObjectManager::class)->get(DataMapper::class);
        return $dataMapper->map(Access::class, $rows);
    }

    /**
     * Get all access entries for the given user where access is about to expire
     * but no notification has been sent yet.
     *
     * @param int $feUserId
     * @param int $expirationTimestamp
     *
     * @return array
     *
     * @throws DBALException
     * @throws Exception|\TYPO3\CMS\Extbase\Object\Exception
     */
    public function findExpiringEntriesByUser(int $feUserId, int $expirationTimestamp): array
    {
        $queryBuilder = $this->getQueryBuilder();

        // start_time + end_time get checked by typo3 core logic
        $rows = $queryBuilder->select('*')
            ->where(
                $queryBuilder->expr()->eq($this->tableName . '.expire_notification', 0),
                $queryBuilder->expr()->lte($this->tableName . '.end_time', $expirationTimestamp),
                $queryBuilder->expr()->eq($this->tableName . '.fe_user', $feUserId)
            )
            ->from($this->tableName)
            ->execute()
            ->fetchAllAssociative();

        $dataMapper = GeneralUtility::makeInstance(ObjectManager::class)->get(DataMapper::class);

        return $dataMapper->map(Access::class, $rows);
    }

    /**
     * Get QueryBuilder for the repository's table.
     *
     * @return QueryBuilder
     */
    private function getQueryBuilder(): QueryBuilder
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($this->tableName)->createQueryBuilder();
    }
}
