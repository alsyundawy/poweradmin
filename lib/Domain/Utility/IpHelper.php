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

namespace Poweradmin\Domain\Utility;

use Poweradmin\Domain\Service\DnsValidation\IPAddressValidator;

class IpHelper
{
    private static ?IPAddressValidator $ipValidator = null;

    /**
     * Get the IP validator instance
     *
     * @return IPAddressValidator
     */
    private static function getIPValidator(): IPAddressValidator
    {
        if (self::$ipValidator === null) {
            self::$ipValidator = new IPAddressValidator();
        }
        return self::$ipValidator;
    }

    public static function getProposedIPv4(string $name, string $zoneName, string $suffix): ?string
    {
        $cleanZoneName = str_replace($suffix, '', $zoneName);
        $proposedReverseIP = $name . '.' . $cleanZoneName;
        $ipParts = explode('.', $proposedReverseIP);

        if (count($ipParts) !== 4 || array_filter($ipParts, fn($part) => !is_numeric($part) || $part < 0 || $part > 255)) {
            return null;
        }

        return implode('.', array_reverse($ipParts));
    }

    public static function getProposedIPv6(string $name, string $zoneName, string $suffix): ?string
    {
        $cleanZoneName = str_replace($suffix, '', $zoneName);
        $proposedReverseIP = $name . '.' . $cleanZoneName;
        $ipParts = explode('.', $proposedReverseIP);

        if (count($ipParts) !== 32 || array_filter($ipParts, fn($part) => !ctype_xdigit($part) || strlen($part) !== 1)) {
            return null;
        }

        $reversedIpParts = array_reverse($ipParts);
        $ipv6 = implode('', $reversedIpParts);
        $ipv6 = preg_replace('/([0-9a-f]{4})/', '$1:', $ipv6);
        $ipv6 = rtrim($ipv6, ':');

        $ipValidator = self::getIPValidator();
        if (!$ipValidator->isValidIPv6($ipv6)) {
            return null;
        }

        return inet_ntop(inet_pton($ipv6));
    }
}
