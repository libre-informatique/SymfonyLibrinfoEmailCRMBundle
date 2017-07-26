<?php

/*
 * This file is part of the Lisem Project.
 *
 * Copyright (C) 2015-2017 Libre Informatique
 *
 * This file is licenced under the GNU GPL v3.
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Librinfo\EmailCRMBundle\Services;

use Librinfo\EmailBundle\Services\AddressManager as BaseAddressManager;

/**
 * AddressManager is used to manage addresses as service (librinfo.email.address_manager).
 */
class AddressManager extends BaseAddressManager
{
    /**
     * manageAddresses.
     *
     * @param Email $mail
     *
     * @return array
     */
    public function manageAddresses($mail)
    {

        $addresses = parent::manageAddresses($mail);

        if ($mail->getPositions() === null) {
            $mail->initPositions();
        }

        foreach ($mail->getPositions() as $position) {
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

        if ($mail->getOrganisms() === null) {
            $mail->initOrganisms();
        }

        foreach ($mail->getOrganisms() as $organism) {
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

        if ($mail->getCircles() === null) {
            $mail->initCircles();
        }

        foreach ($mail->getCircles() as $circle) {
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

        return $addresses;
    }
}
