<?php

/*  Poweradmin, a friendly web-based admin tool for PowerDNS.
 *  See <https://www.poweradmin.org> for more details.
 *
 *  Copyright 2007-2010 Rejo Zenger <rejo@zenger.nl>
 *  Copyright 2010-2025 Poweradmin Development Team
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * Script that handles requests to add new records to zone templates
 *
 * @package     Poweradmin
 * @copyright   2007-2010 Rejo Zenger <rejo@zenger.nl>
 * @copyright   2010-2025 Poweradmin Development Team
 * @license     https://opensource.org/licenses/GPL-3.0 GPL
 */

namespace Poweradmin\Application\Controller;

use Poweradmin\BaseController;
use Poweradmin\Domain\Model\RecordType;
use Poweradmin\Domain\Model\UserManager;
use Poweradmin\Domain\Model\ZoneTemplate;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AddZoneTemplRecordController extends BaseController
{
    protected ValidatorInterface $validator;

    public function __construct(array $request)
    {
        parent::__construct($request);
        $this->validator = $this->validator;
    }

    public function run(): void
    {
        $constraints = [
            'id' => [
                new Assert\NotBlank()
            ]
        ];

        $this->setValidationConstraints($constraints);

        if (!$this->doValidateRequest($_GET)) {
            $this->showFirstValidationError($_GET);
        }

        $zone_templ_id = htmlspecialchars($_GET['id']);
        $owner = ZoneTemplate::get_zone_templ_is_owner($this->db, $zone_templ_id, $_SESSION['userid']);
        $perm_godlike = UserManager::verify_permission($this->db, 'user_is_ueberuser');
        $perm_master_add = UserManager::verify_permission($this->db, 'zone_master_add');

        $this->checkCondition(!($perm_godlike || $perm_master_add && $owner), _("You do not have the permission to delete zone templates."));

        if ($this->isPost()) {
            $this->validateCsrfToken();

            $constraints = [
                'name' => [
                    new Assert\NotBlank()
                ],
                'type' => [
                    new Assert\NotBlank()
                ],
                'content' => [
                    new Assert\NotBlank()
                ],
                'prio' => [
                    new Assert\NotBlank()
                ],
                'ttl' => [
                    new Assert\NotBlank()
                ]
            ];

            $this->setValidationConstraints($constraints);

            if ($this->doValidateRequest($_POST)) {
                $this->addZoneTemplRecord();
            } else {
                $this->showFirstValidationError($_POST);
            }
        } else {
            $this->showAddZoneTemplRecord();
        }
    }

    private function addZoneTemplRecord(): void
    {
        $zone_templ_id = htmlspecialchars($_GET['id']);
        $name = $_POST['name'] ?? "[ZONE]";
        $type = $_POST['type'] ?? "";
        $content = $_POST['content'] ?? "";
        $prio = $_POST['prio'] ?? 0;
        $dns_ttl = $this->config('dns_ttl');
        $ttl = $_POST['ttl'] ?? $dns_ttl;

        $template = new ZoneTemplate($this->db, $this->getConfig());

        if ($template->add_zone_templ_record($zone_templ_id, $name, $type, $content, $ttl, $prio)) {
            $this->setMessage('edit_zone_templ', 'success', 'The record was successfully added.');
            $this->redirect('index.php', ['page' => 'edit_zone_templ', 'id' => $zone_templ_id]);
        } else {
            $this->showAddZoneTemplRecord();
        }
    }

    private function showAddZoneTemplRecord(): void
    {
        $zone_templ_id = htmlspecialchars($_GET['id']);
        $templ_details = ZoneTemplate::get_zone_templ_details($this->db, $zone_templ_id);
        $name = $_POST['name'] ?? "[ZONE]";
        $type = $_POST['type'] ?? "";
        $content = $_POST['content'] ?? "";
        $prio = $_POST['prio'] ?? 0;
        $dns_ttl = $this->config('dns_ttl');
        $ttl = $_POST['ttl'] ?? $dns_ttl;

        $this->render('add_zone_templ_record.html', [
            'templ_name' => $templ_details['name'],
            'zone_templ_id' => $zone_templ_id,
            'name' => htmlspecialchars($name),
            'type' => htmlspecialchars($type),
            'record_types' => RecordType::getAllTypes(),
            'content' => htmlspecialchars($content),
            'prio' => htmlspecialchars($prio),
            'ttl' => htmlspecialchars($ttl),
        ]);
    }
}
