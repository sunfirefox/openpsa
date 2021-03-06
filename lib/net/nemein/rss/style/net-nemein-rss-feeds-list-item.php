<?php
$topic_prefix = midcom_core_context::get()->get_key(MIDCOM_CONTEXT_ANCHORPREFIX);
$prefix = $topic_prefix . '__feeds/rss/';

echo "<li><a href=\"{$data['feed']->url}\"><img src=\"" . MIDCOM_STATIC_URL . "/net.nemein.rss/feed-icon-14x14.png\" alt=\"{$data['feed']->url}\" title=\"{$data['feed']->url}\" /></a>";
if ($data['feed']->can_do('midgard:update'))
{
    echo "<a href=\"{$prefix}edit/{$data['feed']->guid}/\">{$data['feed']->title}</a>\n";
}
else
{
    echo "{$data['feed']->title}\n";
}
echo "    <ul class=\"details\">\n";
echo "        <li></li>\n";

switch ($data['topic']->component)
{
    case 'net.nehmer.blog':
        $qb = midcom_db_article::new_query_builder();
        $qb->add_constraint('topic', '=', $data['topic']->id);
        $qb->add_constraint('extra1', 'LIKE', "%|{$data['feed_category']}|%");
        $data['feed_items'] = $qb->count_unchecked();
        echo "        <li><a href=\"{$topic_prefix}category/{$data['feed_category']}/\">" . sprintf($data['l10n']->get('%s items'), $data['feed_items']) . "</a></li>\n";
        break;
}

if ($data['feed']->latestupdate)
{
    echo "        <li>" . sprintf($data['l10n']->get('latest item from %s'), strftime('%x %X', $data['feed']->latestupdate)) . "</li>\n";
}
if ($data['feed']->latestfetch)
{
    echo "        <li>" . sprintf($data['l10n']->get('latest fetch %s'), strftime('%x %X', $data['feed']->latestfetch)) . "</li>\n";
}
echo "    </ul>\n";

$data['feed_toolbar'] = new midcom_helper_toolbar();
if ($data['feed']->can_do('midgard:update'))
{
    $data['feed_toolbar']->add_item
    (
        array
        (
            MIDCOM_TOOLBAR_URL => "__feeds/rss/edit/{$data['feed']->guid}/",
            MIDCOM_TOOLBAR_LABEL => $data['l10n_midcom']->get('edit'),
            MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/edit.png',
        )
    );
}

if ($data['topic']->can_do('midgard:create'))
{
    $data['feed_toolbar']->add_item
    (
        array
        (
            MIDCOM_TOOLBAR_URL => "__feeds/rss/fetch/{$data['feed']->guid}/",
            MIDCOM_TOOLBAR_LABEL => $data['l10n']->get('refresh feed'),
            MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/stock_refresh.png',
        )
    );
}

if ($data['feed']->can_do('midgard:delete'))
{
    $data['feed_toolbar']->add_item
    (
        array
        (
            MIDCOM_TOOLBAR_URL => "__feeds/rss/delete/{$data['feed']->guid}/",
            MIDCOM_TOOLBAR_LABEL => $data['l10n']->get('delete feed'),
            MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/trash.png',
        )
    );
}
echo $data['feed_toolbar']->render();
echo "</li>\n";
?>