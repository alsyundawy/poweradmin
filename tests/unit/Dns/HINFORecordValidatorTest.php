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

namespace unit\Dns;

use TestHelpers\BaseDnsTest;
use Poweradmin\Domain\Service\DnsValidation\HINFORecordValidator;
use Poweradmin\Infrastructure\Configuration\ConfigurationManager;

class HINFORecordValidatorTest extends BaseDnsTest
{
    private HINFORecordValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $configMock = $this->createMock(ConfigurationManager::class);
        $configMock->method('get')
            ->willReturnCallback(function ($section, $key) {
                if ($section === 'dns') {
                    if ($key === 'top_level_tld_check') {
                        return false;
                    }
                    if ($key === 'strict_tld_check') {
                        return false;
                    }
                }
                return 'example.com'; // Default value for tests
            });
        $this->validator = new HINFORecordValidator($configMock);
    }

    public function testValidateWithValidUnquotedData()
    {
        $content = 'INTEL UNIX';
        $name = 'host.example.com';
        $prio = 0;
        $ttl = 3600;
        $defaultTTL = 86400;

        $result = $this->validator->validate($content, $name, $prio, $ttl, $defaultTTL);

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->getErrors());

        $data = $result->getData();
        $this->assertEquals($content, $data['content']);
        $this->assertEquals($name, $data['name']);
        $this->assertEquals(0, $data['prio']);
        $this->assertEquals(3600, $data['ttl']);

        // Check for warnings
        $this->assertTrue($result->hasWarnings());
        $this->assertNotEmpty($result->getWarnings());

        // Security warning should be present
        $this->assertTrue($result->hasWarnings());
        $warnings = $result->getWarnings();
        $warningText = implode(' ', $warnings);
        $this->assertStringContainsString('security', $warningText);
    }

    public function testValidateWithValidQuotedData()
    {
        $content = '"INTEL CPU" "Linux OS"';
        $name = 'host.example.com';
        $prio = 0;
        $ttl = 3600;
        $defaultTTL = 86400;

        $result = $this->validator->validate($content, $name, $prio, $ttl, $defaultTTL);

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->getErrors());

        $data = $result->getData();
        $this->assertEquals($content, $data['content']);

        // Check for warnings
        $this->assertTrue($result->hasWarnings());
        $this->assertNotEmpty($result->getWarnings());

        // Security warning should be present
        $this->assertTrue($result->hasWarnings());
        $warnings = $result->getWarnings();
        $warningText = implode(' ', $warnings);
        $this->assertStringContainsString('security', $warningText);
    }

    public function testValidateWithMixedQuotedUnquotedData()
    {
        $content = 'INTEL "Linux OS"';
        $name = 'host.example.com';
        $prio = 0;
        $ttl = 3600;
        $defaultTTL = 86400;

        $result = $this->validator->validate($content, $name, $prio, $ttl, $defaultTTL);

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->getErrors());

        // Check for warnings
        $data = $result->getData();
        $this->assertTrue($result->hasWarnings());
        $this->assertNotEmpty($result->getWarnings());
    }

    public function testValidateWithMissingField()
    {
        $content = 'INTEL'; // Missing OS field
        $name = 'host.example.com';
        $prio = 0;
        $ttl = 3600;
        $defaultTTL = 86400;

        $result = $this->validator->validate($content, $name, $prio, $ttl, $defaultTTL);

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->getErrors());
        $this->assertStringContainsString('exactly two fields', $result->getFirstError());
    }

    public function testValidateWithTooManyFields()
    {
        $content = 'INTEL UNIX EXTRA'; // Too many fields
        $name = 'host.example.com';
        $prio = 0;
        $ttl = 3600;
        $defaultTTL = 86400;

        $result = $this->validator->validate($content, $name, $prio, $ttl, $defaultTTL);

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->getErrors());
        $this->assertStringContainsString('exactly two fields', $result->getFirstError());
    }

    public function testValidateWithEmptyField()
    {
        $content = 'INTEL ""'; // Empty second field
        $name = 'host.example.com';
        $prio = 0;
        $ttl = 3600;
        $defaultTTL = 86400;

        $result = $this->validator->validate($content, $name, $prio, $ttl, $defaultTTL);

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->getErrors());
        $this->assertStringContainsString('cannot be empty', $result->getFirstError());
    }

    public function testValidateWithImproperQuoting()
    {
        $content = '"INTEL CPU "UNIX"'; // Improperly quoted
        $name = 'host.example.com';
        $prio = 0;
        $ttl = 3600;
        $defaultTTL = 86400;

        $result = $this->validator->validate($content, $name, $prio, $ttl, $defaultTTL);

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->getErrors());
        $this->assertStringContainsString('HINFO record must have exactly two fields', $result->getFirstError());
    }

    public function testValidateWithQuotesInUnquotedField()
    {
        $content = 'INTEL"CPU UNIX'; // Quotes in unquoted field
        $name = 'host.example.com';
        $prio = 0;
        $ttl = 3600;
        $defaultTTL = 86400;

        $result = $this->validator->validate($content, $name, $prio, $ttl, $defaultTTL);

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->getErrors());
        $this->assertStringContainsString('HINFO record must have exactly two fields', $result->getFirstError());
    }

    public function testValidateWithUnclosedQuotes()
    {
        $content = '"INTEL CPU UNIX'; // Unclosed quotes
        $name = 'host.example.com';
        $prio = 0;
        $ttl = 3600;
        $defaultTTL = 86400;

        $result = $this->validator->validate($content, $name, $prio, $ttl, $defaultTTL);

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->getErrors());
        $this->assertStringContainsString('HINFO record must have exactly two fields', $result->getFirstError());
    }

    public function testValidateWithExcessivelyLongField()
    {
        // RFC 1035 limits character strings to 255 octets
        $longString = str_repeat('A', 256);
        $content = "\"$longString\" UNIX";
        $name = 'host.example.com';
        $prio = 0;
        $ttl = 3600;
        $defaultTTL = 86400;

        $result = $this->validator->validate($content, $name, $prio, $ttl, $defaultTTL);

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->getErrors());
        $this->assertStringContainsString('exceeds maximum length', $result->getFirstError());
        $this->assertStringContainsString('255', $result->getFirstError());
    }

    public function testValidateWithInvalidHostname()
    {
        $content = 'INTEL UNIX';
        $name = '-invalid.example.com'; // Invalid hostname
        $prio = 0;
        $ttl = 3600;
        $defaultTTL = 86400;

        $result = $this->validator->validate($content, $name, $prio, $ttl, $defaultTTL);

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->getErrors());
    }

    public function testValidateWithNonZeroPriority()
    {
        $content = 'INTEL UNIX';
        $name = 'host.example.com';
        $prio = 10; // HINFO records must use priority 0
        $ttl = 3600;
        $defaultTTL = 86400;

        $result = $this->validator->validate($content, $name, $prio, $ttl, $defaultTTL);

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->getErrors());
        $this->assertStringContainsString('Priority field', $result->getFirstError());
    }

    public function testValidateWithEmptyPriority()
    {
        $content = 'INTEL UNIX';
        $name = 'host.example.com';
        $prio = ''; // Empty priority should default to 0
        $ttl = 3600;
        $defaultTTL = 86400;

        $result = $this->validator->validate($content, $name, $prio, $ttl, $defaultTTL);

        $this->assertTrue($result->isValid());
        $data = $result->getData();
        $this->assertEquals(0, $data['prio']);
    }

    public function testValidateWithDefaultTTL()
    {
        $content = 'INTEL UNIX';
        $name = 'host.example.com';
        $prio = 0;
        $ttl = ''; // Empty TTL should use default
        $defaultTTL = 86400;

        $result = $this->validator->validate($content, $name, $prio, $ttl, $defaultTTL);

        $this->assertTrue($result->isValid());
        $data = $result->getData();
        $this->assertEquals(86400, $data['ttl']);
    }

    public function testValidateWithNonStandardValues()
    {
        $content = '"My Custom CPU" "Custom OS"';
        $name = 'host.example.com';
        $prio = 0;
        $ttl = 3600;
        $defaultTTL = 86400;

        $result = $this->validator->validate($content, $name, $prio, $ttl, $defaultTTL);

        $this->assertTrue($result->isValid());
        $data = $result->getData();

        // Check for warnings about non-standard values
        $this->assertTrue($result->hasWarnings());
        $this->assertNotEmpty($result->getWarnings());

        $this->assertTrue($result->hasWarnings());
        $warnings = $result->getWarnings();
        $warningText = implode(' ', $warnings);
        $this->assertStringContainsString('standard', $warningText);
        $this->assertStringContainsString('CPU type', $warningText);
        $this->assertStringContainsString('OS type', $warningText);
    }

    public function testValidateWithTooLongField()
    {
        // RFC 1035 limits character strings to 255 octets
        $longString = str_repeat('A', 256);
        $content = "\"$longString\" UNIX";
        $name = 'host.example.com';
        $prio = 0;
        $ttl = 3600;
        $defaultTTL = 86400;

        $result = $this->validator->validate($content, $name, $prio, $ttl, $defaultTTL);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('255 characters', $result->getFirstError());
    }

    /**
     * Test that warnings are correctly handled with the updated ValidationResult pattern
     */
    public function testWarningHandling()
    {
        // Standard values with spaces that need quotes
        $content = '"Intel CPU" "Windows OS"';
        $name = 'host.example.com';
        $prio = 0;
        $ttl = 3600;
        $defaultTTL = 86400;

        $result = $this->validator->validate($content, $name, $prio, $ttl, $defaultTTL);

        // Verify the result is valid
        $this->assertTrue($result->isValid());

        // Verify warnings are accessible via hasWarnings() and getWarnings()
        $this->assertTrue($result->hasWarnings());
        $warnings = $result->getWarnings();
        $this->assertIsArray($warnings);
        $this->assertNotEmpty($warnings);

        // There should be at least one security warning
        $securityWarningFound = false;
        foreach ($warnings as $warning) {
            if (stripos($warning, 'security') !== false) {
                $securityWarningFound = true;
                break;
            }
        }
        $this->assertTrue($securityWarningFound, 'Security warning should be present');

        // Verify that warnings are not in the data array (old pattern)
        $data = $result->getData();
        $this->assertArrayNotHasKey('warnings', $data);
    }
}
