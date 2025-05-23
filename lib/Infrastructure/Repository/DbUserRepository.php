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

namespace Poweradmin\Infrastructure\Repository;

use PDO;
use Poweradmin\Domain\Model\User;
use Poweradmin\Domain\Model\UserId;
use Poweradmin\Domain\Repository\UserRepository;

class DbUserRepository implements UserRepository
{
    private object $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function canViewOthersContent(UserId $user): bool
    {
        $query = "SELECT DISTINCT u.id
                  FROM users u
                  JOIN perm_templ pt ON u.perm_templ = pt.id
                  JOIN perm_templ_items pti ON pti.templ_id = pt.id
                  JOIN (SELECT id FROM perm_items WHERE name IN ('zone_content_view_others', 'user_is_ueberuser')) pit ON pti.perm_id = pit.id
                  WHERE u.id = :userId";

        $stmt = $this->db->prepare($query);
        $stmt->execute(['userId' => $user->getId()]);

        return (bool)$stmt->fetchColumn();
    }

    public function findByUsername(string $username): ?User
    {
        $stmt = $this->db->prepare('SELECT id, password, use_ldap FROM users WHERE username = ?');
        $stmt->execute([$username]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$data) {
            return null;
        }

        return new User($data['id'], $data['password'], (bool)$data['use_ldap']);
    }

    public function updatePassword(int $userId, string $hashedPassword): bool
    {
        $stmt = $this->db->prepare('UPDATE users SET password = ? WHERE id = ?');
        return $stmt->execute([$hashedPassword, $userId]);
    }

    /**
     * Get user by ID
     *
     * @param int $userId The user ID to fetch
     * @return array|null User data or null if not found
     */
    public function getUserById(int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT id, username, fullname, email, description, active, perm_templ FROM users WHERE id = ?');
        $stmt->execute([$userId]);

        $userData = $stmt->fetch(PDO::FETCH_ASSOC);
        return $userData ?: null;
    }

    /**
     * Get all permissions for a specific user
     *
     * @param int $userId User ID to get permissions for
     * @return array Array of permission names
     */
    public function getUserPermissions(int $userId): array
    {
        // First get the user to check if they exist
        $user = $this->getUserById($userId);
        if (!$user) {
            return [];
        }

        // Query to get all permissions for the user
        $query = "SELECT
            perm_items.name AS permission
            FROM perm_templ_items
            LEFT JOIN perm_items ON perm_items.id = perm_templ_items.perm_id
            LEFT JOIN perm_templ ON perm_templ.id = perm_templ_items.templ_id
            LEFT JOIN users ON perm_templ.id = users.perm_templ
            WHERE users.id = :userId
            ORDER BY perm_items.name";

        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $userPermissions = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $userPermissions[] = $row['permission'];
        }

        return $userPermissions;
    }

    /**
     * Check if a user has admin permissions
     *
     * @param int $userId User ID to check
     * @return bool True if the user is an admin
     */
    public function hasAdminPermission(int $userId): bool
    {
        $query = "SELECT
            COUNT(*)
            FROM perm_templ_items
            LEFT JOIN perm_items ON perm_items.id = perm_templ_items.perm_id
            LEFT JOIN perm_templ ON perm_templ.id = perm_templ_items.templ_id
            LEFT JOIN users ON perm_templ.id = users.perm_templ
            WHERE users.id = :userId AND perm_items.name = 'user_is_ueberuser'";

        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return (bool)$stmt->fetchColumn();
    }
}
