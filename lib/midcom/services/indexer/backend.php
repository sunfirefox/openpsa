<?php
/**
 * @package midcom.services
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * This class provides an abstract base class for all indexer backends.
 *
 * @package midcom.services
 * @see midcom_services_indexer
 */
interface midcom_services_indexer_backend
{
    /**
     * Adds a document to the index.
     *
     * @param array $documents A list of midcom_services_indexer_document objects.
     * @return boolean Indicating success.
     */
    public function index($documents);

    /**
     * Removes the document(s) with the given resource identifier(s) from the index.
     *
     * @param array $RIs The resource identifier(s) of the document(s) that should be deleted.
     * @return boolean Indicating success.
     */
    public function delete($RIs);

    /**
     * Clear the index completely or drop documents matching a query.
     *
     * This will drop the current index.
     *
     * @param Optional query constraint
     * @return boolean Indicating success.
     */
    public function delete_all($constraint);

    /**
     * Query the index and, if set, restrict the query by a given filter.
     *
     * @param string $query The query, which must suite the backends query syntax.
     * @param midcom_services_indexer_filter $filter An optional filter used to restrict the query. This may be null indicating no filter.
     * @return Array An array of documents matching the query, or false on a failure.
     */
    public function query($query, midcom_services_indexer_filter $filter = null);
}
?>