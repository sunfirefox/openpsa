<?php
/**
 * @package openpsa.test
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

if (!defined('OPENPSA_TEST_ROOT'))
{
    define('OPENPSA_TEST_ROOT', dirname(dirname(dirname(dirname(__FILE__)))) . DIRECTORY_SEPARATOR);
    require_once(OPENPSA_TEST_ROOT . 'rootfile.php');
}

/**
 * OpenPSA testcase
 *
 * @package openpsa.test
 */
class org_openpsa_documents_documentTest extends openpsa_testcase
{
    public function testCRUD()
    {
        $user = $this->create_user(true);

        $_MIDCOM->auth->request_sudo('org.openpsa.documents');
        $topic = $this->create_object('org_openpsa_documents_directory', array('name' => 'TEST_' . __CLASS__ . time()));

        $document = new org_openpsa_documents_document_dba();
        $document->topic = $topic->id;
        $stat = $document->create();
        $this->assertTrue($stat);
        $this->register_object($document);
        $document->refresh();
        $this->assertEquals('Document #' . $document->id, $document->title);
        $this->assertEquals($user->id, $document->author);

        $stat = $document->update();
        $this->assertTrue($stat);

        $stat = $document->delete();
        $this->assertTrue($stat);

        $_MIDCOM->auth->drop_sudo();
    }

}
?>