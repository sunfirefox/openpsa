<?php
/**
 * @package openpsa.test
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

if (!defined('OPENPSA_TEST_ROOT'))
{
    define('OPENPSA_TEST_ROOT', dirname(dirname(dirname(dirname(dirname(__FILE__))))) . DIRECTORY_SEPARATOR);
    require_once(OPENPSA_TEST_ROOT . 'rootfile.php');
}

/**
 * OpenPSA testcase
 *
 * @package openpsa.test
 */
class midgard_admin_asgard_handler_componentsTest extends openpsa_testcase
{
    public function testHandler_list()
    {
        $this->create_user(true);
        midcom::get('auth')->request_sudo('midgard.admin.asgard');

        $data = $this->run_handler('net.nehmer.static', array('__mfa', 'asgard', 'components'));
        $this->assertEquals('____mfa-asgard-components', $data['handler_id']);

        midcom::get('auth')->drop_sudo();
    }

    public function testHandler_component()
    {
        $this->create_user(true);
        midcom::get('auth')->request_sudo('midgard.admin.asgard');

        $data = $this->run_handler('net.nehmer.static', array('__mfa', 'asgard', 'components', 'net.nemein.wiki'));
        $this->assertEquals('____mfa-asgard-components_component', $data['handler_id']);

        midcom::get('auth')->drop_sudo();
    }
}
?>