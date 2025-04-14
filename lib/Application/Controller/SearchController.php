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
 * Script that handles search requests
 *
 * @package     Poweradmin
 * @copyright   2007-2010 Rejo Zenger <rejo@zenger.nl>
 * @copyright   2010-2025 Poweradmin Development Team
 * @license     https://opensource.org/licenses/GPL-3.0 GPL
 */

namespace Poweradmin\Application\Controller;

use Poweradmin\Application\Query\RecordSearch;
use Poweradmin\Application\Query\ZoneSearch;
use Poweradmin\Application\Service\PaginationService;
use Poweradmin\BaseController;
use Poweradmin\Domain\Model\Permission;

class SearchController extends BaseController
{
    public function run(): void
    {
        $this->checkPermission('search', _("You do not have the permission to perform searches."));

        $parameters = [
            'query' => '',
            'zones' => true,
            'records' => true,
            'wildcard' => true,
            'reverse' => true,
            'comments' => false,
        ];

        $totalZones = 0;
        $searchResultZones = [];
        $zones_page = 1;
        $totalRecords = 0;
        $searchResultRecords = [];
        $records_page = 1;

        list($zone_sort_by, $zone_sort_direction) = $this->getSortOrder('zone_sort_by', ['name', 'type', 'count_records', 'fullname']);
        list($record_sort_by, $record_sort_direction) = $this->getSortOrder('record_sort_by', ['name', 'type', 'prio', 'content', 'ttl', 'disabled']);

        $_SESSION['zone_sort_by'] = $zone_sort_by;
        $_SESSION['zone_sort_by_direction'] = $zone_sort_direction;
        $_SESSION['record_sort_by'] = $record_sort_by;
        $_SESSION['record_sort_by_direction'] = $record_sort_direction;

        // Get default rows per page from config
        $default_rowamount = $this->config->get('interface', 'rows_per_page', 10);

        // Create pagination service
        $paginationService = new PaginationService();
        
        // Get zones rows per page
        $zone_rowamount = $paginationService->getUserRowsPerPage($default_rowamount);
        // Override with POST parameter if available for zones
        if ($this->isPost() && isset($_POST['zones_rows_per_page']) && is_numeric($_POST['zones_rows_per_page'])) {
            $post_rows_per_page = (int)$_POST['zones_rows_per_page'];
            // Validate against allowed values
            if (in_array($post_rows_per_page, [10, 20, 50, 100])) {
                $zone_rowamount = $post_rows_per_page;
            }
        }
        
        // Get records rows per page
        $record_rowamount = $paginationService->getUserRowsPerPage($default_rowamount);
        // Override with POST parameter if available for records
        if ($this->isPost() && isset($_POST['records_rows_per_page']) && is_numeric($_POST['records_rows_per_page'])) {
            $post_rows_per_page = (int)$_POST['records_rows_per_page'];
            // Validate against allowed values
            if (in_array($post_rows_per_page, [10, 20, 50, 100])) {
                $record_rowamount = $post_rows_per_page;
            }
        }
        
        // Backward compatibility
        if ($this->isPost() && isset($_POST['rows_per_page']) && is_numeric($_POST['rows_per_page'])) {
            $post_rows_per_page = (int)$_POST['rows_per_page'];
            // Validate against allowed values
            if (in_array($post_rows_per_page, [10, 20, 50, 100])) {
                $zone_rowamount = $post_rows_per_page;
                $record_rowamount = $post_rows_per_page;
            }
        }
        $iface_zone_comments = $this->config->get('interface', 'show_zone_comments', true);
        $iface_record_comments = $this->config->get('interface', 'show_record_comments', false);

        if ($this->isPost()) {
            $this->validateCsrfToken();

            $parameters['query'] = !empty($_POST['query']) ? htmlspecialchars($_POST['query']) : '';

            $parameters['zones'] = isset($_POST['zones']) ? htmlspecialchars($_POST['zones']) : false;
            $parameters['records'] = isset($_POST['records']) ? htmlspecialchars($_POST['records']) : false;
            $parameters['wildcard'] = isset($_POST['wildcard']) ? htmlspecialchars($_POST['wildcard']) : false;
            $parameters['reverse'] = isset($_POST['reverse']) ? htmlspecialchars($_POST['reverse']) : false;
            $parameters['comments'] = isset($_POST['comments']) ? htmlspecialchars($_POST['comments']) : false;

            $zones_page = isset($_POST['zones_page']) ? (int)$_POST['zones_page'] : 1;

            $permission_view = Permission::getViewPermission($this->db);

            $db_type = $this->config->get('database', 'type', 'mysql');

            $zoneSearch = new ZoneSearch($this->db, $this->getConfig(), $db_type);
            $searchResultZones = $zoneSearch->searchZones(
                $parameters,
                $permission_view,
                $zone_sort_by,
                $zone_sort_direction,
                $zone_rowamount,
                $iface_zone_comments,
                $zones_page
            );

            $totalZones = $zoneSearch->getTotalZones($parameters, $permission_view);

            $records_page = isset($_POST['records_page']) ? (int)$_POST['records_page'] : 1;

            $iface_search_group_records = $this->config->get('interface', 'search_group_records', false);
            $recordSearch = new RecordSearch($this->db, $this->getConfig(), $db_type);
            $searchResultRecords = $recordSearch->searchRecords(
                $parameters,
                $permission_view,
                $record_sort_by,
                $record_sort_direction,
                $iface_search_group_records,
                $record_rowamount,
                $iface_record_comments,
                $records_page,
            );

            $totalRecords = $recordSearch->getTotalRecords($parameters, $permission_view, $iface_search_group_records);
        }

        $this->showSearchForm($parameters, $searchResultZones, $searchResultRecords, $zone_sort_by, $zone_sort_direction, $record_sort_by, $record_sort_direction, $totalZones, $totalRecords, $zones_page, $records_page, $zone_rowamount, $record_rowamount, $iface_zone_comments, $iface_record_comments);
    }

    private function showSearchForm($parameters, $searchResultZones, $searchResultRecords, $zone_sort_by, $zone_sort_direction, $record_sort_by, $record_sort_direction, $totalZones, $totalRecords, $zones_page, $records_page, $zone_rowamount, $record_rowamount, $iface_zone_comments, $iface_record_comments): void
    {
        $this->render('search.html', [
            'zone_sort_by' => $zone_sort_by,
            'zone_sort_direction' => $zone_sort_direction,
            'record_sort_by' => $record_sort_by,
            'record_sort_direction' => $record_sort_direction,
            'query' => $parameters['query'],
            'search_by_zones' => $parameters['zones'],
            'search_by_records' => $parameters['records'],
            'search_by_comments' => $parameters['comments'],
            'search_by_wildcard' => $parameters['wildcard'],
            'search_by_reverse' => $parameters['reverse'],
            'has_zones' => !empty($searchResultZones),
            'has_records' => !empty($searchResultRecords),
            'found_zones' => $searchResultZones,
            'found_records' => $searchResultRecords,
            'total_zones' => $totalZones,
            'total_records' => $totalRecords,
            'zones_page' => $zones_page,
            'records_page' => $records_page,
            'zone_rowamount' => $zone_rowamount,
            'record_rowamount' => $record_rowamount,
            'iface_zone_comments' => $iface_zone_comments,
            'iface_record_comments' => $iface_record_comments,
            'edit_permission' => Permission::getEditPermission($this->db),
            'user_id' => $_SESSION['userid'],
        ]);
    }

    private function getSortOrder(string $name, array $allowedValues): array
    {
        $sortOrder = 'name';
        $sortDirection = 'ASC';

        if (isset($_POST[$name]) && in_array($_POST[$name], $allowedValues)) {
            $sortOrder = $_POST[$name];
        } elseif (isset($_SESSION[$name]) && in_array($_SESSION[$name], $allowedValues)) {
            $sortOrder = $_SESSION[$name];
        }

        if (isset($_POST[$name . '_direction']) && in_array(strtoupper($_POST[$name . '_direction']), ['ASC', 'DESC'])) {
            $sortDirection = strtoupper($_POST[$name . '_direction']);
        } elseif (isset($_SESSION[$name . '_direction']) && in_array(strtoupper($_SESSION[$name . '_direction']), ['ASC', 'DESC'])) {
            $sortDirection = strtoupper($_SESSION[$name . '_direction']);
        }

        return [$sortOrder, $sortDirection];
    }
}
