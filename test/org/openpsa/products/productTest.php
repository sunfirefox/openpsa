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
class org_openpsa_products_productTest extends openpsa_testcase
{
    protected static $_group;

    public static function setUpBeforeClass()
    {
        self::$_group = self::create_class_object('org_openpsa_products_product_group_dba', array('code' => 'TEST-' . __CLASS__));
    }

    public function testCRUD()
    {
        $code = 'PRODUCT-TEST-' . __CLASS__;
        $product = new org_openpsa_products_product_dba();
        $product->code = $code;
        $product->productGroup = self::$_group->id;

        $_MIDCOM->auth->request_sudo('org.openpsa.products');
        $stat = $product->create();
        $this->assertTrue($stat);
        $this->register_object($product);

        $parent = $product->get_parent();
        $this->assertEquals($parent->guid, self::$_group->guid);

        $product->title = 'TEST TITLE';
        $stat = $product->update();
        $this->assertTrue($stat);

        $this->assertEquals($product->title, 'TEST TITLE');

        $stat = $product->delete();
        $this->assertTrue($stat);

        $_MIDCOM->auth->drop_sudo();
    }
}
?>