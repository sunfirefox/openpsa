<?php
/**
 * @package openpsa.test
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

if (!defined('OPENPSA_TEST_ROOT'))
{
    define('OPENPSA_TEST_ROOT', dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))) . DIRECTORY_SEPARATOR);
    require_once(OPENPSA_TEST_ROOT . 'rootfile.php');
}

/**
 * OpenPSA testcase
 *
 * @package openpsa.test
 */
class org_openpsa_contacts_handler_person_adminTest extends openpsa_testcase
{
    protected static $_person;

    public static function setUpBeforeClass()
    {
        self::$_person = self::create_user(true);
    }

    public function testHandler_edit()
    {
        midcom::get('auth')->request_sudo('org.openpsa.contacts');

        $data = $this->run_handler('org.openpsa.contacts', array('person', 'edit', self::$_person->guid));
        $this->assertEquals('person_edit', $data['handler_id']);

        midcom::get('auth')->drop_sudo();
    }

    public function testHandler_delete()
    {
        midcom::get('auth')->request_sudo('org.openpsa.contacts');

        $data = $this->run_handler('org.openpsa.contacts', array('person', 'delete', self::$_person->guid));
        $this->assertEquals('person_delete', $data['handler_id']);

        midcom::get('auth')->drop_sudo();
    }
}
?>