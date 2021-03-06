<?php
/**
 * @package org.openpsa.reports
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Deliverable reports
 *
 * @package org.openpsa.reports
 */
class org_openpsa_reports_handler_sales_report extends org_openpsa_reports_handler_base
{
    public function _on_initialize()
    {
        $this->module = 'sales';
        $this->_initialize_datamanager();
    }

    /**
     * @param mixed $handler_id The ID of the handler.
     * @param array $args The argument list.
     * @param array &$data The local request data.
     */
    public function _handler_generator($handler_id, array $args, array &$data)
    {
        midcom::get('auth')->require_valid_user();

        $this->_generator_load_redirect($args);
        $this->_handler_generator_style();

        $data['invoices'] = Array();

        // Calculate time range
        $data['start'] = $this->_request_data['query_data']['start'];
        $data['end'] = $this->_request_data['query_data']['end'];

        // List sales projects
        $salesproject_mc = org_openpsa_sales_salesproject_dba::new_collector('metadata.deleted', false);
        $salesproject_mc->add_constraint('status', '<>', org_openpsa_sales_salesproject_dba::STATUS_LOST);

        if ($this->_request_data['query_data']['resource'] != 'all')
        {
            $this->_request_data['query_data']['resource_expanded'] = $this->_expand_resource($this->_request_data['query_data']['resource']);
            if (!empty($this->_request_data['query_data']['resource_expanded']))
            {
                $salesproject_mc->add_constraint('owner', 'IN', $this->_request_data['query_data']['resource_expanded']);
            }
        }
        $salesprojects = $salesproject_mc->get_values('id');

        // List deliverables related to the sales projects
        $deliverable_mc = org_openpsa_sales_salesproject_deliverable_dba::new_collector('metadata.deleted', false);
        $deliverable_mc->add_constraint('state', '<>', org_openpsa_sales_salesproject_deliverable_dba::STATUS_DECLINED);
        $deliverable_mc->add_constraint('salesproject', 'IN', $salesprojects);
        $deliverables = $deliverable_mc->get_values('id');

        foreach ($deliverables as $guid => $id)
        {
            $data['invoices'][$guid] = $this->_get_deliverable_invoices($id);
        }

        $this->add_stylesheet(MIDCOM_STATIC_URL . "/org.openpsa.core/list.css");
    }

    private function _get_deliverable_invoices($id)
    {
        $mc = org_openpsa_invoices_invoice_item_dba::new_collector('deliverable', $id);
        $ids = $mc->get_values('invoice');
        if (sizeof($ids) < 1)
        {
            return array();
        }
        $qb = org_openpsa_invoices_invoice_dba::new_query_builder();
        $qb->add_constraint('id', 'IN', $ids);
        $qb->add_constraint('sent', '>=', $this->_request_data['start']);
        $qb->add_constraint('sent', '<=', $this->_request_data['end']);
        return $qb->execute();
    }

    /**
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array &$data The local request data.
     */
    public function _show_generator($handler_id, array &$data)
    {
        midcom_show_style('sales_report-deliverable-start');

        // Quick workaround to Bergies lazy determination of whether this is user's or everyone's report...
        if ($this->_request_data['query_data']['resource'] == 'user:' . midcom::get('auth')->user->guid)
        {
            // My report
            $data['handler_id'] = 'deliverable_report';
        }
        else
        {
            // Generic report
            $data['handler_id'] = 'sales_report';
        }
        midcom_show_style('sales_report-deliverable-header');

        $invoices_node = midcom_helper_misc::find_node_by_component('org.openpsa.invoices');

        $sums_per_person = Array();
        $sums_all = Array
        (
            'price'  => 0,
            'cost'   => 0,
            'profit' => 0,
        );
        $odd = true;
        foreach ($data['invoices'] as $deliverable_guid => $invoices)
        {
            if (count($invoices) == 0)
            {
                // No invoices sent in this project, skip
                continue;
            }

            try
            {
                $deliverable = org_openpsa_sales_salesproject_deliverable_dba::get_cached($deliverable_guid);
                $product = org_openpsa_products_product_dba::get_cached($deliverable->product);
                $salesproject = org_openpsa_sales_salesproject_dba::get_cached($deliverable->salesproject);
                $customer = midcom_db_group::get_cached($salesproject->customer);
            }
            catch (midcom_error $e)
            {
                continue;
            }
            if (!array_key_exists($salesproject->owner, $sums_per_person))
            {
                $sums_per_person[$salesproject->owner] = Array
                (
                    'price'  => 0,
                    'cost'   => 0,
                    'profit' => 0,
                );
            }

            // Calculate the price and cost from invoices
            $invoice_price = 0;
            $data['invoice_string'] = '';
            $invoice_cycle_numbers = Array();
            foreach ($invoices as $invoice)
            {
                $invoice_price += $invoice->sum;
                $invoice_class = $invoice->get_status();

                if ($invoices_node)
                {
                    $invoice_label = "<a class=\"{$invoice_class}\" href=\"{$invoices_node[MIDCOM_NAV_ABSOLUTEURL]}invoice/{$invoice->guid}/\">" . $invoice->get_label() . "</a>";
                }
                else
                {
                    $invoice_label = $invoice->get_label();
                }

                if ($product->delivery == org_openpsa_products_product_dba::DELIVERY_SUBSCRIPTION)
                {
                    $invoice_cycle_numbers[] = (int) $invoice->parameter('org.openpsa.sales', 'cycle_number');
                }

                $data['invoice_string'] .= "<li class=\"{$invoice_class}\">{$invoice_label}</li>\n";
            }

            if ($product->delivery == org_openpsa_products_product_dba::DELIVERY_SUBSCRIPTION)
            {
                // This is a subscription, it should be shown only if it is the first invoice
                if (!in_array(1, $invoice_cycle_numbers))
                {
                    continue;
                    // This will skip to next deliverable
                }

                $scheduler = new org_openpsa_invoices_scheduler($deliverable);

                if ($deliverable->end == 0)
                {
                    // Subscription doesn't have an end date, use specified amount of months for calculation
                    $cycles = $scheduler->calculate_cycles($this->_config->get('subscription_profit_months'));
                    $data['calculation_basis'] = sprintf($data['l10n']->get('%s cycles in %s months'), $cycles, $this->_config->get('subscription_profit_months'));
                }
                else
                {
                    $cycles = $scheduler->calculate_cycles();
                    $data['calculation_basis'] = sprintf($data['l10n']->get('%s cycles, %s - %s'), $cycles, strftime('%x', $deliverable->start), strftime('%x', $deliverable->end));
                }

                $price = $deliverable->price * $cycles;
                $cost = $deliverable->cost * $cycles;
            }
            else
            {
                // This is a single delivery, calculate cost as percentage as it may be invoiced in pieces
                if ($deliverable->price)
                {
                    $cost_percentage = 100 / $deliverable->price * $invoice_price;
                    $cost = $deliverable->cost / 100 * $cost_percentage;
                }
                else
                {
                    $cost_percentage = 100;
                    $cost = $deliverable->cost;
                }
                $price = $invoice_price;
                $data['calculation_basis'] = sprintf($data['l10n']->get('%s%% of %s'), round($cost_percentage), $deliverable->price);
            }

            // And now just count the profit
            $profit = $price - $cost;
            $data['customer'] = $customer;
            $data['salesproject'] = $salesproject;
            $data['deliverable'] = $deliverable;

            $data['price'] = $price;
            $sums_per_person[$salesproject->owner]['price'] += $price;
            $sums_all['price'] += $price;

            $data['cost'] = $cost;
            $sums_per_person[$salesproject->owner]['cost'] += $cost;
            $sums_all['cost'] += $cost;

            $data['profit'] = $profit;
            $sums_per_person[$salesproject->owner]['profit'] += $profit;
            $sums_all['profit'] += $profit;

            if ($odd)
            {
                $data['row_class'] = '';
                $odd = false;
            }
            else
            {
                $data['row_class'] = ' class="even"';
                $odd = true;
            }

            midcom_show_style('sales_report-deliverable-item');
        }

        $data['sums_per_person'] = $sums_per_person;
        $data['sums_all'] = $sums_all;
        midcom_show_style('sales_report-deliverable-footer');
        midcom_show_style('sales_report-deliverable-end');
    }
}
?>