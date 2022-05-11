<?php

namespace Slub\DigasFeManagement\Controller;

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

use In2code\Femanager\Controller\AbstractController;
use Slub\DigasFeManagement\Domain\Model\Access;
use Slub\DigasFeManagement\Domain\Model\User;
use Slub\DigasFeManagement\Domain\Repository\AccessRepository;
use Slub\DigasFeManagement\Domain\Repository\KitodoDocumentRepository;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Extbase\Mvc\Exception\StopActionException;
use TYPO3\CMS\Extbase\Mvc\Exception\UnsupportedRequestTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Class AccessController
 */
class AccessController extends AbstractController
{
    /**
     * accessRepository
     *
     * @var AccessRepository
     */
    protected $accessRepository = null;

    /**
     * @param AccessRepository $accessRepository
     */
    public function injectAccessRepository(AccessRepository $accessRepository)
    {
        $this->accessRepository = $accessRepository;
    }

    /**
     * kitodoDocumentRepository
     *
     * @var KitodoDocumentRepository
     */
    protected $kitodoDocumentRepository = null;

    /**
     * @param KitodoDocumentRepository $kitodoDocumentRepository
     */
    public function injectKitodoDocumentRepository(KitodoDocumentRepository $kitodoDocumentRepository)
    {
        $this->kitodoDocumentRepository = $kitodoDocumentRepository;
    }

    /**
     *
     */
    public function initializeAction()
    {
        parent::initializeAction();

        // remove "disabled", "starttime" & "endtime" column to show hidden access
        unset($GLOBALS['TCA']['tx_digasfemanagement_domain_model_access']['ctrl']['enablecolumns']['disabled']);
        unset($GLOBALS['TCA']['tx_digasfemanagement_domain_model_access']['ctrl']['enablecolumns']['starttime']);
        unset($GLOBALS['TCA']['tx_digasfemanagement_domain_model_access']['ctrl']['enablecolumns']['endtime']);
    }

    /**
     * List granted and pending access for documents for single fe_user
     *
     * @param User|null $user
     * @return void
     */
    public function listAction(User $user = null)
    {
        $accessGranted = [];
        $accessPending = [];
        $accessExpired = [];
        $accessRejected = [];
        $informUser = 0;

        $documents = $this->accessRepository->findRequestsForUser($user->getUid());

        //sort records by access granted / pending
        /** @var Access $document */
        foreach ($documents as $document) {
            // access rejected documents: hidden and rejected are true
            if ($document->getHidden() === true && $document->getRejected() === true) {
                $accessRejected[] = $document;
                //documents about whose rejections the user has not yet been informed
                if (!$document->getInformUser() && !$document->getAccessGrantedNotification()) {
                    $informUser++;
                }
            }// access pending documents: hidden is true
            elseif ($document->getHidden() === true) {
                $accessPending[] = $document;
            } // access expired documents: endTime is lower than today's date
            elseif ($document->getEndtime() < time()) {
                $accessExpired[] = $document;
            } // access granted documents
            else {
                $accessGranted[] = $document;
                //documents about whose access the user has not yet been informed
                if (!$document->getInformUser() && !$document->getAccessGrantedNotification()) {
                    $informUser++;
                }
            }
        }

        // get request arguments for error handling
        $arguments = $this->request->getArguments();

        $this->view->assignMultiple([
            'accessGranted' => $accessGranted,
            'accessPending' => $accessPending,
            'accessExpired' => $accessExpired,
            'accessRejected' => $accessRejected,
            'user' => $user,
            'errorItem' => $arguments['error'],
            'informUser' => $informUser
        ]);

        if ($informUser) {
            $this->addFlashMessage(
                LocalizationUtility::translate($this->settings['languageFile'] . ':access.user.inform'), '' ,1
            );
        }
    }

    /**
     * @param User $user
     * @throws IllegalObjectTypeException
     * @throws StopActionException
     * @throws UnknownObjectException
     * @throws UnsupportedRequestTypeException
     */
    public function informUserAction(User $user)
    {
        /** @var Access $documentAccess */
        foreach ($user->getKitodoDocumentAccess() as $documentAccess) {
            if (!$documentAccess->getInformUser()) {
                $documentAccess->setInformUser(1);
                $this->accessRepository->update($documentAccess);
            }
        }

        $this->addFlashMessage(
            LocalizationUtility::translate($this->settings['languageFile'] . ':access.user.inform.queued')
        );

        //redirect to list view
        $this->redirect('list', null, null, ['user' => $user]);
    }

    /**
     * @param User $user
     * @param Access|null $access
     * @return void
     */
    public function newAction(User $user, Access $access = null)
    {
        $this->view->assignMultiple([
            'user' => $user,
            'access' => $access
        ]);
    }

    /**
     * @param User $user
     * @param Access $access
     * @throws IllegalObjectTypeException
     * @throws StopActionException
     * @throws UnsupportedRequestTypeException
     */
    public function createAction(User $user, Access $access)
    {
        // process and validate form data
        $access = $this->processFormData($user, $access, 'new');

        // add to database
        $this->accessRepository->add($access);

        // add success message
        $message = LocalizationUtility::translate($this->settings['languageFile'] . ':access.success.granted');
        $this->addFlashMessage(sprintf($message, $access->getRecordId()));

        // redirect to list view
        $this->redirect('list', null, null, ['user' => $user]);
    }

    /**
     * approve action
     *
     * @param User $user
     * @param Access $access
     * @return void
     * @throws UnsupportedRequestTypeException
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     * @throws StopActionException
     */
    public function approveAction(User $user, Access $access)
    {
        // process and validate form data
        $access = $this->processFormData($user, $access);

        // update access to document
        $this->accessRepository->update($access);

        // add success message
        $arguments = $this->request->getArguments();
        $message = LocalizationUtility::translate($this->settings['languageFile'] . ':access.success.' . ($arguments['edit'] ? 'updated' : 'granted'));
        $this->addFlashMessage(sprintf($message, $access->getRecordId()));

        // redirect to list view
        $this->redirect('list', null, null, ['user' => $user]);
    }


    /**
     * @param User $user
     * @param Access $access
     * @param string $action
     * @return Access
     * @throws StopActionException
     */
    protected function processFormData(User $user, Access $access, string $action = 'list'): Access
    {
        $access->setEndtime($access->getEndtimeString());

        // set dlfDocument for new entries
        if (!$access->getDlfDocument() && $access->getRecordId() && $action === 'new') {
            $dlfDocument = $this->kitodoDocumentRepository->findDocumentsByRecordId([$access->getRecordId()]);
            if (!empty($dlfDocument) && isset($dlfDocument[0])) {
                $access->setDlfDocument($dlfDocument[0]);
            }
        }
        // set feUser for new entries
        if (!$access->getFeUser() && $action === 'new') {
            $access->setFeUser($user->getUid());
        }

        // check if validation failed
        if ($this->validateApproval($access, $user, $action) === false) {
            $this->forward($action, null, null, ['user' => $user, 'access' => $access, 'error' => $access->getUid()]);
        }

        // set record unhidden - set starttime & endtime
        $access->setStarttime(strtotime('today'));
        $access->setHidden(false);
        $access->setRejected(false);

        return $access;
    }

    /**
     * validation of access properties
     *
     * @param Access $access
     * @param User $user
     * @param string $action
     * @return bool
     */
    public function validateApproval(Access $access, User $user, string $action) : bool
    {
        $validate = true;

        // starttime < endtime
        if ($access->getEndtimeString() && $access->getEndtimeString() < strtotime("today + 1day")) {

            $validate = false;
            $this->addFlashMessage(
                LocalizationUtility::translate($this->settings['languageFile'] . ':access.error.endtime'),
                '', AbstractMessage::ERROR
            );
        }

        // recordId empty
        if (!$access->getRecordId() && $action === 'new') {
            $validate = false;
            $this->addFlashMessage(
                LocalizationUtility::translate($this->settings['languageFile'] . ':access.error.recordId.missing'),
                '', AbstractMessage::ERROR
            );
        }

        // dlfDocument not found
        if ($access->getRecordId() && !$access->getDlfDocument() && $action === 'new') {
            $validate = false;
            $this->addFlashMessage(
                LocalizationUtility::translate($this->settings['languageFile'] . ':access.error.recordId.notFound'),
                '', AbstractMessage::ERROR
            );
        }

        // dlfDocument already exists in access list
        if ($access->getDlfDocument() && $action === 'new') {
            $checkAvailableDocuments = [];

            if ($user->getKitodoDocumentAccess()) {
                foreach ($user->getKitodoDocumentAccess() as $document) {
                    $checkAvailableDocuments[$document->getRecordId()] = $document;
                }

                if (array_key_exists($access->getRecordId(), $checkAvailableDocuments)) {
                    $validate = false;
                    $this->addFlashMessage(
                        LocalizationUtility::translate($this->settings['languageFile'] . ':access.error.dlfDocument.exists'),
                        '', AbstractMessage::ERROR
                    );
                }
            }

        }

        return $validate;
    }


    /**
     * @param Access $access
     * @param User $user
     * @return void
     */
    public function rejectReasonAction(Access $access, User $user)
    {
        $this->view->assignMultiple([
            'access' => $access,
            'user' => $user
        ]);
    }

    /**
     * refuse access:
     *  - remove access-record from fe_user
     *  - delete access-record in db (deleted=1)
     *
     * @param Access $access
     * @param User $user
     * @return void
     * @throws StopActionException
     * @throws UnsupportedRequestTypeException
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     */
    public function rejectAction(Access $access, User $user)
    {
        $access->setHidden(1);
        $access->setRejected(1);
        $access->setStarttime(0);
        $access->setEndtime(0);
        $access->setExpireNotification(0);
        $access->setAccessGrantedNotification(0);
        $access->setInformUser(0);

        if ($access->getRejectedReason()) {
            $rejectedReason = strip_tags($access->getRejectedReason());
            $access->setRejectedReason($rejectedReason);
        }

        // update access to document
        $this->accessRepository->update($access);

        // add success message
        $message = LocalizationUtility::translate($this->settings['languageFile'] . ':access.success.deleted');
        $this->addFlashMessage(sprintf($message, $access->getDlfDocument()->getTitle()));

        // redirect to list view
        $this->redirect('list', null, null, ['user' => $user]);
    }
}
