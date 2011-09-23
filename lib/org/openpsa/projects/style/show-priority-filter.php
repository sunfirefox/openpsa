<div class="grid-filters">
<?php
if (array_key_exists('qf', $data))
{
    echo $data['l10n']->get('only tasks with priority') . ': ';
    $data['qf']->render();
}

if ($data['view'] == 'grid')
{
    $grid_id = $data['view_identifier'] . '_tasks_grid';

    echo ' ' . $_MIDCOM->i18n->get_string('group by', 'org.openpsa.core') . ': ';
    echo '<select id="chgrouping_' . $grid_id . '">';
    echo '<option value="project">' . $data['l10n']->get('project') . "</option>\n";
    echo '<option value="customer">' . $data['l10n']->get('customer') . "</option>\n";
    echo '<option value="manager">' . $data['l10n']->get('manager') . "</option>\n";
    echo '<option value="clear" ' . (($data['view_identifier'] == 'agreement') ? 'selected="selected"' : '' ) . ' >' . $_MIDCOM->i18n->get_string('no grouping', 'org.openpsa.core') . "</option>\n";
    echo '</select>';
} ?>
</div>