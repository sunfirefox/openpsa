<?php
/**
 * @package net.nemein.wiki
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Wikipage delete handler
 *
 * @package net.nemein.wiki
 */
class net_nemein_wiki_handler_delete extends midcom_baseclasses_components_handler
{
    /**
     * The wikipage we're deleting
     *
     * @var net_nemein_wiki_wikipage
     */
    private $_page = null;

    /**
     * The Datamanager of the article to display
     *
     * @var midcom_helper_datamanager2_datamanager
     */
    private $_datamanager = null;

    /**
     * Internal helper, loads the datamanager for the current wikipage. Any error triggers a 500.
     */
    private function _load_datamanager()
    {
        $this->_datamanager = new midcom_helper_datamanager2_datamanager($this->_request_data['schemadb']);

        if (! $this->_datamanager->autoset_storage($this->_page))
        {
            throw new midcom_error("Failed to create a DM2 instance for article {$this->_page->id}.");
        }
    }

    /**
     * @param mixed $handler_id The ID of the handler.
     * @param array $args The argument list.
     * @param array &$data The local request data.
     */
    public function _handler_delete($handler_id, $args, &$data, $delete_mode = true)
    {
        $this->_page = $this->_master->load_page($args[0]);
        $this->_page->require_do('midgard:delete');

        if (array_key_exists('net_nemein_wiki_deleteok', $_POST))
        {
            $wikiword = $this->_page->title;
            if ($this->_page->delete())
            {
                midcom::get('uimessages')->add($this->_request_data['l10n']->get('net.nemein.wiki'), sprintf($this->_request_data['l10n']->get('page %s deleted'), $wikiword), 'ok');

                // Update the index
                $indexer = midcom::get('indexer');
                $indexer->delete($this->_page->guid);

                return new midcom_response_relocate('');
            }
            else
            {
                throw new midcom_error("Failed to delete wikipage, reason ".midcom_connection::get_error_string());
            }
        }

        $this->_load_datamanager();

        $this->_view_toolbar->add_item
        (
            array
            (
                MIDCOM_TOOLBAR_URL => "{$this->_page->name}/",
                MIDCOM_TOOLBAR_LABEL => $this->_request_data['l10n_midcom']->get('cancel'),
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/cancel.png',
            )
        );
        $this->_view_toolbar->bind_to($this->_page);

        $this->add_breadcrumb("{$this->_page->name}/", $this->_page->title);
        $this->add_breadcrumb("delete/{$this->_page->name}/", $this->_l10n_midcom->get('delete'));

        midcom::get('head')->set_pagetitle($this->_page->title);
    }

    /**
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array &$data The local request data.
     */
    public function _show_delete($handler_id, array &$data)
    {
        $this->_request_data['wikipage_view'] = $this->_datamanager->get_content_html();

        // Replace wikiwords
        if (array_key_exists('content', $this->_request_data['wikipage_view']))
        {
            $parser = new net_nemein_wiki_parser($this->_page);
            $this->_request_data['wikipage_view']['content'] = $parser->get_markdown($this->_request_data['wikipage_view']['content']);
        }

        midcom_show_style('view-wikipage-delete');
    }
}
?>