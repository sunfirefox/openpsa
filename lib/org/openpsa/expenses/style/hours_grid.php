<?php
$prefix = midcom_core_context::get()->get_key(MIDCOM_CONTEXT_ANCHORPREFIX);
$reporters = $data['reporters'];
$reports = $data['reports'];

$entries = array();

$grid_id = $data['status'] . '_hours_grid';

foreach ($reports['reports'] as $report)
{
    $entry = array();

    $description = "<em>" . $data['l10n']->get('no description given') . "</em>";
    if (! preg_match("/^[\W]*?$/", $report->description))
    {
        $description = $report->description;
    }

    $entry['id'] = $report->id;
    $entry['date'] = strftime('%Y-%m-%d', $report->date);

    if ($data['mode'] != 'simple')
    {
        $task = org_openpsa_projects_task_dba::get_cached($report->task);
        $entry['task'] = "<a href=\"{$prefix}hours/task/{$task->guid}/\">" . $task->get_label() . "</a>";
        $entry['index_task'] = $task->get_label();
    }

    $entry['index_description'] = $description;
    $entry['description'] = '<a href="' . $prefix . 'hours/edit/' . $report->guid . '">' . $description . '</a>';

    $entry['reporter'] = $reporters[$report->person];

    $entry['index_hours'] = $report->hours;
    $entry['hours'] = $report->hours . ' ' . $data['l10n']->get('hours unit');

    $entries[] = $entry;
}
$grid = new org_openpsa_widgets_grid($grid_id, 'local');

$grid->set_column('date', $data['l10n']->get('date'), "width: 80, align: 'center', formatter: 'date', fixed: true")
    ->set_column('reporter', $data['l10n']->get('person'), "width: 80, classes: 'ui-ellipsis'");

if ($data['mode'] != 'simple')
{
    $grid->set_column('task', $data['l10n']->get('task'), "classes: 'ui-ellipsis'", 'string');
}
$grid->set_column('hours', $data['l10n']->get('hours'), "width: 50, align: 'right'", 'integer')
    ->set_column('description', $data['l10n']->get('description'), "width: 250, classes: 'ui-ellipsis'", 'string');

$grid->set_option('loadonce', true)
    ->set_option('caption', $data['subheading'])
    ->set_option('sortname', 'date')
    ->set_option('sortorder', 'desc')
    ->set_option('multiselect', true);

$grid->add_pager();

$footer_data = array
(
    'date' => $data['l10n']->get('total'),
    'hours' => $reports['hours']
);

$grid->set_footer_data($footer_data);
?>
<div class="org_openpsa_expenses <?php echo $data['status']; ?> batch-processing full-width fill-height" style="margin-bottom: 1em">

<?php $grid->render($entries); ?>

<form id="form_&(grid_id);" method="post" action="<?php echo $data['action_target_url']; ?>">
<input type="hidden" name="relocate_url" value="<?php echo $_SERVER['REQUEST_URI']; ?>" />
</form>

</div>

<script type="text/javascript">

org_openpsa_batch_processing.initialize(
{
    id: '&(grid_id);',
    options: <?php echo json_encode($data['action_options']); ?>
});
</script>

