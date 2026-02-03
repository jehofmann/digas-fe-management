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
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Exception\StopActionException;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

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

        // remove "disabled", "start_time" & "end_time" column to show hidden access
        unset($GLOBALS['TCA']['tx_digasfemanagement_domain_model_access']['ctrl']['enablecolumns']['disabled']);
        unset($GLOBALS['TCA']['tx_digasfemanagement_domain_model_access']['ctrl']['enablecolumns']['start_time']);
        unset($GLOBALS['TCA']['tx_digasfemanagement_domain_model_access']['ctrl']['enablecolumns']['end_time']);
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

        // if user is not part of the admin user group, he can only see its own requests
        if ($this->isAdminAccessGranted() === false) {
            $user = $this->user;
        } else if($user === null) {
            // special case: an admin user will see its own requests
            $user = $this->user;
        }

        $documents = $this->accessRepository->findRequestsForUser($user->getUid());

        // sort records by access granted / pending
        /** @var Access $document */
        foreach ($documents as $document) {
            // access rejected documents: hidden and rejected are true
            if ($document->getHidden() === true && $document->getRejected() === true) {
                $accessRejected[] = $document;
                // documents about whose rejections the user has not yet been informed
                if (!$document->getInformUser() && !$document->getAccessGrantedNotification()) {
                    $informUser++;
                }
            } // access pending documents: hidden is true
            elseif ($document->getHidden() === true) {
                $accessPending[] = $document;
            } // access expired documents: endTime is lower than today's date
            elseif ($document->getEndTime() < time()) {
                $accessExpired[] = $document;
            } // access granted documents
            else {
                $accessGranted[] = $document;
                // documents about whose access the user has not yet been informed
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
            'informUser' => $informUser,
            'isAdminUser' => $this->isAdminAccessGranted()
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
     */
    public function informUserAction(User $user)
    {
        if ($this->isAdminAccessGranted() === false) {
            return;
        }

        /** @var Access $documentAccess */
        foreach ($user->getKitodoDocumentAccess() as $documentAccess) {
            if (!$documentAccess->getInformUser()) {
                $documentAccess->setInformUser(true);
                $this->accessRepository->update($documentAccess);
            }
        }

        $this->persistenceManager->persistAll();

        $accessEntries = $this->accessRepository->findAccessGrantedEntriesByUser($user->getUid());
        if (!empty($accessEntries)) {
            $this->notifyUser($user, $accessEntries);
            $this->persistenceManager->persistAll();
        }

        $this->addFlashMessage(
            LocalizationUtility::translate($this->settings['languageFile'] . ':access.user.inform.queued')
        );

        //redirect to list view
        $this->redirect('list', null, null, ['user' => $user]);
    }

    /**
     * Prepare and send access granted/rejected notification
     *
     * @param User $user
     * @param Access[] $accessEntries
     * @return void
     */
    protected function notifyUser(User $user, array $accessEntries): void
    {
        if (empty($accessEntries)) {
            return;
        }

        $documentsList = [];
        foreach ($accessEntries as $accessEntry) {
            $dlfDocument = $accessEntry->getDlfDocument();
            if (!$dlfDocument) {
                continue; // Skip entries without valid document
            }

            $documentsList[] = [
                'recordId' => $dlfDocument->getRecordId(),
                'documentTitle' => $dlfDocument->getTitle(),
                'endTime' => $accessEntry->getEndTime(),
                'rejected' => $accessEntry->getRejected(),
                'rejectedReason' => $accessEntry->getRejectedReason()
            ];

            $notificationTimestamp = strtotime('now');
            $this->updateAccessEntry($accessEntry, $notificationTimestamp);
        }

        if (!empty($documentsList)) {
            $this->sendNotificationEmail($user, $documentsList);
        }
    }

    /**
     * Update access entry after notification
     *
     * @param Access $accessEntry
     * @param int $notificationTimestamp
     * @return void
     */
    protected function updateAccessEntry(Access $accessEntry, int $notificationTimestamp): void
    {
        $accessEntry->setAccessGrantedNotification($notificationTimestamp);
        $accessEntry->setInformUser(false);
        $this->accessRepository->update($accessEntry);
    }

    /**
     * Send notification email to user
     *
     * @param User $user
     * @param array $documentsList
     * @return void
     */
    protected function sendNotificationEmail(User $user, array $documentsList): void
    {
        try {
            $this->initUserLocale($user);

            $userEmail = $user->getEmail();
            $userFullName = $user->getFullName();
            if (!GeneralUtility::validEmail($userEmail)) {
                $this->addFlashMessage(
                    'Keine gültige E-Mail-Adresse für Benutzer',
                    '',
                    AbstractMessage::WARNING
                );
                return;
            }

            if (empty($this->settings['adminEmail']) || empty($this->settings['adminName'])) {
                $this->addFlashMessage(
                    'Admin-E-Mail-Konfiguration fehlt in den Einstellungen',
                    '',
                    AbstractMessage::ERROR
                );
                return;
            }

            $email = GeneralUtility::makeInstance(MailMessage::class);

            $textEmail = $this->generateNotificationEmail(
                $documentsList,
                'EXT:digas_fe_management/Resources/Private/Templates/Email/Text/KitodoAccessGrantedNotification.html'
            );
            $htmlEmail = $this->generateNotificationEmail(
                $documentsList,
                'EXT:digas_fe_management/Resources/Private/Templates/Email/Html/KitodoAccessGrantedNotification.html',
                'html'
            );

            $emailSubject = LocalizationUtility::translate('kitodoAccessGrantedNotification.email.subject', 'DigasFeManagement') ?? 'Dokumentenzugriff';

            $email->setSubject($emailSubject)
                ->setFrom([
                    $this->settings['adminEmail'] => $this->settings['adminName']
                ])
                ->setTo([
                    $userEmail => $userFullName
                ])
                ->text($textEmail)
                ->html($htmlEmail)
                ->send();

        } catch (\Exception $e) {
            $this->addFlashMessage(
                'E-Mail konnte nicht versendet werden: ' . $e->getMessage(),
                '',
                AbstractMessage::ERROR
            );
        }
    }

    /**
     * Generate notification mail content
     *
     * @param array $documentsList
     * @param string $emailTemplate
     * @param string $emailType
     * @return string
     */
    protected function generateNotificationEmail(array $documentsList, string $emailTemplate, string $emailType = 'text'): string
    {
        $htmlView = GeneralUtility::makeInstance(StandaloneView::class);
        $htmlView->setFormat($emailType);
        $htmlView->setTemplatePathAndFilename($emailTemplate);

        $site = GeneralUtility::makeInstance(SiteFinder::class)->getSiteByPageId($this->settings['pids']['loginPage']);
        $loginUrl = (string)$site->getRouter()->generateUri($this->settings['pids']['loginPage']);
        $htmlView->assignMultiple([
            'loginUrl' => $loginUrl,
            'documentsList' => $documentsList
        ]);

        return $htmlView->render();
    }

    /**
     * Init user locale to send emails in users selected language
     *
     * @param User $user
     * @return void
     */
    protected function initUserLocale(User $user): void
    {
        switch ($user->getLocale()) {
            case '1':
                setlocale(LC_ALL, 'en_US.utf8');
                $GLOBALS['LANG']->init('en');
                break;
            case '0':
            default:
                setlocale(LC_ALL, 'de_DE.utf8');
                $GLOBALS['LANG']->init('de');
                break;
        }
    }

    /**
     * @param User $user
     * @param Access|null $access
     * @return void
     */
    public function newAction(User $user, Access $access = null)
    {
        if ($this->isAdminAccessGranted() === false) {
             return;
        }

        $this->view->assignMultiple([
            'user' => $user,
            'access' => $access
        ]);
    }

    /**
     * @param User $user
     * @param Access $access
     * @return void
     * @throws IllegalObjectTypeException
     * @throws StopActionException
     */
    public function createAction(User $user, Access $access)
    {
        if ($this->isAdminAccessGranted() === false) {
             return;
        }

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
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     * @throws StopActionException
     */
    public function approveAction(User $user, Access $access)
    {
        if ($this->isAdminAccessGranted() === false) {
             return;
        }

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

        // set record unhidden - set start_time & end_time
        $access->setStartTimeString('today');
        $access->setHidden(false);

        // (re-)set reject state and reason
        $access->setRejected(false);
        $access->setRejectedReason('');

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

        // start_time < end_time
        if ($access->getEndTime() && $access->getEndTime() < strtotime("today + 1day")) {

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
        if ($this->isAdminAccessGranted() === false) {
            throw  new StopActionException('Access denied');
        }

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
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     */
    public function rejectAction(Access $access, User $user)
    {
        if ($this->isAdminAccessGranted() === false) {
             return;
        }

        $access->setHidden(true);
        $access->setRejected(true);
        $access->setStartTime(0);
        $access->setEndTime(0);
        $access->setExpireNotification(0);
        $access->setAccessGrantedNotification(0);
        $access->setInformUser(false);

        if ($access->getRejectedReason()) {
            $rejectedReason = strip_tags($access->getRejectedReason());
            $access->setRejectedReason($rejectedReason);
        }

        // update access to document
        $this->accessRepository->update($access);

        // add success message
        $message = LocalizationUtility::translate($this->settings['languageFile'] . ':access.success.deleted');
        $documentTitle = $access->getDlfDocument() ? $access->getDlfDocument()->getTitle() : $access->getRecordId();
        $this->addFlashMessage(sprintf($message, $documentTitle));

        // redirect to list view
        $this->redirect('list', null, null, ['user' => $user]);
    }


    /**
     * Check if the current fe_user is allowed to do administration tasks
     *
     * @return boolean
     */
    protected function isAdminAccessGranted()
    {
        $isAdmin = false;

        // first check, if we are allowed to access this action (be part of feUserAdminGroups)
        $feUserAdminGroups = array_intersect(
            explode(',', $GLOBALS['TSFE']->fe_user->user['usergroup']),
            explode(',', $this->settings['feUserAdminGroups'])
        );

        // if user is not part of the admin user group, he can only see its own requests
        if (!empty($feUserAdminGroups)) {
            $isAdmin = true;
        }

        return $isAdmin;
    }

}
