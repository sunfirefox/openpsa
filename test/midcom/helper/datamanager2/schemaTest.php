<?php
/**
 * @package openpsa.test
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * OpenPSA testcase
 *
 * @package openpsa.test
 */
class midcom_helper_datamanager2_schemaTest extends openpsa_testcase
{
    public function test_load_database()
    {
        $filename = 'file:/../test/midcom/helper/datamanager2/__files/schemadb_invoice.inc';
        $db = midcom_helper_datamanager2_schema::load_database($filename);
        $this->assertTrue(is_array($db));
        $this->assertEquals(1, sizeof($db));
        $this->assertTrue(array_key_exists('default', $db));
        $this->assertInstanceOf('midcom_helper_datamanager2_schema', $db['default']);
        $this->assertEquals('default', $db['default']->name);
        $this->assertEquals('invoice', $db['default']->description);
        $this->assertEquals(array(), $db['default']->customdata);
        $this->assertEquals(array(), $db['default']->validation);
        $this->assertEquals(array(), $db['default']->filters);
        $this->assertInstanceOf('midcom_services_i18n_l10n', $db['default']->l10n_schema);

        $db['default']->validation = array('TEST');
        $db = midcom_helper_datamanager2_schema::load_database($filename);
        $this->assertEquals(array(), $db['default']->validation);
    }
}
?>