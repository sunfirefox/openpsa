/* function to parse the input and exchanges , with .
*/
function parse_input(string)
{
    return parseFloat(string.replace(',' , '.'));
}

function calculate_row(id)
{
    var price_unit = parse_input($("#price_per_unit_" + id).val()),
    units = parse_input($("#units_" + id).val()),
    sum = 0;
    //check if they are numbers
    if (   !isNaN(price_unit)
        && !isNaN(units))
    {
        sum = price_unit * units;
    }
    $('#row_sum_' + id).html(sum.toFixed(2));
}

function calculate_total(table)
{
    var total = 0;
    table.find('tbody tr').each(function()
    {
        if ($(this).find('input[type="checkbox"]').is(':checked'))
        {
            total += parse_input($(this).find('.units').val()) * parse_input($(this).find('.price_per_unit').val());
        }
    });

    table.find('tfoot .totals').text(total.toFixed(2));
}

function calculate_invoices_total(table)
{
    var total = 0,
    row_sum,
    totals_field = table.closest('.ui-jqgrid-view').find('.ui-jqgrid-ftable .sum'),
    decimal_separator = totals_field.text().slice(totals_field.text().length - 3, totals_field.text().length - 2);

    table.find('tbody tr').not('.jqgfirstrow').each(function()
    {
        row_sum = parseFloat($(this).find('.sum').prev().text());
        if (isNaN(row_sum))
        {
            return;
        }

        total += row_sum;
    });

    total = total.toFixed(2).replace('.', decimal_separator);
    totals_field.text(total);
}

function process_invoice(button, action)
{
    var id = button.parent().parent().attr('id');
    $.post(INVOICES_URL, {id: id, action: action}, function(data, status)
    {
        var parsed = jQuery.parseJSON(data);
        if (parsed.success === false)
        {
            $.midcom_services_uimessage_add(parsed.message);
            return;
        }
        var old_grid = button.closest('.ui-jqgrid-btable'),
        row_data = old_grid.getRowData(id),
        new_grid = jQuery('#' + parsed.new_status + '_invoices_grid');

        old_grid.delRowData(id);
        calculate_invoices_total(old_grid);

        if (new_grid.length < 1)
        {
            // Grid is not present yet, reload
            window.location.reload();
            return;
        }

        if (new_grid.jqGrid('getGridParam', 'datatype') === 'local')
        {
            row_data.action = parsed.action;
            row_data.due = parsed.due;
            jQuery('#' + parsed.new_status + '_invoices_grid').addRowData(row_data.id, row_data, "last");
            calculate_invoices_total(new_grid);
        }
        else
        {
            new_grid.trigger('reloadGrid');
        }
        $(window).trigger('resize');
        $.midcom_services_uimessage_add(parsed.message);
    });
}

function bind_invoice_actions(classes)
{
    classes = classes.replace(/ /g, '.');

    $('.org_openpsa_invoices.' + classes + ' .ui-jqgrid-btable')
        .delegate('button.mark_sent', 'click', function()
        {
            process_invoice($(this), 'mark_sent');
        })
        .delegate('button.mark_sent_per_mail', 'click', function()
        {
            process_invoice($(this), 'send_by_mail');
        })
        .delegate('button.mark_paid', 'click', function()
        {
            process_invoice($(this), 'mark_paid');
        });
}

$(document).ready(function()
{
    $('.projects table')
        .delegate('input[type="text"]', 'change', function()
        {
            var task_id = $(this).closest('tr').attr('id').replace('task_', '');
            calculate_row(task_id);
            calculate_total($(this).closest('table'));
        })
        .delegate('input[type="checkbox"]', 'change', function()
        {
            calculate_total($(this).closest('table'));
        })
        .each(function()
        {
            calculate_total($(this));
        });
});
