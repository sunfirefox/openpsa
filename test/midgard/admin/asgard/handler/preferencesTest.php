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
class midgard_admin_asgard_handler_preferencesTest extends openpsa_testcase
{
    public function testHandler_preferences()
    {
        $this->create_user(true);
        midcom::get('auth')->request_sudo('midgard.admin.asgard');

        $data = $this->run_handler('net.nehmer.static', array('__mfa', 'asgard', 'preferences'));
        $this->assertEquals('____mfa-asgard-preferences', $data['handler_id']);

        midcom::get('auth')->drop_sudo();
    }

    public function testHandler_preferences_ajax()
    {
        $this->create_user(true);
        midcom::get('auth')->request_sudo('midgard.admin.asgard');

        $data = $this->run_handler('net.nehmer.static', array('__mfa', 'asgard', 'preferences', 'ajax'));
        $this->assertEquals('____mfa-asgard-preferences_ajax', $data['handler_id']);

        midcom::get('auth')->drop_sudo();
    }

    public function testHandler_preferences_guid()
    {
        $this->create_user(true);
        midcom::get('auth')->request_sudo('midgard.admin.asgard');

        $person = $this->create_object('midcom_db_person');

        $data = $this->run_handler('net.nehmer.static', array('__mfa', 'asgard', 'preferences', $person->guid));
        $this->assertEquals('____mfa-asgard-preferences_guid', $data['handler_id']);

        midcom::get('auth')->drop_sudo();
    }
}
?>