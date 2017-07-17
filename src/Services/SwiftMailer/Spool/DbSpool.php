<?php

/*
 * This file is part of the Blast Project package.
 *
 * Copyright (C) 2015-2017 Libre Informatique
 *
 * This file is licenced under the GNU LGPL v3.
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Librinfo\EmailCRMBundle\Services\SwiftMailer\Spool;

use Librinfo\EmailBundle\Services\SwiftMailer\Spool\DbSpool as BaseDbSpool;
use Librinfo\EmailBundle\Services\SwiftMailer\Spool\SpoolStatus;
use Librinfo\EmailBundle\Services\InlineAttachments;
use Librinfo\EmailCRMBundle\Services\SwiftMailer\DecoratorPlugin\Replacements;

/**
 * Class DbSpool.
 */
class DbSpool extends BaseDbSpool
{
    /**
     * Sends messages using the given transport instance.
     *
     * @param \Swift_Transport $transport         A transport instance
     * @param string[]         &$failedRecipients An array of failures by-reference
     *
     * @return int The number of sent emails
     */
    public function flushQueue(\Swift_Transport $transport, &$failedRecipients = null)
    {
        $replacements = new Replacements($this->manager);
        $decorator = new \Swift_Plugins_DecoratorPlugin($replacements);

        $transport->registerPlugin($decorator);

        if (!$transport->isStarted()) {
            $transport->start();
        }

        $emails = $this->repository->findBy(
                array('status' => SpoolStatus::STATUS_READY, 'environment' => $this->environment), null
        );

        if (!count($emails)) {
            return 0;
        }

        $failedRecipients = (array) $failedRecipients;
        $count = 0;
        $time = time();

        foreach ($emails as $email) {
            $email->setStatus(SpoolStatus::STATUS_PROCESSING);

            $this->updateEmail($email);

            $message = unserialize(base64_decode($email->getMessage()));

            $addresses = $email->getFieldToAsArray();

            foreach ($email->getPositions() as $position) {
                $name = sprintf(
                        '%s %s', $position->getIndividual()->getFirstName(), $position->getIndividual()->getName()
                );

                if ($position->getEmail()) {
                    $addresses[$position->getEmail()] = $name;
                } elseif ($position->getIndividual()->getEmail()) {
                    $addresses[$position->getIndividual->getEmail()] = $name;
                } else {
                    continue;
                }
            }

            foreach ($email->getOrganisms() as $organism) {
                if ($organism->getEmail()) {
                    if ($organism->isIndividual()) {
                        $name = sprintf(
                            '%s %s', $organism->getFirstName(), $organism->getName()
                        );

                        $addresses[$organism->getEmail()] = $name;
                    } else {
                        $addresses[$organism->getEmail()] = $organism->getName();
                    }
                }
            }

            foreach ($email->getCircles() as $circle) {
                foreach ($circle->getOrganisms() as $organism) {
                    if ($organism->isIndividual()) {
                        $name = sprintf(
                            '%s %s', $organism->getFirstName(), $organism->getName()
                        );

                        $addresses[$organism->getEmail()] = $name;
                    } else {
                        $addresses[$organism->getEmail()] = $organism->getName();
                    }
                }
            }

            foreach ($addresses as $address) {
                $message->setTo(trim($address));
                $content = $email->getContent();

                if ($email->getTracking()) {
                    $tracker = new Tracking($this->router);
                    $content = $tracker->addTracking($content, $address, $email->getId());
                }

                $attachmentsHandler = new InlineAttachments();
                $content = $attachmentsHandler->handle($content, $message);

                $message->setBody($content);

                try {
                    $count += $transport->send($message, $failedRecipients);
                    sleep($this->pauseTime);
                } catch (\Swift_TransportException $e) {
                    $email->setStatus(SpoolStatus::STATUS_READY);
                    $this->updateEmail($email);
                    dump($e->getMessage());
                }
            }
            $email->setStatus(SpoolStatus::STATUS_COMPLETE);

            $this->updateEmail($email);

            if ($this->getTimeLimit() && (time() - $time) >= $this->getTimeLimit()) {
                break;
            }
        }

        return $count;
    }
}
